<?php
require __DIR__ . '/bootstrap.php';

use App\Db;
use App\Exams;
use App\Policy;
use App\Repo;
use App\Role;
use App\RuleException;

$user = require_login(Role::Teacher, Role::Curator);
$exam = Exams::find((int)($_GET['id'] ?? 0)) ?? abort(404);
if (!Policy::canManageExam($user, (int)$exam['subject_id'])) {
    abort(403);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    try {
        $n = Exams::saveScores($user, (int)$exam['id'], (array)($_POST['score'] ?? []));
        flash('exam.saved', ['n' => $n]);
    } catch (RuleException $e) {
        flash($e->key, $e->params, 'error');
    }
    redirect('exam.php?id=' . $exam['id']);
}

$scores = array_column(Db::all('SELECT student_id, score FROM grades WHERE exam_id = ?', [$exam['id']]), 'score', 'student_id');
$label = subject_label($exam['subject_slug'], $exam['subject_name']);
render('exam', ['exam' => $exam, 'label' => $label, 'students' => Repo::students($user), 'scores' => $scores], [
    'title' => $exam['name'], 'active' => 'exams',
    'crumbs' => [[t('nav.home'), 'dashboard.php'], [t('nav.exams'), 'exams.php'], [$exam['name'], null]],
]);
