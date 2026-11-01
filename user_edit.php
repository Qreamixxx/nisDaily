<?php
require __DIR__ . '/bootstrap.php';

use App\Repo;
use App\Role;
use App\RuleException;
use App\Users;

$user = require_login(Role::Curator);
$id = (int)($_GET['id'] ?? 0);
$target = $id ? (Users::find($id) ?? abort(404)) : null;
if ($target && !App\Policy::canManageUser($user, $target)) {
    abort(403);
}

$form = $target ?? ['iin' => '', 'full_name' => '', 'role' => 'student', 'class_name' => '', 'room' => '', 'subject_ids' => [], 'child_ids' => [], 'class_ids' => []];
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $data = [
        'iin' => $_POST['iin'] ?? '', 'full_name' => $_POST['full_name'] ?? '', 'role' => $_POST['role'] ?? '',
        'class' => $_POST['class'] ?? '', 'room' => $_POST['room'] ?? '',
        'subject_ids' => (array)($_POST['subject_ids'] ?? []), 'child_ids' => (array)($_POST['child_ids'] ?? []),
        'class_ids' => (array)($_POST['class_ids'] ?? []),
    ];
    try {
        if ($target) {
            Users::update($user, $id, $data);
            flash('user.saved');
        } else {
            $res = Users::create($user, $data);
            flash('user.created', ['name' => trim((string)$data['full_name']), 'iin' => trim((string)$data['iin']), 'password' => $res['password']], 'secret');
        }
        redirect('users.php');
    } catch (RuleException $e) {
        $error = t($e->key, $e->params);
        $form = array_merge($form, $data, ['class_name' => $data['class']]);
        http_response_code(422);
    }
}

render('user_edit', [
    'target' => $target, 'form' => $form, 'error' => $error,
    'subjects' => Repo::subjects(), 'students' => Repo::students($user),
    'classes' => Repo::classes(), 'canAssign' => App\Scope::isHead($user),
    'roles' => App\Scope::isScoped($user) ? [Role::Student, Role::Parent] : Role::cases(),
], [
    'title' => $target ? $target['full_name'] : t('users.add'), 'active' => 'users',
    'crumbs' => [[t('nav.home'), 'dashboard.php'], [t('nav.users'), 'users.php'], [$target ? $target['full_name'] : t('users.add'), null]],
]);
