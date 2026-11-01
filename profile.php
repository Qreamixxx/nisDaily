<?php
require __DIR__ . '/bootstrap.php';

use App\Auth;
use App\Db;

$user = require_login();
$forced = (bool)$user['must_change_password'];
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $current = (string)($_POST['current'] ?? '');
    $new = (string)($_POST['new'] ?? '');
    $hash = (string)Db::val('SELECT password_hash FROM users WHERE id = ?', [$user['id']]);
    if (!password_verify($current, $hash)) {
        $error = t('profile.err.current');
    } elseif (mb_strlen($new) < Auth::MIN_PASSWORD) {
        $error = t('profile.err.short', ['min' => Auth::MIN_PASSWORD]);
    } elseif ($new !== (string)($_POST['confirm'] ?? '')) {
        $error = t('profile.err.match');
    } elseif ($new === $current) {
        $error = t('profile.err.same');
    } else {
        Auth::setPassword($user['id'], $new, false);
        session_regenerate_id(true);
        flash('profile.changed');
        redirect('dashboard.php');
    }
    http_response_code(422);
}

render('profile', ['error' => $error, 'forced' => $forced], [
    'title' => t('profile.title'), 'active' => 'profile',
    'crumbs' => [[t('nav.home'), 'dashboard.php'], [t('profile.title'), null]],
]);
