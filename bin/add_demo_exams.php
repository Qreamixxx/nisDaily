<?php
declare(strict_types=1);

// Adds DEMO exams and grades to an existing database so the progress graphs have something to show.
// Safe to run again: exams that already exist are skipped, and grades are only added where missing.
//   php bin/add_demo_exams.php
require dirname(__DIR__) . '/bootstrap.php';

use App\Db;

$subj = array_column(App\Repo::subjects(), 'id', 'slug');
$students = Db::all("SELECT u.id FROM users u JOIN students s ON s.user_id = u.id WHERE u.role = 'student' AND u.is_active = 1 ORDER BY u.id");
if (!$students) {
    fwrite(STDERR, "No students found. Run bin/seed_demo.php first.\n");
    exit(1);
}
$today = App\Clock::today();

// [name, subject slug, days ago, max score]. Four informatics and four math exams, spread over about three months.
$exams = [
    ['SAU 1', 'informatics', 77, 100], ['SAU 2', 'informatics', 56, 100], ['SAU 3', 'informatics', 30, 100], ['SAU 4', 'informatics', 9, 100],
    ['SAT 1', 'math', 70, 100], ['SAT 2', 'math', 45, 100], ['SAT 3', 'math', 23, 100], ['SAT 4', 'math', 4, 100],
];

$createdExams = $createdGrades = 0;
Db::transaction(function () use ($exams, $subj, $students, $today, &$createdExams, &$createdGrades): void {
    foreach ($exams as $i => [$name, $slug, $daysAgo, $max]) {
        $subjectId = $subj[$slug] ?? null;
        if (!$subjectId) {
            continue;
        }
        $examId = Db::val('SELECT id FROM exams WHERE name = ? AND subject_id = ?', [$name, $subjectId]);
        if (!$examId) {
            $examId = Db::insert(
                'INSERT INTO exams (subject_id, name, exam_date, max_score, created_at) VALUES (?, ?, ?, ?, ?)',
                [$subjectId, $name, add_days($today, -$daysAgo), $max, date('Y-m-d H:i:s')]
            );
            $createdExams++;
        }
        $step = $i % 4;   // 0..3: which exam of its subject this is
        foreach ($students as $s) {
            if (Db::val('SELECT 1 FROM grades WHERE exam_id = ? AND student_id = ?', [$examId, $s['id']])) {
                continue;
            }
            // Each student has their own level and improves a bit exam after exam, with small ups and downs.
            $level = 52 + (($s['id'] * 7) % 28);
            $trend = 4.5 * $step + (($s['id'] % 3) * 1.2 * $step);
            $noise = ((crc32($name . $slug . $s['id']) % 70) - 35) / 10;   // -3.5 .. +3.5
            $score = round(max(5.0, min($max, $level + $trend + $noise)), 1);
            Db::run('INSERT INTO grades (exam_id, student_id, score) VALUES (?, ?, ?)', [$examId, $s['id'], $score]);
            $createdGrades++;
        }
    }
});
echo "Added $createdExams exam(s) and $createdGrades grade(s) for " . count($students) . " students.\n";
