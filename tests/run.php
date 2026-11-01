<?php
declare(strict_types=1);

// Dependency-free test runner.   Run:   php tests/run.php
// Uses an in-memory SQLite database built from the real migrations, so it never touches your MySQL data.
putenv('DB_DSN=sqlite::memory:');
require dirname(__DIR__) . '/bootstrap.php';

use App\{SchoolClasses, Scope, Auth, Booking, Chart, Clock, Db, Exams, I18n, Migrator, Policy, Registration, Repo, Role, RuleException, Slots, Users};

I18n::force('en');
$pass = $fail = 0;
function check(bool $ok, string $label): void
{
    global $pass, $fail;
    if ($ok) { $pass++; } else { $fail++; echo "  FAIL: $label\n"; }
}
/** Assert that $fn throws a RuleException with this translation key. */
function rule(callable $fn, string $key, string $label): void
{
    try { $fn(); check(false, "$label (expected $key, nothing thrown)"); }
    catch (RuleException $e) { check($e->key === $key, "$label (expected $key, got {$e->key})"); }
}
function section(string $name): void { echo "• $name\n"; }

// ---------------------------------------------------------------- set-up
$pdo = Db::connect('sqlite::memory:');
Db::setPdo($pdo);
Migrator::run($pdo, dirname(__DIR__) . '/migrations');
Clock::fix(new DateTimeImmutable('2026-11-02 09:00:00'));   // a Monday, 09:00
$MON = '2026-11-02';

function mkuser(string $iin, string $name, string $role, string $pw = 'password1'): int
{
    return Db::insert(
        'INSERT INTO users (iin, full_name, password_hash, role, lang, is_active, must_change_password, created_at) VALUES (?, ?, ?, ?, ?, 1, 0, ?)',
        [$iin, $name, password_hash($pw, PASSWORD_BCRYPT, ['cost' => 4]), $role, 'en', '2026-01-01 00:00:00']
    );
}
function load(int $id): array { Auth::forget(); $_SESSION['uid'] = $id; return Auth::user(); }

$subj = array_column(Repo::subjects(), 'id', 'slug');
$cur = mkuser('800000000001', 'Curator', 'curator');
$t1 = mkuser('800000000002', 'Teacher One', 'teacher');
$t2 = mkuser('800000000003', 'Teacher Two', 'teacher');
Db::run('INSERT INTO teachers (user_id, room) VALUES (?, ?)', [$t1, '204']);
Db::run('INSERT INTO teachers (user_id, room) VALUES (?, ?)', [$t2, '311']);
Db::run('INSERT INTO teacher_subjects (teacher_id, subject_id) VALUES (?, ?)', [$t1, $subj['informatics']]);
Db::run('INSERT INTO teacher_subjects (teacher_id, subject_id) VALUES (?, ?)', [$t2, $subj['math']]);
$s1 = mkuser('030000000001', 'Student One', 'student');
$s2 = mkuser('030000000002', 'Student Two', 'student');
$s3 = mkuser('030000000003', 'Student Three', 'student');
foreach ([$s1, $s2, $s3] as $s) { Db::run('INSERT INTO students (user_id, class_id) VALUES (?, NULL)', [$s]); }
$par = mkuser('700000000001', 'Parent One', 'parent');
Db::run('INSERT INTO parent_student (parent_id, student_id) VALUES (?, ?)', [$par, $s1]);

$mk = fn(int $t, int $sub, string $date, int $order, int $cap = 1) => Db::insert(
    'INSERT INTO slots (teacher_id, subject_id, lesson_date, lesson_order, capacity, room, created_at) VALUES (?, ?, ?, ?, ?, NULL, ?)',
    [$t, $sub, $date, $order, $cap, '2026-10-01 00:00:00']
);

// ---------------------------------------------------------------- translations
section('translations');
$en = require dirname(__DIR__) . '/lang/en.php';
foreach (['ru', 'kk'] as $l) {
    $other = require dirname(__DIR__) . "/lang/$l.php";
    check(array_keys($en) === array_keys($other), "$l has exactly the same keys as en (missing: " . implode(',', array_diff(array_keys($en), array_keys($other))) . ' extra: ' . implode(',', array_diff(array_keys($other), array_keys($en))) . ')');
    foreach ($en as $k => $v) {
        preg_match_all('/\{(\w+)\}/', $v, $a); preg_match_all('/\{(\w+)\}/', $other[$k] ?? '', $b);
        sort($a[1]); sort($b[1]);
        if ($a[1] !== $b[1]) { check(false, "$l placeholders differ for $k"); }
    }
}
$missing = [];
$files = array_merge(glob(dirname(__DIR__) . '/*.php'), glob(dirname(__DIR__) . '/src/*.php'), glob(dirname(__DIR__) . '/templates/*.php'));
foreach ($files as $f) {
    preg_match_all("/'((?:a11y|classes|scope|reg|appt|bell|book|cancel|col|dash|err|error|exam|exams|filter|footer|home|import|legend|lesson|lessons|login|logout|mark|n|nav|notif|pager|profile|slot|slots|stats|subj|user|users|week)\.[a-z0-9_.]+)'/", (string)file_get_contents($f), $m);
    foreach ($m[1] as $k) {
        if (!str_ends_with($k, '.') && !str_ends_with($k, '.php') && !isset($en[$k])) { $missing[$k] = basename($f); }
    }
}
check(!$missing, 'every translation key used in code exists: ' . json_encode($missing));
foreach (['error.400', 'error.403', 'error.404', 'error.500', 'status.booked', 'status.attended', 'status.no_show', 'status.cancelled', 'appt.tab.upcoming', 'appt.tab.past', 'appt.tab.cancelled', 'appt.empty.upcoming', 'appt.empty.past', 'appt.empty.cancelled'] as $k) { check(isset($en[$k]), "dynamic key $k"); }
foreach (Repo::subjects() as $s) { check(isset($en['subject.' . $s['slug']]), 'subject label for ' . $s['slug']); }
check(t('book.confirm', ['subject' => 'X', 'when' => 'Y', 'teacher' => 'Z']) === 'Book X on Y with Z?', 'placeholders are filled in');
I18n::force('ru'); check(t('nav.home') === 'Главная', 'Russian works'); I18n::force('kk'); check(t('nav.home') === 'Басты бет', 'Kazakh works'); I18n::force('en');

