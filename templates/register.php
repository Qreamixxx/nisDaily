<?php $role = $old['role'] === 'parent' ? 'parent' : 'student'; ?>
<section class="card narrow">
<?php if ($done): ?>
    <h1><?= e(t('reg.done_title')) ?></h1>
    <p class="flash flash-success" role="status"><?= e(t('reg.done')) ?></p>
    <p><a class="btn btn-primary" href="login.php"><?= e(t('nav.login')) ?></a></p>
<?php else: ?>
    <h1><?= e(t('reg.title')) ?></h1>
    <p class="muted"><?= e(t('reg.intro')) ?></p>
    <?php if ($error): ?><p class="form-error" role="alert"><?= e($error) ?></p><?php endif; ?>
    <form method="post" action="register.php" class="stack" id="user-form">
        <?= csrf_field() ?>
        <fieldset>
            <legend><?= e(t('reg.i_am')) ?></legend>
            <label class="choice"><input type="radio" name="role" value="student" data-role-radio <?= $role === 'student' ? 'checked' : '' ?>> <?= e(t('role.student')) ?></label>
            <label class="choice"><input type="radio" name="role" value="parent" data-role-radio <?= $role === 'parent' ? 'checked' : '' ?>> <?= e(t('role.parent')) ?></label>
        </fieldset>
        <div class="field">
            <label for="iin"><?= e(t('login.iin')) ?></label>
            <input id="iin" name="iin" type="text" inputmode="numeric" pattern="\d{12}" maxlength="12" minlength="12" required autocomplete="off" value="<?= e($old['iin']) ?>">
            <small><?= e(t('login.iin_hint')) ?></small>
        </div>
        <div class="field">
            <label for="full_name"><?= e(t('reg.full_name')) ?></label>
            <input id="full_name" name="full_name" type="text" maxlength="100" required autocomplete="name" value="<?= e($old['full_name']) ?>">
        </div>
        <div class="field role-only role-student">
            <label for="class"><?= e(t('col.class')) ?> <span class="muted">(<?= e(t('reg.optional')) ?>)</span></label>
            <input id="class" name="class" type="text" maxlength="20" placeholder="10A" list="class-list" value="<?= e($old['class']) ?>">
            <datalist id="class-list"><?php foreach ($classNames as $cn): ?><option value="<?= e($cn) ?>"><?php endforeach; ?></datalist>
        </div>
        <div class="field role-only role-parent">
            <label for="child_iin"><?= e(t('reg.child_iin')) ?></label>
            <input id="child_iin" name="child_iin" type="text" inputmode="numeric" pattern="\d{12}" maxlength="12" minlength="12" autocomplete="off" value="<?= e($old['child_iin']) ?>">
            <small><?= e(t('reg.child_hint')) ?></small>
        </div>
        <div class="field">
            <label for="password"><?= e(t('login.password')) ?></label>
            <div class="with-button">
                <input id="password" name="password" type="password" minlength="<?= App\Auth::MIN_PASSWORD ?>" required autocomplete="new-password">
                <button type="button" class="btn btn-small" data-toggle-password="password" data-show="<?= e(t('login.show')) ?>" data-hide="<?= e(t('login.hide')) ?>"><?= e(t('login.show')) ?></button>
            </div>
            <small><?= e(t('profile.rule', ['min' => App\Auth::MIN_PASSWORD])) ?></small>
        </div>
        <div class="field">
            <label for="password2"><?= e(t('reg.password2')) ?></label>
            <input id="password2" name="password2" type="password" minlength="<?= App\Auth::MIN_PASSWORD ?>" required autocomplete="new-password">
        </div>
        <div class="hp" aria-hidden="true"><label>Website <input name="website" type="text" tabindex="-1" autocomplete="off"></label></div>
        <button class="btn btn-primary btn-big" type="submit"><?= e(t('reg.submit')) ?></button>
    </form>
    <p class="muted"><?= e(t('reg.have_account')) ?> <a href="login.php"><?= e(t('nav.login')) ?></a></p>
<?php endif; ?>
</section>
