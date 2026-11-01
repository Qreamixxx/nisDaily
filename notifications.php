<?php
require __DIR__ . '/bootstrap.php';

use App\Notify;

$user = require_login();

$items = Notify::latest($user['id'], 50);
Notify::markAllRead($user['id']);   // opening the page counts as reading; the new ones stay highlighted below

render('notifications', ['items' => $items], [
    'title' => t('notif.title'), 'active' => 'notifications',
    'crumbs' => [[t('nav.home'), 'dashboard.php'], [t('notif.title'), null]],
]);
