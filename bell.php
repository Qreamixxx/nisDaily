<?php
require __DIR__ . '/bootstrap.php';

use App\Db;
use App\Repo;
use App\Role;

$user = require_login(Role::Curator);
if (!App\Policy::canAdminister($user)) {
    abort(403);
}
$error = null;
$bell = Repo::bell();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $new = [];
    foreach ($bell as $n => $_) {
        $s = trim((string)($_POST['start'][$n] ?? ''));
        $e = trim((string)($_POST['end'][$n] ?? ''));
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $s) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $e) || $s >= $e) {
            $error = t('bell.err', ['n' => $n]);
            break;
        }
        $new[$n] = ['starts_at' => $s, 'ends_at' => $e];
    }
    if ($error === null) {
        Db::transaction(function () use ($new): void {
            foreach ($new as $n => $r) {
                Db::run('UPDATE bell_schedule SET starts_at = ?, ends_at = ? WHERE lesson_order = ?', [$r['starts_at'], $r['ends_at'], $n]);
            }
        });
        flash('bell.saved');
        redirect('bell.php');
    }
    $bell = $new + $bell;
    http_response_code(422);
}

render('bell', ['bell' => $bell, 'error' => $error], [
    'title' => t('nav.bell'), 'active' => 'bell',
    'crumbs' => [[t('nav.home'), 'dashboard.php'], [t('nav.bell'), null]],
]);