// ---------------------------------------------------------------- helpers
section('helpers');
check(safe_local_path('book.php?subject=1') === 'book.php?subject=1', 'relative path allowed');
foreach (['//evil.com', 'https://evil.com', '/etc/passwd', 'javascript:alert(1)', "a\r\nSet-Cookie: x", '\\\\evil', ''] as $bad) { check(safe_local_path($bad) === 'dashboard.php', 'blocked redirect target: ' . json_encode($bad)); }
check(week_start('2026-11-04') === '2026-11-02' && week_start('2026-11-08') === '2026-11-02' && week_start('2026-11-02') === '2026-11-02', 'week_start gives Monday');
check(valid_date('2026-02-30') === false && valid_date('2026-11-02') === true && valid_date('nope') === false, 'valid_date');
check(num('100') === '100' && num('100.0') === '100' && num(64.7) === '64.7' && num('80.00') === '80' && num(0) === '0' && num(null) === '', 'num() keeps zeros that matter');
check(e('<script>"x"&') === '&lt;script&gt;&quot;x&quot;&amp;', 'e() escapes HTML');

// ---------------------------------------------------------------- authentication
section('authentication');
$tryLogin = fn(string $iin, string $pw, string $ip = '1.1.1.1') => Auth::attempt($iin, $pw, $ip);
rule(fn() => $tryLogin('030000000001', 'wrong'), 'login.wrong', 'wrong password');
rule(fn() => $tryLogin("030000000001' OR '1'='1", 'x'), 'login.wrong', 'SQL injection in IIN is just a wrong IIN');
rule(fn() => $tryLogin('999999999999', 'x'), 'login.wrong', 'unknown IIN gives the same message');
$u = $tryLogin('030000000001', 'password1');
check($u['id'] === $s1 && $_SESSION['uid'] === $s1 && $u['role'] === Role::Student, 'correct login');
check(Db::val('SELECT COUNT(*) FROM login_attempts WHERE iin = ?', ['030000000001']) == 0, 'successful login clears failed attempts');
for ($i = 0; $i < 5; $i++) { try { $tryLogin('030000000002', 'bad', '2.2.2.2'); } catch (RuleException) {} }
rule(fn() => $tryLogin('030000000002', 'password1', '2.2.2.2'), 'login.locked', 'locked after 5 failures, even with the right password');
Clock::fix(new DateTimeImmutable('2026-11-02 09:20:00'));
check($tryLogin('030000000002', 'password1', '2.2.2.2')['id'] === $s2, 'lock expires after 15 minutes');
Clock::fix(new DateTimeImmutable('2026-11-02 09:00:00'));
Db::run('UPDATE users SET is_active = 0 WHERE id = ?', [$s3]);
rule(fn() => $tryLogin('030000000003', 'password1'), 'login.wrong', 'deactivated user cannot log in');
$me = load($s1); Db::run('UPDATE users SET is_active = 0 WHERE id = ?', [$s1]); Auth::forget();
check(Auth::user() === null, 'deactivating a user ends their session immediately');
Db::run('UPDATE users SET is_active = 1 WHERE id = ?', [$s1]); Db::run('UPDATE users SET is_active = 1 WHERE id = ?', [$s3]);
check(strlen(Auth::tempPassword()) === 10 && Auth::tempPassword() !== Auth::tempPassword(), 'temp passwords are random');

// ---------------------------------------------------------------- policies
section('policies');
$S1 = load($s1); $S2 = load($s2); $P = load($par); $T1 = load($t1); $T2 = load($t2); $C = load($cur);
check(Policy::canViewStudent($S1, $s1) && !Policy::canViewStudent($S1, $s2), 'student sees only self');
check(Policy::canViewStudent($P, $s1) && !Policy::canViewStudent($P, $s2), 'parent sees only own child');
check(Policy::canViewStudent($T1, $s2) && Policy::canViewStudent($C, $s2), 'staff see any student');
check(Policy::canBook($S1) && !Policy::canBook($P) && !Policy::canBook($T1) && !Policy::canBook($C), 'only students book');
check(Policy::canManageExam($T1, $subj['informatics']) && !Policy::canManageExam($T1, $subj['math']) && Policy::canManageExam($C, $subj['math']), 'exam rights follow subject');
check(Policy::canManageUsers($C) && !Policy::canManageUsers($T1), 'only curators manage users');

