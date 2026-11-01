<?php
require __DIR__ . '/bootstrap.php';

use App\Booking;
use App\Repo;
use App\Role;
use App\RuleException;

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    try {
        Booking::cancel($user, (int)($_POST['id'] ?? 0));
        flash('cancel.ok');
    } catch (RuleException $e) {
        flash($e->key, $e->params, 'error');
    }
    redirect(safe_local_path($_POST['return'] ?? null, 'appointments.php'));
}

$tab = in_array($_GET['tab'] ?? '', ['upcoming', 'past', 'cancelled'], true) ? $_GET['tab'] : 'upcoming';
$page = max(1, (int)($_GET['page'] ?? 1));
$opts = ['tab' => $tab, 'page' => $page, 'per' => 20];
switch ($user['role']) {
    case Role::Student: $opts['student_ids'] = [$user['id']]; break;
    case Role::Parent:  $opts['student_ids'] = $user['children']; break;
    case Role::Teacher: $opts['teacher_id'] = $user['id']; break;
    case Role::Curator:
        $opts['q'] = trim((string)($_GET['q'] ?? ''));
        $opts['subject_id'] = (int)($_GET['subject'] ?? 0);
        $opts['class_ids'] = App\Scope::classIds($user) ?? [];   // a scoped curator only sees their own classes
        break;
}
$res = Repo::appointments($opts);
$pg = pager($res['total'], $page, 20);
if ($pg['page'] !== $page) {   // asked for a page past the end: show the last one
    $opts['page'] = $pg['page'];
    $res = Repo::appointments($opts);
}

render('appointments', [
    'rows' => $res['rows'], 'tab' => $tab, 'pg' => $pg, 'subjects' => Repo::subjects(),
    'showStudent' => $user['role'] !== Role::Student,
    'hasCalendar' => $user['role'] !== Role::Curator,
    'q' => $opts['q'] ?? '', 'subjectFilter' => $opts['subject_id'] ?? 0,
], [
    'title' => t('nav.appointments'), 'active' => 'appointments',
    'crumbs' => [[t('nav.home'), 'dashboard.php'], [t('nav.appointments'), null]],
]);
