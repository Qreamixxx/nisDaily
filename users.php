<?php
require __DIR__ . '/bootstrap.php';

use App\Role;
use App\RuleException;
use App\Users;

$user = require_login(Role::Curator);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $id = (int)($_POST['id'] ?? 0);
    try {
        switch ($_POST['action'] ?? '') {
            case 'deactivate':
                Users::setActive($user, $id, false);
                flash('user.deactivated');
                break;
            case 'activate':
                Users::setActive($user, $id, true);
                flash('user.activated');
                break;
            case 'reset':
                $target = Users::find($id);
                $pw = Users::resetPassword($user, $id);
                flash('user.reset', ['name' => $target['full_name'], 'password' => $pw], 'secret');
                break;
        }
    } catch (RuleException $e) {
        flash($e->key, $e->params, 'error');
    }
    redirect(safe_local_path($_POST['return'] ?? null, 'users.php'));
}

$q = trim((string)($_GET['q'] ?? ''));
$role = (string)($_GET['role'] ?? '');
$status = (string)($_GET['status'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$res = Users::search($q, $role, $status, $page, 25, $user);
$pg = pager($res['total'], $page, 25);

render('users', ['rows' => $res['rows'], 'pg' => $pg, 'q' => $q, 'roleFilter' => $role, 'status' => $status], [
    'title' => t('nav.users'), 'active' => 'users',
    'crumbs' => [[t('nav.home'), 'dashboard.php'], [t('nav.users'), null]],
]);
