<?php
require __DIR__ . '/bootstrap.php';

use App\Exams;
use App\Repo;
use App\Role;
use App\RuleException;

$user = require_login(Role::Teacher, Role::Curator);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    try {
        $id = Exams::create(
            $user, (int)($_POST['subject'] ?? 0), (string)($_POST['name'] ?? ''), (string)($_POST['date'] ?? ''), (string)($_POST['max'] ?? '')
        );
        flash('exam.created');
        redirect('exam.php?id=' . $id);
    } catch (RuleException $e) {
        flash($e->key, $e->params, 'error');
        redirect('exams.php');
    }
}

$subjects = $user['role'] === Role::Curator ? Repo::subjects() : Repo::teacherSubjects($user['id']);
render('exams', ['exams' => Exams::listFor($user), 'subjects' => $subjects, 'today' => App\Clock::today()], [
    'title' => t('nav.exams'), 'active' => 'exams',
    'crumbs' => [[t('nav.home'), 'dashboard.php'], [t('nav.exams'), null]],
]);
