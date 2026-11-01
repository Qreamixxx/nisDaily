<h1><?= e(t('nav.bell')) ?></h1>
<p class="muted"><?= e(t('bell.lead')) ?></p>
<?php if ($error): ?><p class="form-error" role="alert"><?= e($error) ?></p><?php endif; ?>
<form method="post" class="card">
    <?= csrf_field() ?>
    <div class="table-wrap"><table>
        <caption class="sr-only"><?= e(t('nav.bell')) ?></caption>
        <thead><tr><th scope="col"><?= e(t('slots.lesson')) ?></th><th scope="col"><?= e(t('bell.start')) ?></th><th scope="col"><?= e(t('bell.end')) ?></th></tr></thead>
        <tbody><?php foreach ($bell as $n => $b): ?>
            <tr><th scope="row"><?= (int)$n ?></th>
                <td><input name="start[<?= (int)$n ?>]" type="time" required value="<?= e($b['starts_at']) ?>" aria-label="<?= e(t('bell.start')) ?> <?= (int)$n ?>"></td>
                <td><input name="end[<?= (int)$n ?>]" type="time" required value="<?= e($b['ends_at']) ?>" aria-label="<?= e(t('bell.end')) ?> <?= (int)$n ?>"></td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <p class="actions"><button class="btn btn-primary"><?= e(t('profile.save')) ?></button></p>
</form>