// ---------------------------------------------------------------- booking rules
section('booking');
$a = $mk($t1, $subj['informatics'], '2026-11-03', 8, 1);    // Tue lesson 8, capacity 1
$b = $mk($t2, $subj['math'], '2026-11-03', 8, 2);           // same time, other teacher
$id = Booking::book($S1, $a);
check($id > 0 && Db::val('SELECT is_active FROM appointments WHERE id = ?', [$id]) == 1, 'student books a free slot');
check(Notify_count($t1) === 1, 'teacher is notified');
function Notify_count(int $uid): int { return (int)Db::val('SELECT COUNT(*) FROM notifications WHERE user_id = ?', [$uid]); }
rule(fn() => Booking::book($S1, $a), 'book.err.already', 'same slot twice');
rule(fn() => Booking::book($S2, $a), 'book.err.full', 'slot full');
rule(fn() => Booking::book($S1, $b), 'book.err.clash', 'two lessons at the same time');
check(Booking::book($S2, $b) > 0, 'other student can take the other slot');
rule(fn() => Booking::book($P, $b), 'err.forbidden', 'parent cannot book');
rule(fn() => Booking::book($T1, $b), 'err.forbidden', 'teacher cannot book');
$past = $mk($t1, $subj['informatics'], $MON, 1);            // 08:00 today, already started at 09:00
rule(fn() => Booking::book($S2, $past), 'book.err.past', 'lesson already started');
$soon = $mk($t1, $subj['informatics'], $MON, 3);            // 09:40 today, less than 2 hours away
rule(fn() => Booking::book($S2, $soon), 'book.err.cutoff', 'inside the 2-hour cut-off');
$later = $mk($t1, $subj['informatics'], $MON, 6);           // 12:25 today, more than 2 hours away
check(Booking::book($S2, $later) > 0, 'just outside the cut-off is fine');
// weekly limit: max 3 per subject per week (Alice already has Tue L8 Informatics; add Wed 1, Wed 2 -> 3, Wed 3 -> 4th)
$w = [$mk($t1, $subj['informatics'], '2026-11-04', 1), $mk($t1, $subj['informatics'], '2026-11-04', 2), $mk($t1, $subj['informatics'], '2026-11-04', 3)];
Booking::book($S1, $w[0]); Booking::book($S1, $w[1]);
rule(fn() => Booking::book($S1, $w[2]), 'book.err.limit', 'fourth booking in a week for one subject');
$nextWeek = $mk($t1, $subj['informatics'], '2026-11-10', 1);
check(Booking::book($S1, $nextWeek) > 0, 'limit is per week');
Db::run('UPDATE slots SET closed_at = ? WHERE id = ?', ['2026-11-01 00:00:00', $w[2]]);
rule(fn() => Booking::book($S2, $w[2]), 'book.err.closed', 'closed slot');
rule(fn() => Booking::book($S2, 99999), 'book.err.closed', 'unknown slot');
Db::run('UPDATE users SET is_active = 0 WHERE id = ?', [$t2]);
$t2slot = $mk($t2, $subj['math'], '2026-11-05', 1);
rule(fn() => Booking::book($S2, $t2slot), 'book.err.closed', 'inactive teacher');
Db::run('UPDATE users SET is_active = 1 WHERE id = ?', [$t2]);

section('database safety net (the UNIQUE keys)');
$threw = false;
try { Db::run("INSERT INTO appointments (slot_id, student_id, lesson_date, lesson_order, status, is_active) VALUES (?, ?, '2026-11-03', 8, 'booked', 1)", [$a, $s2]); } catch (PDOException) { $threw = true; }
check($threw, 'database refuses a second live booking at the same time for one student');
$threw = false;
try { Db::run("INSERT INTO appointments (slot_id, student_id, lesson_date, lesson_order, status, is_active) VALUES (?, ?, '2026-11-10', 1, 'booked', 1)", [$nextWeek, $s1]); } catch (PDOException) { $threw = true; }
check($threw, 'database refuses the same student twice in one slot');
$threw = false;
try { Db::run("INSERT INTO slots (teacher_id, subject_id, lesson_date, lesson_order) VALUES (?, ?, '2026-11-03', 8)", [$t1, $subj['informatics']]); } catch (PDOException) { $threw = true; }
check($threw, 'database refuses a teacher with two slots at one time');

section('cancelling');
$S3 = load($s3);
rule(fn() => Booking::cancel($S2, $id), 'err.forbidden', "student cannot cancel someone else's booking");
rule(fn() => Booking::cancel($P, $id), 'err.forbidden', 'parent cannot cancel');
rule(fn() => Booking::cancel($T2, $id), 'err.forbidden', "another subject's teacher cannot cancel");
Booking::cancel($S1, $id);
$row = Db::one('SELECT status, is_active, cancelled_by FROM appointments WHERE id = ?', [$id]);
check($row['status'] === 'cancelled' && $row['is_active'] === null && (int)$row['cancelled_by'] === $s1, 'cancel keeps the row (history) and records who');
check(Notify_count($t1) >= 2, 'teacher notified of the cancellation');
check(Booking::book($S3, $a) > 0, 'a cancelled seat can be booked by someone else');
rule(fn() => Booking::cancel($S1, $id), 'err.notfound', 'cancelling twice');
$aid = (int)Db::val('SELECT id FROM appointments WHERE slot_id = ? AND is_active = 1', [$a]);
Booking::cancel($T1, $aid);
check(Notify_count($s3) === 1, 'teacher cancelling notifies the student');
$later_appt = (int)Db::val('SELECT id FROM appointments WHERE slot_id = ? AND is_active = 1', [$later]);
Clock::fix(new DateTimeImmutable('2026-11-02 12:30:00'));
rule(fn() => Booking::cancel($S2, $later_appt), 'cancel.err.started', 'cannot cancel a lesson that has started');
Clock::fix(new DateTimeImmutable('2026-11-02 09:00:00'));
check(Booking::book($S1, $a) > 0, 'student can re-book after cancelling');

