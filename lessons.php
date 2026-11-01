<?php
require __DIR__ . '/bootstrap.php';

use App\Booking;
use App\Repo;
use App\Role;
use App\RuleException;

$user = require_login(Role::Teacher);
$today = App\Clock::today();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    try {
        Booking::mark($user, (int)($_POST['id'] ?? 0), (string)($_POST['status'] ?? ''));
        flash('mark.ok');
    } catch (RuleException $e) {
        flash($e->key, $e->params, 'error');
    }
    redirect('lessons.php#lesson-' . (int)($_POST['slot_id'] ?? 0));
}

$slots = Repo::openSlots(add_days($today, -30), add_days($today, 30), null, $user['id']);
$bookings = Repo::bookingsBySlot(array_column($slots, 'id'));
$needs = $upcoming = $done = [];
foreach ($slots as $s) {
    $list = $bookings[(int)$s['id']] ?? [];
    $started = Booking::hasStarted($s);
    $s['students'] = $list;
    if (!$started) {
        $upcoming[] = $s;
    } elseif (array_filter($list, fn($b) => $b['status'] === 'booked')) {
        $needs[] = $s;
    } elseif ($list) {
        $done[] = $s;
    }
}

render('lessons', [
    'needs' => $needs, 'upcoming' => $upcoming, 'done' => array_reverse($done),
], [
    'title' => t('nav.lessons'), 'active' => 'lessons',
    'crumbs' => [[t('nav.home'), 'dashboard.php'], [t('nav.lessons'), null]],
]);
