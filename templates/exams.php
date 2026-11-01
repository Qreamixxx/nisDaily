<h1><?= e(t('exams.title')) ?></h1>

<?php if ($subjects): ?>
<section class="card" aria-labelledby="new-h">
    <h2 id="new-h"><?= e(t('exams.new')) ?></h2>
    <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <div class="field"><label for="subject"><?= e(t('col.subject')) ?></label>
            <select id="subject" name="subject"><?php foreach ($subjects as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e(subject_label($s['slug'], $s['name'])) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="name"><?= e(t('exams.name')) ?></label><input id="name" name="name" maxlength="60" required placeholder="SAU 1"></div>
        <div class="field"><label for="date"><?= e(t('col.date')) ?></label><input id="date" name="date" type="date" value="<?= e($today) ?>" required></div>
        <div class="field"><label for="max"><?= e(t('exams.max')) ?></label><input id="max" name="max" type="number" min="1" max="1000" step="0.5" value="100" required></div>
        <div class="field align-end"><button class="btn btn-primary"><?= e(t('exams.create')) ?></button></div>
    </form>
</section>
<?php else: ?>
    <section class="card flash flash-warn"><p><?= e(t('dash.no_subjects')) ?></p></section>
<?php endif; ?>

<?php if ($exams): ?>
<div class="table-wrap"><table>
    <caption class="sr-only"><?= e(t('exams.title')) ?></caption>
    <thead><tr><th scope="col"><?= e(t('exams.name')) ?></th><th scope="col"><?= e(t('col.subject')) ?></th><th scope="col"><?= e(t('col.date')) ?></th>
        <th scope="col"><?= e(t('exams.max')) ?></th><th scope="col"><?= e(t('exams.graded')) ?></th><th scope="col"><span class="sr-only"><?= e(t('col.actions')) ?></span></th></tr></thead>
    <tbody>
    <?php foreach ($exams as $x): ?>
        <tr>
            <th scope="row"><?= e($x['name']) ?></th>
            <td><?= e(subject_label($x['subject_slug'], $x['subject_name'])) ?></td>
            <td><?= e(fmt_date($x['exam_date'])) ?></td>
            <td><?= e(num($x['max_score'])) ?></td>
            <td><?= (int)$x['graded'] ?></td>
            <td><a class="btn btn-small" href="exam.php?id=<?= (int)$x['id'] ?>"><?= e(t('exams.enter')) ?></a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?php else: ?>
    <p class="muted"><?= e(t('exams.none')) ?></p>
<?php endif; ?>
