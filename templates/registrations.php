<h1><?= e(t('reg.pending_title')) ?></h1>
<?php if (!$rows): ?>
    <section class="card"><p class="muted"><?= e(t('reg.none')) ?></p></section>
<?php else: ?>
<p class="muted"><?= e(t('reg.pending_help')) ?></p>
<div class="table-wrap"><table>
    <caption class="sr-only"><?= e(t('reg.pending_title')) ?></caption>
    <thead><tr><th scope="col"><?= e(t('col.name')) ?></th><th scope="col"><?= e(t('login.iin')) ?></th><th scope="col"><?= e(t('col.role')) ?></th><th scope="col"><?= e(t('reg.details')) ?></th><th scope="col"><?= e(t('reg.sent')) ?></th><th scope="col"><span class="sr-only"><?= e(t('col.actions')) ?></span></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <th scope="row"><?= e($r['full_name']) ?></th>
            <td><?= e($r['iin']) ?></td>
            <td><?= e(role_label($r['role'])) ?></td>
            <td>
                <?php if ($r['role'] === 'student'): ?>
                    <?= e($r['class_name'] ? t('col.class') . ': ' . $r['class_name'] : '–') ?>
                <?php elseif ($r['child_name']): ?>
                    <?= e(t('reg.child_of', ['name' => $r['child_name']])) ?> <span class="muted">(<?= e($r['child_iin']) ?>)</span>
                <?php else: ?>
                    <span class="badge badge-cancelled"><?= e(t('reg.child_unknown', ['iin' => $r['child_iin']])) ?></span>
                <?php endif; ?>
            </td>
            <td><?= e(fmt_date(substr($r['created_at'], 0, 10))) ?></td>
            <td class="row-actions">
                <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="action" value="approve"><button class="btn btn-primary btn-small"><?= e(t('reg.approve')) ?></button></form>
                <form method="post" class="inline" data-confirm="<?= e(t('reg.reject_confirm', ['name' => $r['full_name']])) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="action" value="reject"><button class="btn btn-danger btn-small"><?= e(t('reg.reject')) ?></button></form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?php endif; ?>
