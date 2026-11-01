<?php
declare(strict_types=1);

namespace App;

/** Exams and grade entry. */
final class Exams
{
    public static function create(array $user, int $subjectId, string $name, string $date, string $max): int
    {
        if (!Policy::canManageExam($user, $subjectId)) {
            throw new RuleException('err.forbidden');
        }
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 60) {
            throw new RuleException('exam.err.name');
        }
        if (!valid_date($date)) {
            throw new RuleException('slot.err.date');
        }
        if (!is_numeric($max) || (float)$max <= 0 || (float)$max > 1000) {
            throw new RuleException('exam.err.max');
        }
        return Db::insert(
            'INSERT INTO exams (subject_id, name, exam_date, max_score, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$subjectId, $name, $date, (float)$max, $user['id'], Clock::now()->format('Y-m-d H:i:s')]
        );
    }

    public static function find(int $id): ?array
    {
        return Db::one(
            'SELECT e.id, e.subject_id, e.name, e.exam_date, e.max_score, sj.slug AS subject_slug, sj.name AS subject_name
               FROM exams e JOIN subjects sj ON sj.id = e.subject_id WHERE e.id = ?',
            [$id]
        );
    }

    /** Exams the user may manage, newest first, with how many students have a score. */
    public static function listFor(array $user): array
    {
        $sql = "SELECT e.id, e.subject_id, e.name, e.exam_date, e.max_score, sj.slug AS subject_slug, sj.name AS subject_name,
                       (SELECT COUNT(*) FROM grades g WHERE g.exam_id = e.id) AS graded
                  FROM exams e JOIN subjects sj ON sj.id = e.subject_id";
        $params = [];
        if ($user['role'] === Role::Teacher) {
            if (!$user['subject_ids']) {
                return [];
            }
            $sql .= ' WHERE e.subject_id IN (' . implode(',', array_fill(0, count($user['subject_ids']), '?')) . ')';
            $params = $user['subject_ids'];
        }
        return Db::all($sql . ' ORDER BY e.exam_date DESC, e.id DESC', $params);
    }

    /**
     * Save scores for an exam. $scores = [studentId => "85.5" | ""]. Empty clears the grade.
     * @return int number of scores saved
     */
    public static function saveScores(array $user, int $examId, array $scores): int
    {
        $exam = self::find($examId);
        if (!$exam) {
            throw new RuleException('err.notfound');
        }
        if (!Policy::canManageExam($user, (int)$exam['subject_id'])) {
            throw new RuleException('err.forbidden');
        }
        $max = (float)$exam['max_score'];
        $clean = [];
        foreach ($scores as $studentId => $raw) {
            $raw = trim(str_replace(',', '.', (string)$raw));
            if ($raw === '') {
                $clean[(int)$studentId] = null;
                continue;
            }
            if (!is_numeric($raw) || (float)$raw < 0 || (float)$raw > $max) {
                throw new RuleException('exam.err.score', ['max' => $max]);
            }
            $clean[(int)$studentId] = round((float)$raw, 1);
        }
        $valid = array_flip(array_column(Repo::students($user), 'id'));
        $saved = 0;
        Db::transaction(function () use ($clean, $examId, $valid, &$saved): void {
            foreach ($clean as $studentId => $score) {
                if (!isset($valid[$studentId])) {
                    continue;
                }
                $exists = Db::val('SELECT id FROM grades WHERE exam_id = ? AND student_id = ?', [$examId, $studentId]);
                if ($score === null) {
                    if ($exists) {
                        Db::run('DELETE FROM grades WHERE id = ?', [$exists]);
                    }
                    continue;
                }
                if ($exists) {
                    Db::run('UPDATE grades SET score = ?, updated_at = ? WHERE id = ?', [$score, Clock::now()->format('Y-m-d H:i:s'), $exists]);
                } else {
                    Db::run('INSERT INTO grades (exam_id, student_id, score) VALUES (?, ?, ?)', [$examId, $studentId, $score]);
                }
                $saved++;
            }
        });
        return $saved;
    }
}
