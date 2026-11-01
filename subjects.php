<?php
require __DIR__ . '/bootstrap.php';

use App\Booking;
use App\Repo;
use App\Role;

$user = require_login(Role::Student, Role::Curator);
$today = App\Clock::today();

// Free seats in the next 4 weeks, per subject (only seats a student could still book).
$free = $slotCount = [];
foreach (Repo::openSlots($today, add_days($today, 28), null, null, $user['id']) as $s) {
    if (Booking::blockReason($s) !== null) {
        continue;
    }
    $slotCount[$s['subject_id']] = ($slotCount[$s['subject_id']] ?? 0) + 1;
    $free[$s['subject_id']] = ($free[$s['subject_id']] ?? 0) + max(0, (int)$s['capacity'] - (int)$s['taken']);
}

render('subjects', [
    'subjects' => Repo::subjects(), 'free' => $free, 'slotCount' => $slotCount,
], [
    'title' => t('nav.booking'), 'active' => 'booking',
    'crumbs' => [[t('nav.home'), 'dashboard.php'], [t('nav.booking'), null]],
]);
