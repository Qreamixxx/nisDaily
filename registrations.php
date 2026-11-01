<?php
require __DIR__ . '/bootstrap.php';

use App\Registration;
use App\Role;
use App\RuleException;

$user = require_login(Role::Curator);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $id = (int)($_POST['id'] ?? 0);
    try {
        if (($_POST['action'] ?? '') === 'approve') {
            Registration::approve($user, $id);
            flash('reg.approved');
        } elseif (($_POST['action'] ?? '') === 'reject') {
            Registration::reject($user, $id);
            flash('reg.rejected');
        }
    } catch (RuleException $e) {
        flash($e->key, $e->params, 'error');
    }
    redirect('registrations.php');
}

render('registrations', ['rows' => Registration::pending($user)], [
    'title' => t('reg.pending_title'), 'active' => 'registrations',
    'crumbs' => [[t('nav.home'), 'dashboard.php'], [t('reg.pending_title'), null]],
]);