section('attendance');
rule(fn() => Booking::mark($T1, $later_appt, 'attended'), 'mark.err.notstarted', 'cannot mark before the lesson starts');
Clock::fix(new DateTimeImmutable('2026-11-02 12:30:00'));
rule(fn() => Booking::mark($T2, $later_appt, 'attended'), 'err.forbidden', 'other teacher cannot mark');
rule(fn() => Booking::mark($S2, $later_appt, 'attended'), 'err.forbidden', 'student cannot mark');
rule(fn() => Booking::mark($T1, $later_appt, 'banana'), 'err.forbidden', 'unknown status refused');
Booking::mark($T1, $later_appt, 'attended');
check(Db::val('SELECT status FROM appointments WHERE id = ?', [$later_appt]) === 'attended', 'teacher marks attended');
Booking::mark($T1, $later_appt, 'no_show');
check(Db::val('SELECT status FROM appointments WHERE id = ?', [$later_appt]) === 'no_show', 'teacher can change their mind');
check(count(Repo::attendanceRows($s2)) === 1, 'attendance rows feed the statistics');
Clock::fix(new DateTimeImmutable('2026-11-02 09:00:00'));

section('slots');
$r = Slots::open($T1, '2026-11-09', 4, $subj['informatics'], 2, '', 3);
check($r === ['created' => 3, 'skipped' => 0], 'open a slot repeating for 3 weeks');
check(Db::val("SELECT COUNT(*) FROM slots WHERE teacher_id = ? AND lesson_order = 4 AND lesson_date IN ('2026-11-09','2026-11-16','2026-11-23')", [$t1]) == 3, 'three dated slots exist');
check(Slots::open($T1, '2026-11-09', 4, $subj['informatics'], 2, '', 1) === ['created' => 0, 'skipped' => 1], 'opening an open slot again is skipped');
rule(fn() => Slots::open($T1, '2026-11-14', 4, $subj['informatics'], 1, null), 'slot.err.weekend', 'weekend refused');
rule(fn() => Slots::open($T1, $MON, 1, $subj['informatics'], 1, null), 'slot.err.past', 'past time refused');
rule(fn() => Slots::open($T1, '2026-11-12', 4, $subj['math'], 1, null), 'slot.err.subject', "other teacher's subject refused");
rule(fn() => Slots::open($T1, '2026-11-12', 4, $subj['informatics'], 99, null), 'slot.err.capacity', 'capacity bounds');
rule(fn() => Slots::open($T1, '2026-11-12', 77, $subj['informatics'], 1, null), 'slot.err.lesson', 'unknown lesson number');
rule(fn() => Slots::open($T1, 'garbage', 4, $subj['informatics'], 1, null), 'slot.err.date', 'bad date');
rule(fn() => Slots::open($S1, '2026-11-12', 4, $subj['informatics'], 1, null), 'err.forbidden', 'student cannot open slots');
$open = (int)Db::val("SELECT id FROM slots WHERE teacher_id = ? AND lesson_date = '2026-11-16' AND lesson_order = 4", [$t1]);
rule(fn() => Slots::close($T2, $open), 'err.forbidden', "cannot close another teacher's slot");
Slots::close($T1, $open);
check(Db::val('SELECT closed_at FROM slots WHERE id = ?', [$open]) !== null, 'teacher closes an empty slot');
check(Slots::open($T1, '2026-11-16', 4, $subj['informatics'], 2, null)['created'] === 1, 'a closed slot can be re-opened');
rule(fn() => Slots::close($T1, $a), 'slot.err.has_bookings', 'cannot close a booked slot');

section('exams and grades');
$ex = Exams::create($T1, $subj['informatics'], 'SAU 1', '2026-10-20', '100');
rule(fn() => Exams::create($T1, $subj['math'], 'X', '2026-10-20', '100'), 'err.forbidden', "teacher cannot create exams for other subjects");
rule(fn() => Exams::create($T1, $subj['informatics'], '', '2026-10-20', '100'), 'exam.err.name', 'exam needs a name');
rule(fn() => Exams::create($T1, $subj['informatics'], 'X', '2026-10-20', '0'), 'exam.err.max', 'max score above zero');
check(Exams::saveScores($T1, $ex, [$s1 => '64,7', $s2 => '90', $s3 => '']) === 2, 'saves scores (comma decimals ok, blank skipped)');
check((float)Db::val('SELECT score FROM grades WHERE exam_id = ? AND student_id = ?', [$ex, $s1]) === 64.7, 'decimal stored');
rule(fn() => Exams::saveScores($T1, $ex, [$s1 => '101']), 'exam.err.score', 'score above max refused');
rule(fn() => Exams::saveScores($T1, $ex, [$s1 => 'abc']), 'exam.err.score', 'non-numeric refused');
rule(fn() => Exams::saveScores($T2, $ex, [$s1 => '50']), 'err.forbidden', "other subject's teacher cannot enter scores");
rule(fn() => Exams::saveScores($S1, $ex, [$s1 => '100']), 'err.forbidden', 'student cannot grade themselves');
Exams::saveScores($C, $ex, [$s1 => '']);
check(Db::val('SELECT COUNT(*) FROM grades WHERE exam_id = ? AND student_id = ?', [$ex, $s1]) == 0, 'blank score clears the grade');
Db::run('INSERT INTO classes (name) VALUES (?)', ['9Z']);
$z = (int)Db::val('SELECT id FROM classes WHERE name = ?', ['9Z']);
Db::run('UPDATE students SET class_id = ? WHERE user_id IN (?, ?)', [$z, $s1, $s2]);
$g = Repo::grades($s2);
check(count($g) === 1 && (float)$g[0]['class_avg'] === 90.0, 'grades include class average');

