<?php
require __DIR__ . '/bootstrap.php';

use App\Db;
use App\Notify;
use App\Repo;
use App\Role;

$user = require_login();
$today = App\Clock::today();
$now = App\Clock::now();
$v = ['notes' => array_slice(array_filter(Notify::latest($user['id'], 10), fn($n) => !$n['is_read']), 0, 3)];

/** Next lesson + latest grade for one student. */
$summary = function (int $studentId): array {
    $next = Repo::appointments(['student_ids' => [$studentId], 'tab' => 'upcoming', 'limit' => 3]);
    $grades = Repo::grades($studentId);
    return ['next' => $next['rows'], 'upcoming' => $next['total'], 'grade' => $grades ? end($grades) : null];
};

switch ($user['role']) {
    case Role::Student:
        $v['mine'] = $summary($user['id']);
        break;
    case Role::Parent:
        $v['kids'] = [];
        foreach ($user['children'] as $cid) {
            if ($child = Repo::student($cid)) {
                $v['kids'][] = ['student' => $child] + $summary($cid);
            }
        }
        break;
    case Role::Teacher:
        $slots = Repo::openSlots($today, add_days($today, 60), null, $user['id']);
        $v['today'] = array_values(array_filter($slots, fn($s) => $s['lesson_date'] === $today));
        $v['bookings'] = Repo::bookingsBySlot(array_column($v['today'], 'id'));
        $v['openCount'] = count($slots);
        $v['toMark'] = (int)Db::val(
            "SELECT COUNT(*) FROM appointments a JOIN slots sl ON sl.id = a.slot_id JOIN bell_schedule b ON b.lesson_order = a.lesson_order
              WHERE sl.teacher_id = ? AND a.is_active = 1 AND a.status = 'booked'
                AND (a.lesson_date < ? OR (a.lesson_date = ? AND b.starts_at <= ?))",
            [$user['id'], $today, $today, $now->format('H:i')]
        );
        break;
    case Role::Curator:
        $v['counts'] = [];
        foreach (Db::all('SELECT role, COUNT(*) AS n FROM users WHERE is_active = 1 GROUP BY role') as $r) {
            $v['counts'][$r['role']] = (int)$r['n'];
        }
        $scope = App\Scope::classIds($user);
        $v['classNames'] = App\Scope::classNames($user);
        $v['tileRoles'] = ['student', 'teacher', 'parent', 'curator'];
        if ($scope) {   // a scoped curator counts only their own students and those students' parents
            $in = App\Scope::marks($scope);
            $v['counts']['student'] = (int)Db::val("SELECT COUNT(*) FROM students s JOIN users u ON u.id = s.user_id WHERE u.is_active = 1 AND s.class_id IN ($in)", $scope);
            $v['counts']['parent'] = (int)Db::val(
                "SELECT COUNT(DISTINCT ps.parent_id) FROM parent_student ps JOIN students s ON s.user_id = ps.student_id JOIN users p ON p.id = ps.parent_id
                  WHERE p.is_active = 1 AND s.class_id IN ($in)", $scope);
            $v['tileRoles'] = ['student', 'parent'];
        }
        $v['pendingRegs'] = App\Registration::pendingCount($user);
        $v['upcoming'] = Repo::appointments(['tab' => 'upcoming', 'limit' => 1, 'class_ids' => $scope ?? []])['total'];
        $v['openSlots'] = count(Repo::openSlots($today, add_days($today, 7)));
        break;
}

render('dashboard', $v, ['title' => t('nav.home'), 'active' => 'home']);
