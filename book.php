<?php
require __DIR__ . '/bootstrap.php';

use App\Booking;
use App\Repo;
use App\Role;
use App\RuleException;

$user = require_login(Role::Student, Role::Curator);
$subject = Repo::subject((int)($_GET['subject'] ?? 0)) ?? abort(404);
$today = App\Clock::today();

$thisWeek = week_start($today);
$defaultWeek = (int)(new DateTimeImmutable($today))->format('N') >= 6 ? add_days($thisWeek, 7) : $thisWeek;   // on a weekend show next week
$week = valid_date($_GET['week'] ?? null) ? week_start($_GET['week']) : $defaultWeek;
$week = max($thisWeek, min($week, add_days($thisWeek, 7 * 26)));   // no past weeks, at most half a year ahead
if ($user['role'] === Role::Curator && valid_date($_GET['week'] ?? null)) {
    $week = max(add_days($thisWeek, -7 * 26), min(week_start($_GET['week']), add_days($thisWeek, 7 * 26)));
}
$self = 'book.php?subject=' . $subject['id'] . '&week=' . $week;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    try {
        Booking::book($user, (int)($_POST['slot_id'] ?? 0));
        flash('book.ok');
    } catch (RuleException $e) {
        flash($e->key, $e->params + ['hours' => (int)env('BOOKING_CUTOFF_HOURS', 2)], 'error');
    }
    redirect($self);
}

$slots = Repo::openSlots($week, add_days($week, 6), (int)$subject['id'], null, $user['id']);
$bookings = $user['role'] === Role::Curator ? Repo::bookingsBySlot(array_column($slots, 'id')) : [];
if (App\Scope::isScoped($user)) {   // names of students from other classes stay private
    foreach ($bookings as &$list) {
        foreach ($list as &$b) {
            if (!App\Scope::studentInScope($user, (int)$b['student_id'])) {
                $b['student_name'] = t('scope.hidden');
            }
        }
    }
    unset($list, $b);
}
$days = [];
for ($i = 0; $i < 5; $i++) {
    $days[add_days($week, $i)] = [];
}
foreach ($slots as $s) {
    $reason = Booking::blockReason($s);
    if ($user['role'] === Role::Student && $reason === 'book.err.past') {
        continue;   // students don't need to see lessons that already started
    }
    if (!isset($days[$s['lesson_date']])) {
        continue;
    }
    $s['state'] = (int)$s['mine'] > 0 ? 'mine' : ($reason === 'book.err.past' ? 'past' : ($reason ? 'cutoff' : ((int)$s['taken'] >= (int)$s['capacity'] ? 'full' : 'free')));
    $days[$s['lesson_date']][] = $s;
}

$label = subject_label($subject['slug'], $subject['name']);
render('book', [
    'subject' => $subject, 'label' => $label, 'days' => $days, 'week' => $week, 'bookings' => $bookings,
    'prev' => ($user['role'] === Role::Curator || $week > $thisWeek) ? add_days($week, -7) : null,
    'next' => $week < add_days($thisWeek, 7 * 26) ? add_days($week, 7) : null,
    'thisWeek' => $thisWeek, 'today' => $today,
], [
    'title' => $label, 'active' => 'booking',
    'crumbs' => [[t('nav.home'), 'dashboard.php'], [t('nav.booking'), 'subjects.php'], [$label, null]],
]);