section('class progress graphs');
$ex2 = Exams::create($T1, $subj['informatics'], 'SAU 2', '2026-11-20', '50');
Exams::saveScores($T1, $ex2, [$s1 => '25', $s2 => '45']);
Exams::saveScores($T1, $ex, [$s1 => '40', $s2 => '90']);
$st = Repo::classExamStats($z, $subj['informatics']);
check(count($st) === 2 && $st[0]['name'] === 'SAU 1' && $st[1]['name'] === 'SAU 2', 'one row per exam, oldest first');
check($st[0]['avg_pct'] === 65.0 && $st[0]['min_pct'] === 40.0 && $st[0]['max_pct'] === 90.0 && $st[0]['graded'] == 2, 'class average / min / max for exam 1');
check($st[1]['avg_pct'] === 70.0, 'scores are percent of each exam\'s own maximum (25/50 and 45/50 -> 70%)');
check(Repo::classExamStats($z, $subj['math']) === [], 'other subjects are not mixed in');
check(Repo::classExamStats(999999, $subj['informatics']) === [], 'class with no grades has no rows');
check(count(Repo::grades($s2, $subj['informatics'])) === 2 && Repo::grades($s2, $subj['math']) === [], 'student grades can be filtered by subject');
check((float)Repo::grades($s2)[0]['class_avg'] === 65.0, 'class average is the student\'s own class only');
$svg2 = Chart::lines(['A', 'B', 'C'], [['class' => 'chart-line', 'name' => 'S', 'values' => [10.0, 50.0, 90.0]], ['class' => 'chart-ref', 'name' => 'C', 'values' => [20.0, null, 70.0]]], 100.0, 'x', '%');
check(substr_count($svg2, '<polyline') === 2 && substr_count($svg2, '<circle') === 5, 'two lines; a missing point is skipped');
check(Chart::lines([], [], 100.0, 'x') === '', 'no exams, no chart');
$zoom = Chart::lines(['A', 'B'], [['class' => 'chart-line', 'name' => 'S', 'values' => [60.0, 80.0]]], 100.0, 'x', '%', 50.0);
check(str_contains($zoom, '>50<') && str_contains($zoom, '>75<') && str_contains($zoom, '>100<'), 'zoomed axis starts at the given minimum');

section('a teacher sees only their own subjects and lessons');
check(Repo::grades($s2, 0, [$subj['math']]) === [], 'math teacher sees no informatics grades of the student');
check(count(Repo::grades($s2, 0, [$subj['informatics']])) === 2 && count(Repo::grades($s2, 0, [$subj['informatics'], $subj['math']])) === 2, 'informatics teacher sees informatics grades');
check(Repo::grades($s2, 0, []) === [], 'teacher with no subjects sees nothing');
check(Repo::grades($s2, $subj['informatics'], [$subj['math']]) === [], 'a subject filter cannot widen a teacher\'s access');
check(count(Repo::grades($s2)) === 2, 'no restriction for other roles');
check(count(Repo::attendanceRows($s2, $t1)) === 1 && Repo::attendanceRows($s2, $t2) === [], 'attendance only counts the viewing teacher\'s own lessons');

section('user management');
rule(fn() => Users::create($T1, ['iin' => '123456789012', 'full_name' => 'X', 'role' => 'student']), 'err.forbidden', 'only curators create users');
rule(fn() => Users::create($C, ['iin' => '123', 'full_name' => 'Name', 'role' => 'student']), 'user.err.iin', 'IIN must be 12 digits');
rule(fn() => Users::create($C, ['iin' => '123456789012', 'full_name' => 'Name', 'role' => 'wizard']), 'user.err.role', 'unknown role');
rule(fn() => Users::create($C, ['iin' => '030000000001', 'full_name' => 'Dup', 'role' => 'student']), 'user.err.exists', 'duplicate IIN');
$new = Users::create($C, ['iin' => '040000000001', 'full_name' => 'New Kid', 'role' => 'student', 'class' => '9A']);
check(Db::val('SELECT must_change_password FROM users WHERE id = ?', [$new['id']]) == 1, 'new accounts must change their password');
check(Auth::attempt('040000000001', $new['password'], '9.9.9.9')['id'] === $new['id'], 'one-time password works');
check(Db::val('SELECT c.name FROM students s JOIN classes c ON c.id = s.class_id WHERE s.user_id = ?', [$new['id']]) === '9A', 'class created and linked');
$pw2 = Users::resetPassword($C, $new['id']);
check(Db::val('SELECT must_change_password FROM users WHERE id = ?', [$new['id']]) == 1 && Auth::attempt('040000000001', $pw2, '9.9.9.9') !== null, 'reset gives a new one-time password');
rule(fn() => Users::setActive($C, $cur, false), 'user.err.self', 'curator cannot deactivate themselves');
$csv = "iin,full_name,role,class,room,subjects,children\n050000000001,Csv Student,student,10A,,,\n050000000002,Csv Teacher,teacher,,12,math|physics,\n050000000003,Csv Parent,parent,,,,050000000001\nbad,Broken,student,,,,\n";
$res = Users::importCsv($C, $csv);
check(count($res['created']) === 3 && count($res['errors']) === 1, 'CSV import creates 3, reports 1 error');
$tid = (int)Db::val('SELECT id FROM users WHERE iin = ?', ['050000000002']);
check(count(Repo::teacherSubjects($tid)) === 2, 'teacher subjects imported');
$pid = (int)Db::val('SELECT id FROM users WHERE iin = ?', ['050000000003']);
check(load($pid)['children'] === [(int)Db::val('SELECT id FROM users WHERE iin = ?', ['050000000001'])], 'parent linked to child by IIN');
rule(fn() => Users::importCsv($C, "name,age\nx,1"), 'import.err.header', 'CSV needs the right header');

