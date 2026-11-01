<?php use App\Role; $role = $form['role']; $isNew = !$target; ?>
<h1><?= e($isNew ? t('users.add') : $target['full_name']) ?></h1>
<?php if ($error): ?><p class="form-error" role="alert"><?= e($error) ?></p><?php endif; ?>
<?php if ($isNew): ?><p class="muted"><?= e(t('users.temp_note')) ?></p><?php endif; ?>

<form method="post" class="card stack" id="user-form">
    <?= csrf_field() ?>
    <div class="field"><label for="iin"><?= e(t('login.iin')) ?></label>
        <input id="iin" name="iin" inputmode="numeric" pattern="\d{12}" maxlength="12" required value="<?= e($form['iin']) ?>" <?= $isNew ? '' : 'readonly' ?>></div>
    <div class="field"><label for="full_name"><?= e(t('col.name')) ?></label>
        <input id="full_name" name="full_name" maxlength="100" required value="<?= e($form['full_name']) ?>"></div>
    <div class="field"><label for="role"><?= e(t('col.role')) ?></label>
        <?php if ($isNew): ?>
            <select id="role" name="role" data-role-select>
                <?php foreach ($roles as $r): ?><option value="<?= e($r->value) ?>" <?= $role === $r->value ? 'selected' : '' ?>><?= e($r->label()) ?></option><?php endforeach; ?>
            </select>
        <?php else: ?>
            <input id="role" name="role" readonly value="<?= e($role) ?>"><small><?= e(t('users.role_fixed')) ?></small>
        <?php endif; ?>
    </div>

    <div class="field role-only role-student"><label for="class"><?= e(t('col.class')) ?></label>
        <input id="class" name="class" maxlength="20" placeholder="10A" value="<?= e((string)($form['class_name'] ?? '')) ?>"></div>

    <div class="role-only role-teacher">
        <div class="field"><label for="room"><?= e(t('col.room')) ?></label><input id="room" name="room" maxlength="20" value="<?= e((string)($form['room'] ?? '')) ?>"></div>
        <fieldset><legend><?= e(t('users.subjects')) ?></legend>
            <?php foreach ($subjects as $s): ?>
                <label class="check"><input type="checkbox" name="subject_ids[]" value="<?= (int)$s['id'] ?>" <?= in_array((int)$s['id'], array_map('intval', $form['subject_ids'] ?? []), true) ? 'checked' : '' ?>> <?= e(subject_label($s['slug'], $s['name'])) ?></label>
            <?php endforeach; ?>
        </fieldset>
    </div>

    <fieldset class="role-only role-parent"><legend><?= e(t('users.children')) ?></legend>
        <?php foreach ($students as $s): ?>
            <label class="check"><input type="checkbox" name="child_ids[]" value="<?= (int)$s['id'] ?>" <?= in_array((int)$s['id'], array_map('intval', $form['child_ids'] ?? []), true) ? 'checked' : '' ?>> <?= e($s['full_name']) ?> <?= $s['class_name'] ? '(' . e($s['class_name']) . ')' : '' ?></label>
        <?php endforeach; ?>
    </fieldset>

    <fieldset class="role-only role-curator"><legend><?= e(t('users.classes')) ?></legend>
        <?php if ($canAssign): ?>
            <p class="muted"><?= e(t('users.classes_help')) ?></p>
            <?php foreach ($classes as $c): ?>
                <label class="check"><input type="checkbox" name="class_ids[]" value="<?= (int)$c['id'] ?>" <?= in_array((int)$c['id'], array_map('intval', $form['class_ids'] ?? []), true) ? 'checked' : '' ?>> <?= e($c['name']) ?></label>
            <?php endforeach; ?>
            <?php if (!$classes): ?><p class="muted"><?= e(t('classes.none')) ?></p><?php endif; ?>
        <?php endif; ?>
    </fieldset>

    <p class="actions"><button class="btn btn-primary"><?= e(t('profile.save')) ?></button> <a class="btn" href="users.php"><?= e(t('exam.back')) ?></a></p>
</form>
