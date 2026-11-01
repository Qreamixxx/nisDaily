<h1><?= e(t('nav.classes')) ?></h1>
<p class="muted"><?= e(t('classes.help')) ?></p>

<form method="post" class="card inline-form">
    <?= csrf_field() ?><input type="hidden" name="action" value="add">
    <div class="field"><label for="name"><?= e(t('classes.new')) ?></label>
        <input id="name" name="name" maxlength="20" placeholder="12B" required></div>
    <button class="btn btn-primary"><?= e(t('classes.add')) ?></button>
</form>

<div class="table-wrap"><table>
    <caption class="sr-only"><?= e(t('nav.classes')) ?></caption>
    <thead><tr><th scope="col"><?= e(t('col.class')) ?></th><th scope="col"><?= e(t('nav.users')) ?></th><th scope="col"><?= e(t('classes.curators')) ?></th><th scope="col"><span class="sr-only"><?= e(t('col.actions')) ?></span></th></tr></thead>
    <tbody>
    <?php foreach ($classes as $c): ?>
        <tr>
            <th scope="row"><?= e($c['name']) ?></th>
            <td><?= (int)$c['students'] ?> <?= e(t('role.students')) ?></td>
            <td><?= $c['curators'] ? e(implode(', ', $c['curators'])) : '<span class="muted">' . e(t('classes.head_only')) . '</span>' ?></td>
            <td class="row-actions">
                <?php if ((int)$c['students'] === 0): ?>
                <form method="post" class="inline" data-confirm="<?= e(t('classes.delete_confirm', ['name' => $c['name']])) ?>"><?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                    <button class="btn btn-danger btn-small"><?= e(t('classes.delete')) ?></button></form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$classes): ?><tr><td colspan="4" class="muted"><?= e(t('classes.none')) ?></td></tr><?php endif; ?>
    </tbody>
</table></div>
<p class="muted"><?= e(t('classes.assign_hint')) ?> <a href="users.php?role=curator"><?= e(t('nav.users')) ?></a></p>
