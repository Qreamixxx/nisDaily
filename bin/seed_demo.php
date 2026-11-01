<?php
declare(strict_types=1);

// Fills the database with DEMO people and lessons (dates are relative to today, so the demo always looks alive).
// Run after migrate.php:   php bin/seed_demo.php
// Never run this on a real school's database.
require dirname(__DIR__) . '/bootstrap.php';

use App\Db;
use App\Role;

if (Db::val('SELECT COUNT(*) FROM users') > 0 && !in_array('--force', $argv ?? [], true)) {
    fwrite(STDERR, "The database already has users. Add --force to add demo data anyway.\n");
    exit(1);
}

$password = 'demo12345';
$hash = password_hash($password, PASSWORD_DEFAULT);
$now = date('Y-m-d H:i:s');

function person(string $iin, string $name, string $role, string $hash, string $lang = 'en'): int
{
    return Db::insert(
        'INSERT INTO users (iin, full_name, password_hash, role, lang, is_active, must_change_password, created_at) VALUES (?, ?, ?, ?, ?, 1, 0, ?)',
        [$iin, $name, $hash, $role, $lang, date('Y-m-d H:i:s')]
    );
}
$subj = array_column(App\Repo::subjects(), 'id', 'slug');

Db::transaction(function () use ($hash, $subj, $now): void {
    $c10a = Db::insert('INSERT INTO classes (name) VALUES (?)', ['10A']);
    $c10b = Db::insert('INSERT INTO classes (name) VALUES (?)', ['10B']);

    $curator = person('800202300555', 'Gulnara Curatorova', 'curator', $hash);   // head curator: all classes
    $curator10a = person('800303300888', 'Aibek Classov', 'curator', $hash);      // looks after 10A only
    Db::run('INSERT INTO curator_classes (curator_id, class_id) VALUES (?, ?)', [$curator10a, $c10a]);
    $curator10b = person('800404300999', 'Dana Classova', 'curator', $hash);      // looks after 10B only
    Db::run('INSERT INTO curator_classes (curator_id, class_id) VALUES (?, ?)', [$curator10b, $c10b]);

    $t1 = person('850101300444', 'Daniyar Informatov', 'teacher', $hash);
    Db::run('INSERT INTO teachers (user_id, room) VALUES (?, ?)', [$t1, '204']);
    Db::run('INSERT INTO teacher_subjects (teacher_id, subject_id) VALUES (?, ?)', [$t1, $subj['informatics']]);

    $t2 = person('860505300666', 'Aigerim Matematikova', 'teacher', $hash, 'ru');
    Db::run('INSERT INTO teachers (user_id, room) VALUES (?, ?)', [$t2, '311']);
    Db::run('INSERT INTO teacher_subjects (teacher_id, subject_id) VALUES (?, ?)', [$t2, $subj['math']]);
    Db::run('INSERT INTO teacher_subjects (teacher_id, subject_id) VALUES (?, ?)', [$t2, $subj['physics']]);

    $t3 = person('870707300777', 'Bauyrzhan Qazaqov', 'teacher', $hash, 'kk');
    Db::run('INSERT INTO teachers (user_id, room) VALUES (?, ?)', [$t3, '105']);
    Db::run('INSERT INTO teacher_subjects (teacher_id, subject_id) VALUES (?, ?)', [$t3, $subj['kazakh']]);

    $students = [];
    foreach ([
        ['030512500111', 'Alizhan Yertargynov', $c10a], ['030817600222', 'Aruzhan Bekova', $c10a],
        ['030301500333', 'Nurlan Sadykov', $c10a], ['030914600444', 'Madina Omarova', $c10b],
        ['031122500555', 'Timur Akhmetov', $c10b], ['030630600666', 'Zarina Kenzhebayeva', $c10b],
    ] as [$iin, $name, $class]) {
        $id = person($iin, $name, 'student', $hash);
        Db::run('INSERT INTO students (user_id, class_id) VALUES (?, ?)', [$id, $class]);
        $students[$iin] = $id;
    }

    $p1 = person('780301400333', 'Parent of Alizhan', 'parent', $hash);
    Db::run('INSERT INTO parent_student (parent_id, student_id) VALUES (?, ?)', [$p1, $students['030512500111']]);
    $p2 = person('790909400888', 'Parent of Aruzhan and Nurlan', 'parent', $hash);
    Db::run('INSERT INTO parent_student (parent_id, student_id) VALUES (?, ?)', [$p2, $students['030817600222']]);
    Db::run('INSERT INTO parent_student (parent_id, student_id) VALUES (?, ?)', [$p2, $students['030301500333']]);

    // Slots: two past weeks (with attendance) and three upcoming weeks.
    $monday = week_start(date('Y-m-d'));
    $bell = App\Repo::bell();
    $makeSlot = function (int $teacher, int $subject, string $date, int $order, int $cap, string $room) use ($now): int {
        return Db::insert(
            'INSERT INTO slots (teacher_id, subject_id, lesson_date, lesson_order, capacity, room, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$teacher, $subject, $date, $order, $cap, $room, $now]
        );
    };
    $book = function (int $slot, int $student, string $status) use ($now): void {
        $s = Db::one('SELECT lesson_date, lesson_order FROM slots WHERE id = ?', [$slot]);
        Db::run(
            'INSERT INTO appointments (slot_id, student_id, lesson_date, lesson_order, status, is_active, created_at) VALUES (?, ?, ?, ?, ?, 1, ?)',
            [$slot, $student, $s['lesson_date'], $s['lesson_order'], $status, $now]
        );
    };
    $ids = array_values($students);
    for ($w = -2; $w <= 2; $w++) {
        $m = add_days($monday, 7 * $w);
        $past = $w < 0;
        $a = $makeSlot($t1, $subj['informatics'], add_days($m, 0), 8, 3, '204');
        $b = $makeSlot($t1, $subj['informatics'], add_days($m, 2), 8, 3, '204');
        $c = $makeSlot($t2, $subj['math'], add_days($m, 1), 7, 2, '311');
        $d = $makeSlot($t2, $subj['physics'], add_days($m, 3), 7, 1, '311');
        $e = $makeSlot($t3, $subj['kazakh'], add_days($m, 4), 6, 4, '105');
        if ($past) {
            $book($a, $ids[0], $w === -2 ? 'attended' : 'no_show');
            $book($a, $ids[1], 'attended');
            $book($b, $ids[0], 'attended');
            $book($c, $ids[2], 'attended');
            $book($e, $ids[3], 'attended');
        } elseif ($w === 0) {
            $book($b, $ids[0], 'booked');
            $book($c, $ids[1], 'booked');
        } elseif ($w === 1) {
            $book($a, $ids[0], 'booked');
            $book($a, $ids[2], 'booked');
            $book($d, $ids[1], 'booked');
        }
    }

    // Exams and grades.
    // Four informatics and three math exams, so the class progress graphs have something to show.
    $exams = [
        ['SAU 1', 'informatics', add_days($monday, -70), 100, [64.7, 82.0, 71.5, 90.0, 55.0, 77.0]],
        ['SAU 2', 'informatics', add_days($monday, -49), 100, [70.2, 79.5, 74.0, 91.5, 58.0, 74.0]],
        ['SAU 3', 'informatics', add_days($monday, -28), 100, [78.6, 75.5, 80.0, 93.5, 61.0, 70.0]],
        ['SAU 4', 'informatics', add_days($monday, -10), 100, [84.0, 80.5, 83.0, 94.0, 67.5, 76.0]],
        ['SAT 1', 'math', add_days($monday, -63), 100, [81.0, 88.0, 60.0, 92.0, 68.0, 80.0]],
        ['SAT 2', 'math', add_days($monday, -35), 100, [86.5, 85.0, 63.5, 94.0, 70.0, 82.0]],
        ['SAT 3', 'math', add_days($monday, -14), 100, [91.2, 88.0, 67.0, 95.0, 72.5, 84.0]],
        ['Quiz: Kazakh grammar', 'kazakh', add_days($monday, -7), 20, [16, 18, 12, 19, 14, 15]],
    ];
    foreach ($exams as [$name, $slug, $date, $max, $scores]) {
        $eid = Db::insert('INSERT INTO exams (subject_id, name, exam_date, max_score, created_at) VALUES (?, ?, ?, ?, ?)', [$subj[$slug], $name, $date, $max, $now]);
        foreach ($ids as $i => $sid) {
            Db::run('INSERT INTO grades (exam_id, student_id, score) VALUES (?, ?, ?)', [$eid, $sid, $scores[$i]]);
        }
    }

    App\Notify::to($ids[0], 'n.welcome', []);
    App\Notify::to($t1, 'n.welcome', []);
});

echo "Demo data created. Every demo account uses the password: $password\n";
echo "  curator  800202300555 (head: all classes)   800303300888 (class 10A only)   800404300999 (class 10B only)\n  teacher  850101300444 (Informatics)   860505300666 (Math, Physics)   870707300777 (Kazakh)\n";
echo "  student  030512500111 (Alizhan)   030817600222 (Aruzhan)   030301500333 ...\n";
echo "  parent   780301400333 (one child)   790909400888 (two children)\n";
