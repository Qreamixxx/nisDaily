<?php
require __DIR__ . '/bootstrap.php';

use App\Auth;
use App\RuleException;

if (Auth::user()) {
    redirect('dashboard.php');
}

$error = null;
$iin = '';
$next = safe_local_path($_POST['next'] ?? $_GET['next'] ?? null);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $iin = trim((string)($_POST['iin'] ?? ''));
    try {
        Auth::attempt($iin, (string)($_POST['password'] ?? ''), (string)($_SERVER['REMOTE_ADDR'] ?? '0'));
        redirect($next);
    } catch (RuleException $e) {
        $error = t($e->key, $e->params);
        if ($e->key === 'login.wrong' && App\Registration::matchesPending($iin, (string)($_POST['password'] ?? ''))) {
            $error = t('login.pending');   // right IIN and password, but the curator hasn't approved it yet
        }
        http_response_code($e->key === 'login.locked' ? 429 : 401);
    }
}

render('login', ['error' => $error, 'iin' => $iin, 'next' => $next], ['title' => t('login.title'), 'active' => 'login']);
