<?php $max = num($exam['max_score']); ?>
<h1><?= e($exam['name']) ?></h1>
<p class="muted"><?= e($label) ?> · <?= e(fmt_date_long($exam['exam_date'])) ?> · <?= e(t('exams.max')) ?>: <?= e($max) ?></p>
<p><?= e(t('exam.hint')) ?></p>

<form method="post">
    <?= csrf_field() ?>
    <div class="table-wrap"><table>
        <caption class="sr-only"><?= e($exam['name']) ?></caption>
        <thead><tr><th scope="col"><?= e(t('col.student')) ?></th><th scope="col"><?= e(t('exam.class')) ?></th><th scope="col"><?= e(t('exam.score', ['max' => $max])) ?></th></tr></thead>
        <tbody>
        <?php foreach ($students as $s): $has = $scores[$s['id']] ?? null; ?>
            <tr>
                <th scope="row"><label for="s<?= (int)$s['id'] ?>"><?= e($s['full_name']) ?></label></th>
                <td><?= e((string)$s['class_name']) ?></td>
                <td><input id="s<?= (int)$s['id'] ?>" class="score-input" name="score[<?= (int)$s['id'] ?>]" type="text" inputmode="decimal" maxlength="6"
                           value="<?= e($has !== null ? num($has) : '') ?>"></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <p class="actions"><button class="btn btn-primary"><?= e(t('exam.save')) ?></button> <a class="btn" href="exams.php"><?= e(t('exam.back')) ?></a></p>
</form>