section('self-registration');
$form = ['role' => 'student', 'iin' => '060000000001', 'full_name' => 'Reg Student', 'class' => '9B', 'password' => 'longenough1', 'password2' => 'longenough1'];
rule(fn() => Registration::submit(['role' => 'teacher'] + $form, '5.5.5.5'), 'reg.err.role', 'teachers cannot self-register');
rule(fn() => Registration::submit(['role' => 'curator'] + $form, '5.5.5.5'), 'reg.err.role', 'curators cannot self-register');
rule(fn() => Registration::submit(['iin' => '12'] + $form, '5.5.5.5'), 'user.err.iin', 'IIN must be 12 digits');
rule(fn() => Registration::submit(['password' => 'short', 'password2' => 'short'] + $form, '5.5.5.5'), 'reg.err.pw_short', 'short password');
rule(fn() => Registration::submit(['password2' => 'different11'] + $form, '5.5.5.5'), 'reg.err.pw_match', 'passwords must match');
rule(fn() => Registration::submit(['role' => 'parent', 'child_iin' => 'abc'] + $form, '5.5.5.5'), 'reg.err.child', 'parent needs a valid child IIN');
check(Registration::pendingCount() === 0, 'invalid forms store nothing');
Registration::submit($form, '5.5.5.5');
check(Registration::pendingCount() === 1, 'a valid request is stored');
check(Db::val('SELECT COUNT(*) FROM users WHERE iin = ?', ['060000000001']) == 0, 'no account exists before approval');
rule(fn() => Auth::attempt('060000000001', 'longenough1', '5.5.5.5'), 'login.wrong', 'a pending request cannot log in');
check(Registration::matchesPending('060000000001', 'longenough1') && !Registration::matchesPending('060000000001', 'nope'), 'login page can tell "waiting" from "wrong"');
check(Db::val('SELECT password_hash FROM registration_requests WHERE iin = ?', ['060000000001']) !== 'longenough1', 'password is stored hashed');
Registration::submit($form, '5.5.5.5');
Registration::submit(['iin' => '030000000001'] + $form, '5.5.5.5');
check(Registration::pendingCount() === 1, 'duplicate pending / existing IIN: silently ignored (no account enumeration)');
Registration::submit(['iin' => '060000000009', 'website' => 'http://spam'] + $form, '5.5.5.5');
check(Registration::pendingCount() === 1, 'honeypot catches bots');
check(Notify_count($cur) >= 1, 'curators are notified');
rule(fn() => Registration::approve($T1, 1), 'err.forbidden', 'teacher cannot approve');
rule(fn() => Registration::pending($S1), 'err.forbidden', 'student cannot see requests');
$rid = (int)Db::val('SELECT id FROM registration_requests WHERE iin = ?', ['060000000001']);
$newUid = Registration::approve($C, $rid);
check(Registration::pendingCount() === 0 && Db::val('SELECT is_active FROM users WHERE id = ?', [$newUid]) == 1, 'approval creates an active account');
check(Auth::attempt('060000000001', 'longenough1', '6.6.6.6')['id'] === $newUid, 'the chosen password works after approval');
check(Db::val('SELECT c.name FROM students s JOIN classes c ON c.id = s.class_id WHERE s.user_id = ?', [$newUid]) === '9B', 'class saved');
Registration::submit(['role' => 'parent', 'iin' => '070000000001', 'full_name' => 'Reg Parent', 'child_iin' => '060000000001'] + $form, '5.5.5.5');
$pr = (int)Db::val('SELECT id FROM registration_requests WHERE iin = ?', ['070000000001']);
$pUid = Registration::approve($C, $pr);
check(load($pUid)['children'] === [$newUid], 'parent linked to the child by IIN');
Registration::submit(['role' => 'parent', 'iin' => '070000000002', 'full_name' => 'Orphan', 'child_iin' => '069999999999'] + $form, '5.5.5.5');
$or = (int)Db::val('SELECT id FROM registration_requests WHERE iin = ?', ['070000000002']);
rule(fn() => Registration::approve($C, $or), 'reg.err.child_missing', 'cannot approve a parent whose child is not registered');
check(Registration::pendingCount() === 1, 'failed approval keeps the request');
Registration::reject($C, $or);
check(Registration::pendingCount() === 0, 'reject removes the request');
for ($i = 0; $i < 5; $i++) { Registration::submit(['iin' => '08000000000' . $i] + $form, '7.7.7.7'); }
rule(fn() => Registration::submit(['iin' => '080000000009'] + $form, '7.7.7.7'), 'reg.err.rate', 'rate limit per IP');

