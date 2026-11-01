<?php
declare(strict_types=1);

namespace App;

/** Read-only queries used by several pages. (Writes live in the service classes.) */
final class Repo
{
    private const APPT_SELECT = "
        SELECT a.id, a.slot_id, a.student_id, a.status, a.is_active, a.lesson_date, a.lesson_order,
               su.full_name AS student_name, sl.teacher_id, tu.full_name AS teacher_name,
               COALESCE(sl.room, t.room) AS room, sj.id AS subject_id, sj.slug AS subject_slug,
               sj.name AS subject_name, b.starts_at, b.ends_at
          FROM appointments a
          JOIN slots sl         ON sl.id = a.slot_id
          JOIN users su         ON su.id = a.student_id
          JOIN users tu         ON tu.id = sl.teacher_id
          LEFT JOIN teachers t  ON t.user_id = sl.teacher_id
          JOIN subjects sj      ON sj.id = sl.subject_id
          JOIN bell_schedule b  ON b.lesson_order = a.lesson_order";

    /** @return array<int, array{starts_at:string, ends_at:string}> keyed by lesson number */
    public static function bell(): array
    {
        $out = [];
        foreach (Db::all('SELECT lesson_order, starts_at, ends_at FROM bell_schedule ORDER BY lesson_order') as $r) {
            $out[(int)$r['lesson_order']] = ['starts_at' => $r['starts_at'], 'ends_at' => $r['ends_at']];
        }
        return $out;
    }

    public static function subjects(): array
    {
        return Db::all('SELECT id, slug, name FROM subjects ORDER BY id');
    }

    public static function subject(int $id): ?array
    {
        return Db::one('SELECT id, slug, name FROM subjects WHERE id = ?', [$id]);
    }

    public static function slot(int $id): ?array
    {
        return Db::one(
            "SELECT sl.id, sl.teacher_id, sl.subject_id, sl.lesson_date, sl.lesson_order, sl.capacity,
                    sl.closed_at, COALESCE(sl.room, t.room) AS room, b.starts_at, b.ends_at,
                    tu.full_name AS teacher_name, tu.is_active AS teacher_active,
                    sj.slug AS subject_slug, sj.name AS subject_name,
                    (SELECT COUNT(*) FROM appointments a WHERE a.slot_id = sl.id AND a.is_active = 1) AS taken
               FROM slots sl
               JOIN users tu        ON tu.id = sl.teacher_id
               LEFT JOIN teachers t ON t.user_id = sl.teacher_id
               JOIN subjects sj     ON sj.id = sl.subject_id
               JOIN bell_schedule b ON b.lesson_order = sl.lesson_order
              WHERE sl.id = ?",
            [$id]
        );
    }

    /** Open slots (all subjects) between two dates, with how many seats are taken. */
    public static function openSlots(string $from, string $to, ?int $subjectId = null, ?int $teacherId = null, int $viewerId = 0): array
    {
        $sql = "SELECT sl.id, sl.teacher_id, sl.subject_id, sl.lesson_date, sl.lesson_order, sl.capacity,
                       sl.closed_at, COALESCE(sl.room, t.room) AS room, b.starts_at, b.ends_at,
                       tu.full_name AS teacher_name, sj.slug AS subject_slug, sj.name AS subject_name,
                       (SELECT COUNT(*) FROM appointments a WHERE a.slot_id = sl.id AND a.is_active = 1) AS taken,
                       (SELECT COUNT(*) FROM appointments a WHERE a.slot_id = sl.id AND a.is_active = 1
                           AND a.student_id = ?) AS mine
                  FROM slots sl
                  JOIN users tu        ON tu.id = sl.teacher_id
                  LEFT JOIN teachers t ON t.user_id = sl.teacher_id
                  JOIN subjects sj     ON sj.id = sl.subject_id
                  JOIN bell_schedule b ON b.lesson_order = sl.lesson_order
                 WHERE sl.closed_at IS NULL AND tu.is_active = 1 AND sl.lesson_date BETWEEN ? AND ?";
        $params = [$viewerId, $from, $to];
        if ($subjectId !== null) {
            $sql .= ' AND sl.subject_id = ?';
            $params[] = $subjectId;
        }
        if ($teacherId !== null) {
            $sql .= ' AND sl.teacher_id = ?';
            $params[] = $teacherId;
        }
        $sql .= ' ORDER BY sl.lesson_date, sl.lesson_order, tu.full_name';
        return Db::all($sql, $params);
    }

