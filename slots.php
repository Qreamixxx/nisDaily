<?php
require __DIR__ . '/bootstrap.php';

use App\Repo;
use App\Role;
use App\RuleException;
use App\Slots;

$user = require_login(Role::Teacher);
$today = App\Clock::today();
$thisWeek = week_start($today);
$defaultWeek = (int)(new DateTimeImmutable($today))->format('N') >= 6 ? add_days($thisWeek, 7) : $thisWeek;   // on a weekend show next week
$week = valid_date($_GET['week'] ?? null) ? week_start($_GET['week']) : $defaultWeek;
$week = max(add_days($thisWeek, -7 * 8), min($week, add_days($thisWeek, 7 * 26)));
$self = 'slots.php?week=' . $week;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    try {
        if (($_POST['action'] ?? '') === 'open') {
            $res = Slots::open(
                $user, (string)($_POST['date'] ?? ''), (int)($_POST['lesson'] ?? 0), (int)($_POST['subject'] ?? 0),
                (int)($_POST['capacity'] ?? 1), (string)($_POST['room'] ?? ''), (int)($_POST['repeat'] ?? 1)
            );
            flash('slot.opened', $res);
            if (valid_date($_POST['date'] ?? null)) {
                $self = 'slots.php?week=' . week_start($_POST['date']);
            }
        } elseif (($_POST['action'] ?? '') === 'close') {
            Slots::close($user, (int)($_POST['slot_id'] ?? 0));
            flash('slot.closed');
        }
    } catch (RuleException $e) {
        flash($e->key, $e->params, 'error');
    }
    redirect($self);
}

$slots = Repo::openSlots($week, add_days($week, 6), null, $user['id']);
$bookings = Repo::bookingsBySlot(array_column($slots, 'id'));
$mySubjects = Repo::teacherSubjects($user['id']);
$teacher = App\Db::one('SELECT room FROM teachers WHERE user_id = ?', [$user['id']]);

render('slots', [
    'slots' => $slots, 'bookings' => $bookings, 'week' => $week, 'thisWeek' => $thisWeek, 'today' => $today,
    'mySubjects' => $mySubjects, 'bell' => Repo::bell(), 'defaultRoom' => $teacher['room'] ?? '',
    'defaultDate' => (function (string $d): string { while ((int)(new DateTimeImmutable($d))->format('N') >= 6) { $d = add_days($d, 1); } return $d; })(max($today, $week)),
], [
    'title' => t('nav.slots'), 'active' => 'slots',
    'crumbs' => [[t('nav.home'), 'dashboard.php'], [t('nav.slots'), null]],
]);
