<?php
require __DIR__ . '/bootstrap.php';

use App\Registration;
use App\RuleException;

if (App\Auth::user()) {
    redirect('dashboard.php');
}

$error = null;
$done = false;
$old = ['role' => 'student', 'iin' => '', 'full_name' => '', 'class' => '', 'child_iin' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    foreach ($old as $k => $_) {
        $old[$k] = trim((string)($_POST[$k] ?? $old[$k]));
    }
    try {
        Registration::submit($_POST, (string)($_SERVER['REMOTE_ADDR'] ?? '0'));
        $done = true;
    } catch (RuleException $e) {
        $error = t($e->key, $e->params);
        http_response_code(422);
    }
}

render('register', ['error' => $error, 'done' => $done, 'old' => $old, 'classNames' => array_column(App\Repo::classes(), 'name')], [
    'title' => t('reg.title'), 'active' => 'register',
]);