    /** Live bookings for a set of slots, grouped by slot id. */
    public static function bookingsBySlot(array $slotIds): array
    {
        if (!$slotIds) {
            return [];
        }
        $in = implode(',', array_fill(0, count($slotIds), '?'));
        $out = [];
        foreach (Db::all(
            "SELECT a.id, a.slot_id, a.student_id, a.status, u.full_name AS student_name
               FROM appointments a JOIN users u ON u.id = a.student_id
              WHERE a.is_active = 1 AND a.slot_id IN ($in) ORDER BY u.full_name",
            array_values($slotIds)
        ) as $r) {
            $out[(int)$r['slot_id']][] = $r;
        }
        return $out;
    }

    public static function appointment(int $id): ?array
    {
        return Db::one(self::APPT_SELECT . ' WHERE a.id = ?', [$id]);
    }

    /**
     * Appointments for a list screen.
     * $opts: student_ids[], teacher_id, tab (upcoming|past|cancelled), q, subject_id, page, per, limit
     * @return array{rows: array, total: int}
     */
    public static function appointments(array $opts): array
    {
        $now = Clock::now();
        $d = $now->format('Y-m-d');
        $tm = $now->format('H:i');
        $where = [];
        $params = [];

        if (isset($opts['student_ids'])) {
            if (!$opts['student_ids']) {
                return ['rows' => [], 'total' => 0];
            }
            $where[] = 'a.student_id IN (' . implode(',', array_fill(0, count($opts['student_ids']), '?')) . ')';
            array_push($params, ...array_values($opts['student_ids']));
        }
        if (!empty($opts['class_ids'])) {
            $where[] = 'su.id IN (SELECT s.user_id FROM students s WHERE s.class_id IN (' . Scope::marks($opts['class_ids']) . '))';
            array_push($params, ...array_values($opts['class_ids']));
        }
        if (isset($opts['teacher_id'])) {
            $where[] = 'sl.teacher_id = ?';
            $params[] = $opts['teacher_id'];
        }
        if (!empty($opts['subject_id'])) {
            $where[] = 'sl.subject_id = ?';
            $params[] = (int)$opts['subject_id'];
        }
        if (!empty($opts['q'])) {
            $where[] = "su.full_name LIKE ? ESCAPE '!'";
            $params[] = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], (string)$opts['q']) . '%';
        }

        $tab = $opts['tab'] ?? 'upcoming';
        $order = 'a.lesson_date, a.lesson_order, su.full_name';
        if ($tab === 'cancelled') {
            $where[] = 'a.is_active IS NULL';
            $order = 'a.lesson_date DESC, a.lesson_order DESC';
        } elseif ($tab === 'past') {
            $where[] = 'a.is_active = 1 AND (a.lesson_date < ? OR (a.lesson_date = ? AND b.starts_at <= ?))';
            array_push($params, $d, $d, $tm);
            $order = 'a.lesson_date DESC, a.lesson_order DESC, su.full_name';
        } else {
            $where[] = 'a.is_active = 1 AND (a.lesson_date > ? OR (a.lesson_date = ? AND b.starts_at > ?))';
            array_push($params, $d, $d, $tm);
        }
        $w = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $from = substr(self::APPT_SELECT, strpos(self::APPT_SELECT, 'FROM appointments a'));
        $total = (int)Db::val('SELECT COUNT(*) ' . $from . $w, $params);

        $limit = (int)($opts['limit'] ?? 0);
        $per = (int)($opts['per'] ?? 20);
        $page = max(1, (int)($opts['page'] ?? 1));
        $sql = self::APPT_SELECT . $w . ' ORDER BY ' . $order;
        $sql .= $limit > 0 ? ' LIMIT ' . $limit : ' LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per);
        return ['rows' => Db::all($sql, $params), 'total' => $total];
    }

    /** Lessons the student attended or missed, newest first (for the statistics page). */
    /** Lessons the student attended or missed. Pass a teacher id to see only that teacher's lessons. */
    public static function attendanceRows(int $studentId, ?int $teacherId = null): array
    {
        return Db::all(
            "SELECT a.lesson_date, a.status FROM appointments a JOIN slots sl ON sl.id = a.slot_id
              WHERE a.student_id = ? AND a.is_active = 1 AND a.status IN ('attended', 'no_show')" . ($teacherId ? ' AND sl.teacher_id = ?' : '') . "
              ORDER BY a.lesson_date",
            $teacherId ? [$studentId, $teacherId] : [$studentId]
        );
    }

    /** A student's grades, oldest exam first. class_avg = average score of the student's own class in that exam. */
    public static function grades(int $studentId, int $subjectId = 0, ?array $onlySubjects = null): array
    {
        if ($onlySubjects !== null && !$onlySubjects) {
            return [];
        }
        $params = [$studentId];
        $extra = '';
        if ($subjectId > 0) {
            $extra .= ' AND e.subject_id = ?';
            $params[] = $subjectId;
        }
        if ($onlySubjects !== null) {   // e.g. a teacher: only their own subjects
            $extra .= ' AND e.subject_id IN (' . Scope::marks($onlySubjects) . ')';
            array_push($params, ...array_values($onlySubjects));
        }
        return Db::all(
            "SELECT g.score, e.id AS exam_id, e.name AS exam_name, e.exam_date, e.max_score,
                    sj.slug AS subject_slug, sj.name AS subject_name,
                    (SELECT AVG(g2.score) FROM grades g2 JOIN students s2 ON s2.user_id = g2.student_id
                      WHERE g2.exam_id = e.id
                        AND s2.class_id = (SELECT s0.class_id FROM students s0 WHERE s0.user_id = g.student_id)) AS class_avg
               FROM grades g
               JOIN exams e     ON e.id = g.exam_id
               JOIN subjects sj ON sj.id = e.subject_id
              WHERE g.student_id = ?" . $extra . "
              ORDER BY e.exam_date, e.id",
            $params
        );
    }

    /**
     * How one class did in each exam of a subject, oldest first (for the "track progress" graph).
     * Scores are turned into percent of the exam's maximum so exams with different maximums can be compared.
     */
    public static function classExamStats(int $classId, int $subjectId): array
    {
        $rows = Db::all(
            "SELECT e.id, e.name, e.exam_date, e.max_score, COUNT(g.id) AS graded,
                    AVG(g.score * 100.0 / e.max_score) AS avg_pct,
                    MIN(g.score * 100.0 / e.max_score) AS min_pct,
                    MAX(g.score * 100.0 / e.max_score) AS max_pct
               FROM exams e
               JOIN grades g   ON g.exam_id = e.id
               JOIN students s ON s.user_id = g.student_id
              WHERE s.class_id = ? AND e.subject_id = ?
              GROUP BY e.id, e.name, e.exam_date, e.max_score
              ORDER BY e.exam_date, e.id",
            [$classId, $subjectId]
        );
        foreach ($rows as &$r) {
            foreach (['avg_pct', 'min_pct', 'max_pct'] as $k) {
                $r[$k] = round((float)$r[$k], 1);
            }
        }
        return $rows;
    }

    public static function student(int $id): ?array
    {
        return Db::one(
            "SELECT u.id, u.full_name, u.iin, c.name AS class_name FROM users u
               LEFT JOIN students s ON s.user_id = u.id LEFT JOIN classes c ON c.id = s.class_id
              WHERE u.id = ? AND u.role = 'student'",
            [$id]
        );
    }

    /** Active students. Pass the viewer so a scoped curator only gets the students of their own classes. */
    public static function students(?array $viewer = null): array
    {
        $ids = $viewer ? Scope::classIds($viewer) : null;
        return Db::all(
            "SELECT u.id, u.full_name, u.iin, c.name AS class_name FROM users u
               LEFT JOIN students s ON s.user_id = u.id LEFT JOIN classes c ON c.id = s.class_id
              WHERE u.role = 'student' AND u.is_active = 1" . ($ids ? ' AND s.class_id IN (' . Scope::marks($ids) . ')' : '') . "
              ORDER BY c.name, u.full_name",
            $ids ?? []
        );
    }

    /** Every class with its student count and the curators assigned to it. */
    public static function classes(): array
    {
        $rows = Db::all(
            'SELECT c.id, c.name, (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id) AS students FROM classes c ORDER BY c.name'
        );
        foreach ($rows as &$r) {
            $r['curators'] = array_column(Db::all(
                'SELECT u.full_name FROM curator_classes cc JOIN users u ON u.id = cc.curator_id WHERE cc.class_id = ? ORDER BY u.full_name',
                [$r['id']]
            ), 'full_name');
        }
        return $rows;
    }

    public static function teacherSubjects(int $teacherId): array
    {
        return Db::all(
            'SELECT sj.id, sj.slug, sj.name FROM teacher_subjects ts JOIN subjects sj ON sj.id = ts.subject_id
              WHERE ts.teacher_id = ? ORDER BY sj.id',
            [$teacherId]
        );
    }
}
