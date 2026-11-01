<div class="title-row">
    <h1><?= e(t('nav.users')) ?></h1>
    <span class="actions"><a class="btn btn-primary btn-small" href="user_edit.php"><?= e(t('users.add')) ?></a> <a class="btn btn-small" href="users_import.php"><?= e(t('import.title')) ?></a></span>
</div>

<form method="get" class="filters">
    <div class="field"><label for="q"><?= e(t('appt.search')) ?></label><input id="q" name="q" type="search" value="<?= e($q) ?>"></div>
    <div class="field"><label for="role"><?= e(t('col.role')) ?></label>
        <select id="role" name="role"><option value=""><?= e(t('filter.all')) ?></option>
            <?php foreach (App\Role::cases() as $r): ?><option value="<?= e($r->value) ?>" <?= $roleFilter === $r->value ? 'selected' : '' ?>><?= e($r->label()) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label for="status"><?= e(t('col.status')) ?></label>
        <select id="status" name="status"><option value=""><?= e(t('filter.all')) ?></option>
            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>><?= e(t('users.active')) ?></option>
            <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>><?= e(t('users.inactive')) ?></option></select></div>
    <button class="btn"><?= e(t('filter.apply')) ?></button>
</form>

<div class="table-wrap"><table>
    <caption class="sr-only"><?= e(t('nav.users')) ?></caption>
    <thead><tr><th scope="col"><?= e(t('col.name')) ?></th><th scope="col"><?= e(t('login.iin')) ?></th><th scope="col"><?= e(t('col.role')) ?></th><th scope="col"><?= e(t('col.class')) ?></th><th scope="col"><?= e(t('col.status')) ?></th><th scope="col"><span class="sr-only"><?= e(t('col.actions')) ?></span></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <th scope="row"><?= e($r['full_name']) ?></th>
            <td><?= e($r['iin']) ?></td>
            <td><?= e(role_label($r['role'])) ?></td>
            <td><?= e((string)$r['class_name']) ?></td>
            <td><?= $r['is_active'] ? '<span class="badge badge-attended">' . e(t('users.active')) . '</span>' : '<span class="badge badge-cancelled">' . e(t('users.inactive')) . '</span>' ?></td>
            <td class="row-actions">
                <a class="btn btn-small" href="user_edit.php?id=<?= (int)$r['id'] ?>"><?= e(t('users.edit')) ?></a>
                <form method="post" data-confirm="<?= e(t('users.reset_confirm', ['name' => $r['full_name']])) ?>"><?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="action" value="reset"><input type="hidden" name="return" value="<?= e(url_with([])) ?>">
                    <button class="btn btn-small"><?= e(t('users.reset')) ?></button></form>
                <?php if ((int)$r['id'] !== $user['id']): ?>
                <form method="post" <?= $r['is_active'] ? 'data-confirm="' . e(t('users.deactivate_confirm', ['name' => $r['full_name']])) . '"' : '' ?>><?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="action" value="<?= $r['is_active'] ? 'deactivate' : 'activate' ?>"><input type="hidden" name="return" value="<?= e(url_with([])) ?>">
                    <button class="btn btn-small <?= $r['is_active'] ? 'btn-danger' : '' ?>"><?= e(t($r['is_active'] ? 'users.deactivate' : 'users.activate')) ?></button></form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?php if (!$rows): ?><p class="muted"><?= e(t('users.none')) ?></p><?php endif; ?>
<?= pager_html($pg) ?>