section('curators and classes');
$c10a = SchoolClasses::add($C, '12A'); $c10b = SchoolClasses::add($C, '12B');
rule(fn() => SchoolClasses::add($C, '12A'), 'classes.err.exists', 'duplicate class');
rule(fn() => SchoolClasses::add($C, '  '), 'classes.err.name', 'blank class name');
rule(fn() => SchoolClasses::add($T1, '11A'), 'err.forbidden', 'only a head curator manages classes');
$sa = Users::create($C, ['iin' => '090000000001', 'full_name' => 'Alpha 12A', 'role' => 'student', 'class' => '12A'])['id'];
$sb = Users::create($C, ['iin' => '090000000002', 'full_name' => 'Beta 12B', 'role' => 'student', 'class' => '12B'])['id'];
$pa = Users::create($C, ['iin' => '091000000001', 'full_name' => 'Parent A', 'role' => 'parent', 'child_ids' => [$sa]])['id'];
$pb = Users::create($C, ['iin' => '091000000002', 'full_name' => 'Parent B', 'role' => 'parent', 'child_ids' => [$sb]])['id'];
$k = Users::create($C, ['iin' => '092000000001', 'full_name' => 'Curator 12A', 'role' => 'curator', 'class_ids' => [$c10a]])['id'];
$K = load($k);
check($K['class_ids'] === [$c10a] && Scope::isScoped($K) && !Scope::isHead($K) && Scope::isHead($C), 'a curator with classes is scoped; one without is the head');
check(Scope::classNames($K) === ['12A'], 'class names for the dashboard');
check(!Policy::canAdminister($K) && Policy::canAdminister($C), 'only the head administers lesson times and classes');
check(Policy::canViewStudent($K, $sa) && !Policy::canViewStudent($K, $sb) && Policy::canViewStudent($C, $sb), 'scoped curator views only own-class students');
check(array_column(Repo::students($K), 'id') === [$sa], 'student lists are limited to the curator\'s classes');
check(count(Repo::students($C)) > 1, 'the head sees everyone');
$found = array_column(Users::search('', '', '', 1, 50, $K)['rows'], 'full_name');
sort($found);
check($found === ['Alpha 12A', 'Parent A'], 'user list: own students and their parents only (got ' . implode(',', $found) . ')');
check(Users::search('', 'teacher', '', 1, 50, $K)['total'] === 0, 'a scoped curator never lists teachers or curators');
rule(fn() => Users::update($K, $sb, ['full_name' => 'Hacked', 'class' => '12B']), 'err.forbidden', 'cannot edit a student of another class');
rule(fn() => Users::update($K, $sa, ['full_name' => 'Alpha', 'class' => '12B']), 'scope.err.create', 'cannot move a student out of own classes');
Users::update($K, $sa, ['full_name' => 'Alpha Renamed', 'class' => '12A']);
check(Db::val('SELECT full_name FROM users WHERE id = ?', [$sa]) === 'Alpha Renamed', 'can edit own student');
rule(fn() => Users::update($K, $t1, ['full_name' => 'x']), 'err.forbidden', 'cannot edit a teacher');
rule(fn() => Users::update($K, $cur, ['full_name' => 'x']), 'err.forbidden', 'cannot edit a curator');
rule(fn() => Users::create($K, ['iin' => '093000000001', 'full_name' => 'Nope', 'role' => 'student', 'class' => '12B']), 'scope.err.create', 'cannot create a student in another class');
rule(fn() => Users::create($K, ['iin' => '093000000002', 'full_name' => 'Nope', 'role' => 'student', 'class' => 'NEW']), 'scope.err.create', 'cannot invent a class');
rule(fn() => Users::create($K, ['iin' => '093000000003', 'full_name' => 'Nope', 'role' => 'teacher']), 'scope.err.create', 'cannot create a teacher');
rule(fn() => Users::create($K, ['iin' => '093000000004', 'full_name' => 'Nope', 'role' => 'curator', 'class_ids' => []]), 'scope.err.create', 'cannot create a head curator');
rule(fn() => Users::create($K, ['iin' => '093000000005', 'full_name' => 'Nope', 'role' => 'parent', 'child_ids' => [$sb]]), 'scope.err.create', "cannot create a parent of another class's student");
check(Users::create($K, ['iin' => '093000000006', 'full_name' => 'Gamma 12A', 'role' => 'student', 'class' => '12A'])['id'] > 0, 'can create a student in own class');
check(Users::create($K, ['iin' => '093000000007', 'full_name' => 'Parent G', 'role' => 'parent', 'child_ids' => [$sa]])['id'] > 0, 'can create a parent of own student');
rule(fn() => Users::resetPassword($K, $sb), 'err.forbidden', 'cannot reset another class\'s password');
rule(fn() => Users::setActive($K, $sb, false), 'err.forbidden', 'cannot deactivate another class\'s student');
Users::setActive($K, $sa, false); Users::setActive($K, $sa, true);
check(Db::val('SELECT is_active FROM users WHERE id = ?', [$sa]) == 1, 'can deactivate and restore own student');
$imp = Users::importCsv($K, "iin,full_name,role,class\n094000000001,Csv A,student,12A\n094000000002,Csv B,student,12B\n094000000003,Csv T,teacher,\n");
check(count($imp['created']) === 1 && count($imp['errors']) === 2, 'CSV import by a scoped curator only creates their own students');

$mkSlot = fn(string $date, int $order) => $mk($t1, $subj['informatics'], $date, $order, 4);
$s_a = $mkSlot('2026-11-17', 2);
$appA = Booking::book(load($sa), $s_a); $appB = Booking::book(load($sb), $s_a);
$K = load($k);
check(Repo::appointments(['class_ids' => [$c10a], 'tab' => 'upcoming'])['total'] === 1, 'appointment list limited to own classes');
rule(fn() => Booking::cancel($K, $appB), 'err.forbidden', 'cannot cancel another class\'s booking');
Booking::cancel($K, $appA);
check(Db::val('SELECT status FROM appointments WHERE id = ?', [$appA]) === 'cancelled', 'can cancel own student\'s booking');
check(Exams::saveScores($K, $ex, [$sa => '50', $sb => '60']) === 1, 'exam scores: only own students are saved');
check(Db::val('SELECT COUNT(*) FROM grades WHERE exam_id = ? AND student_id = ?', [$ex, $sb]) == 0, 'other class\'s grade untouched');
rule(fn() => SchoolClasses::add($K, '11A'), 'err.forbidden', 'scoped curator cannot add classes');
rule(fn() => SchoolClasses::delete($C, $c10a), 'classes.err.not_empty', 'cannot delete a class that has students');

