<?php
require __DIR__ . '/bootstrap.php';

use App\Chart;
use App\Policy;
use App\Repo;
use App\Role;

$user = require_login();

$staff = in_array($user['role'], [Role::Teacher, Role::Curator], true);
$view = $staff && ($_GET['view'] ?? '') !== 'student' ? 'classes' : 'student';

// Subject filter. Staff look at one subject at a time (mixing subjects says nothing about progress);
// students and parents may also see "all subjects".
$subjectOptions = match ($user['role']) {
    Role::Teacher => Repo::teacherSubjects($user['id']),
    default       => Repo::subjects(),
};
$subjectId = (int)($_GET['subject'] ?? 0);
$validSubjects = array_map('intval', array_column($subjectOptions, 'id'));
if ($view === 'classes') {
    if (!in_array($subjectId, $validSubjects, true)) {
        $subjectId = $validSubjects[0] ?? 0;
    }
} elseif ($user['role'] === Role::Teacher) {
    // A teacher sees a student's results only in their own subjects (not other teachers' subjects).
    if (!in_array($subjectId, $validSubjects, true)) {
        $subjectId = count($validSubjects) === 1 ? $validSubjects[0] : 0;
    }
} elseif ($subjectId !== 0 && !in_array($subjectId, array_map('intval', array_column(Repo::subjects(), 'id')), true)) {
    $subjectId = 0;
}
$teacherOnly = $user['role'] === Role::Teacher;
$allowAllSubjects = !($teacherOnly && count($validSubjects) <= 1);

// ---- Class progress (teachers and curators): one graph per class, exam after exam
/** Y-axis start for a progress graph: just below the lowest value, in steps of 20, so small changes stay visible. */
$axisMin = function (array $values): float {
    $values = array_filter($values, fn($x) => $x !== null);
    return $values ? max(0.0, floor((min($values) - 5) / 20) * 20) : 0.0;
};
$classCards = [];
if ($view === 'classes' && $subjectId) {
    $scope = App\Scope::classIds($user);
    foreach (Repo::classes() as $c) {
        if ($scope !== null && !in_array((int)$c['id'], $scope, true)) {
            continue;
        }
        $exams = Repo::classExamStats((int)$c['id'], $subjectId);
        if (!$exams) {
            continue;
        }
        $classCards[] = ['class' => $c, 'exams' => $exams, 'chart' => Chart::lines(
            array_column($exams, 'name'),
            [['class' => 'chart-line', 'name' => t('stats.legend_class'), 'values' => array_map('floatval', array_column($exams, 'avg_pct'))]],
            100.0, t('stats.class_chart', ['class' => $c['name']]), '%', $axisMin(array_column($exams, 'min_pct'))
        )];
    }
}

// ---- One student (everyone; staff pick the student, parents pick a child)
switch ($user['role']) {
    case Role::Student:
        $choices = [Repo::student($user['id'])];
        break;
    case Role::Parent:
        $choices = array_values(array_filter(array_map(fn($id) => Repo::student($id), $user['children'])));
        break;
    default:
        $choices = Repo::students($user);
}
$choices = array_values(array_filter($choices));

$wanted = (int)($_GET['student'] ?? 0);
$student = null;
foreach ($choices as $c) {
    if ((int)$c['id'] === $wanted) {
        $student = $c;
    }
}
$student ??= $choices[0] ?? null;
if ($student && !Policy::canViewStudent($user, (int)$student['id'])) {
    abort(403);
}

$v = ['choices' => $choices, 'student' => $student, 'charts' => [], 'view' => $view, 'staff' => $staff,
      'subjectOptions' => $subjectOptions, 'subjectId' => $subjectId, 'allowAllSubjects' => $allowAllSubjects, 'teacherOnly' => $teacherOnly, 'classCards' => $classCards];
if ($student && $view === 'student') {
    $sid = (int)$student['id'];
    $grades = Repo::grades($sid, $subjectId, $teacherOnly ? $validSubjects : null);
    $rows = [];
    foreach ($grades as $g) {
        $max = (float)$g['max_score'];
        $rows[] = $g + [
            'pct' => round((float)$g['score'] * 100 / $max, 1),
            'class_pct' => $g['class_avg'] !== null ? round((float)$g['class_avg'] * 100 / $max, 1) : null,
        ];
    }
    $v['grades'] = $rows;
    $v['avg'] = $rows ? round(array_sum(array_column($rows, 'pct')) / count($rows), 1) : null;
    if ($rows) {
        $v['charts']['grades'] = Chart::lines(
            array_column($rows, 'exam_name'),
            [
                ['class' => 'chart-line', 'name' => t('stats.legend_student'), 'values' => array_map('floatval', array_column($rows, 'pct'))],
                ['class' => 'chart-ref', 'name' => t('stats.legend_class'), 'values' => array_map(fn($x) => $x === null ? null : (float)$x, array_column($rows, 'class_pct'))],
            ],
            100.0, t('stats.grades_chart'), '%',
            $axisMin(array_merge(array_column($rows, 'pct'), array_column($rows, 'class_pct')))
        );
    }

    $att = Repo::attendanceRows($sid, $teacherOnly ? (int)$user['id'] : null);   // a teacher sees only their own lessons
    $attended = count(array_filter($att, fn($a) => $a['status'] === 'attended'));
    $v['attended'] = $attended;
    $v['missed'] = count($att) - $attended;
    $v['rate'] = $att ? (int)round($attended * 100 / count($att)) : null;
    // Lessons attended per week, last 8 weeks.
    $weeks = [];
    $thisMonday = week_start(App\Clock::today());
    for ($i = 7; $i >= 0; $i--) {
        $weeks[add_days($thisMonday, -7 * $i)] = 0;
    }
    foreach ($att as $a) {
        $m = week_start($a['lesson_date']);
        if ($a['status'] === 'attended' && isset($weeks[$m])) {
            $weeks[$m]++;
        }
    }
    $v['weeks'] = $weeks;
    if ($att) {
        $labels = array_map(fn($m) => (int)substr($m, 8, 2) . ' ' . t('monthshort.' . (int)substr($m, 5, 2)), array_keys($weeks));
        $v['charts']['attendance'] = Chart::line($labels, array_map('floatval', array_values($weeks)), max(5.0, (float)max($weeks)), t('stats.attendance_chart'));
    }
}

render('statistics', $v, [
    'title' => t('nav.stats'), 'active' => 'stats',
    'crumbs' => [[t('nav.home'), 'dashboard.php'], [t('nav.stats'), null]],
]);
