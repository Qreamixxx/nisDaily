<?php
require __DIR__ . '/bootstrap.php';

use App\Role;
use App\RuleException;
use App\Users;

$user = require_login(Role::Curator);
$result = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $csv = (string)($_POST['csv'] ?? '');
    if (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
        if ((int)$_FILES['file']['size'] > 200000) {
            $error = t('import.err.big');
        } else {
            $csv = (string)file_get_contents($_FILES['file']['tmp_name']);
        }
    }
    if ($error === null) {
        try {
            $result = Users::importCsv($user, $csv);
        } catch (RuleException $e) {
            $error = t($e->key, $e->params);
        }
    }
}

render('users_import', ['result' => $result, 'error' => $error], [
    'title' => t('import.title'), 'active' => 'users',
    'crumbs' => [[t('nav.home'), 'dashboard.php'], [t('nav.users'), 'users.php'], [t('import.title'), null]],
]);