section('registration requests respect classes');
$before = Notify_count($k);
Registration::submit(['role' => 'student', 'iin' => '095000000001', 'full_name' => 'Req B', 'class' => '12B'] + $form, '8.8.8.8');
check(Notify_count($k) === $before, '12A curator is not notified about a 12B request');
Registration::submit(['role' => 'student', 'iin' => '095000000002', 'full_name' => 'Req A', 'class' => '12A'] + $form, '8.8.8.8');
check(Notify_count($k) === $before + 1, '12A curator is notified about a 12A request');
Registration::submit(['role' => 'parent', 'iin' => '095000000003', 'full_name' => 'Req PB', 'child_iin' => '090000000002'] + $form, '8.8.8.8');
Registration::submit(['role' => 'parent', 'iin' => '095000000004', 'full_name' => 'Req PA', 'child_iin' => '090000000001'] + $form, '8.8.8.8');
$names = array_column(Registration::pending($K), 'full_name'); sort($names);
check($names === ['Req A', 'Req PA'], 'scoped curator sees only requests for own classes (got ' . implode(',', $names) . ')');
check(Registration::pendingCount($K) === 2 && Registration::pendingCount($C) === count(Registration::pending($C)) && Registration::pendingCount($C) > 2, 'badge counts match what each curator can see');
$reqB = (int)Db::val('SELECT id FROM registration_requests WHERE iin = ?', ['095000000001']);
rule(fn() => Registration::approve($K, $reqB), 'err.forbidden', 'cannot approve a request for another class');
rule(fn() => Registration::reject($K, $reqB), 'err.forbidden', 'cannot reject a request for another class');
check(Registration::approve($K, (int)Db::val('SELECT id FROM registration_requests WHERE iin = ?', ['095000000002'])) > 0, 'approves a request for own class');
check(Registration::approve($C, $reqB) > 0, 'head curator approves any request');

section('the last head curator');
$h2 = Users::create($C, ['iin' => '096000000001', 'full_name' => 'Second Head', 'role' => 'curator', 'class_ids' => []])['id'];
Users::setActive($C, $h2, false);
rule(fn() => Users::update($C, $cur, ['full_name' => 'Curator', 'class_ids' => [$c10a]]), 'scope.err.last_head', 'cannot give the only head curator classes');
check(Db::val('SELECT COUNT(*) FROM curator_classes WHERE curator_id = ?', [$cur]) == 0, 'the failed change was rolled back');
Users::setActive($C, $h2, true);
Users::update($C, $h2, ['full_name' => 'Second Head', 'class_ids' => [$c10b]]);
check(Scope::isScoped(load($h2)), 'the head can assign classes to another curator');
rule(fn() => Users::setActive($K, $cur, false), 'err.forbidden', 'a scoped curator cannot deactivate the head');

section('lists, charts, calendar data');
$up = Repo::appointments(['student_ids' => [$s1], 'tab' => 'upcoming']);
check($up['total'] >= 3 && count($up['rows']) === $up['total'], 'upcoming list');
$cancelled = Repo::appointments(['student_ids' => [$s1], 'tab' => 'cancelled']);
check($cancelled['total'] === 1, 'cancelled list keeps history');
check(Repo::appointments(['student_ids' => []])['total'] === 0, 'no children = empty list');
check(Repo::appointments(['tab' => 'upcoming', 'q' => 'Student%One'])['total'] === 0, 'search treats % literally');
check(Repo::appointments(['tab' => 'upcoming', 'q' => 'Student One'])['total'] >= 1, 'search by name');
$svg = Chart::bars(['A<b>', 'B'], [50.0, 80.5], 100.0, 'Test chart', [60.0, null], '%');
check(str_starts_with($svg, '<svg') && str_contains($svg, '&lt;b&gt;') && !str_contains($svg, '<b>'), 'chart is SVG and escapes labels');
check(Chart::line(['a'], [3.0], 5.0, 'x') !== '' && Chart::bars([], [], 100.0, 'x') === '', 'line chart / empty chart');
$n = Db::val('SELECT COUNT(*) FROM users');
Db::run('INSERT INTO notifications (user_id, message_key, params, link, created_at) VALUES (?, ?, ?, NULL, ?)', [$s1, 'n.booked', json_encode(['student' => 'Z', 'subject_slug' => 'math', 'subject' => 'Math', 'date' => '2026-11-03', 'time' => '13:55']), '2026-11-02 09:00:00']);
I18n::force('ru');
$notes = App\Notify::latest($s1, 5);
check(str_contains($notes[0]['text'], 'Математика') && str_contains($notes[0]['text'], 'Вт'), 'notifications render in the reader\'s language');
I18n::force('en');
check(App\Notify::unread($s1) >= 1, 'unread count');

echo "\n" . ($fail === 0 ? "ALL $pass CHECKS PASSED\n" : "$fail FAILED, $pass passed\n");
exit($fail === 0 ? 0 : 1);
