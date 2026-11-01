<?php
require __DIR__ . '/bootstrap.php';

use App\Repo;
use App\Role;
use App\RuleException;
use App\SchoolClasses;

$user = require_login(Role::Curator);
if (!App\Policy::canAdminister($user)) {
    abort(403);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    try {
        if (($_POST['action'] ?? '') === 'add') {
            SchoolClasses::add($user, (string)($_POST['name'] ?? ''));
            flash('classes.added');
        } elseif (($_POST['action'] ?? '') === 'delete') {
            SchoolClasses::delete($user, (int)($_POST['id'] ?? 0));
            flash('classes.deleted');
        }
    } catch (RuleException $e) {
        flash($e->key, $e->params, 'error');
    }
    redirect('classes.php');
}

render('classes', ['classes' => Repo::classes()], [
    'title' => t('nav.classes'), 'active' => 'classes',
    'crumbs' => [[t('nav.home'), 'dashboard.php'], [t('nav.classes'), null]],
]);
