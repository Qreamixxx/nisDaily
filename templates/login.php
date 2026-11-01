<section class="card narrow">
    <h1><?= e(t('login.title')) ?></h1>
    <?php if ($error): ?><p class="form-error" role="alert"><?= e($error) ?></p><?php endif; ?>
    <form method="post" action="login.php" class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <div class="field">
            <label for="iin"><?= e(t('login.iin')) ?></label>
            <input id="iin" name="iin" type="text" inputmode="numeric" pattern="\d{12}" maxlength="12" minlength="12"
                   required autocomplete="username" value="<?= e($iin) ?>" <?= $error ? 'aria-invalid="true"' : '' ?>>
            <small><?= e(t('login.iin_hint')) ?></small>
        </div>
        <div class="field">
            <label for="password"><?= e(t('login.password')) ?></label>
            <div class="with-button">
                <input id="password" name="password" type="password" required autocomplete="current-password">
                <button type="button" class="btn btn-small" data-toggle-password="password" data-show="<?= e(t('login.show')) ?>" data-hide="<?= e(t('login.hide')) ?>"><?= e(t('login.show')) ?></button>
            </div>
        </div>
        <button class="btn btn-primary btn-big" type="submit"><?= e(t('nav.login')) ?></button>
    </form>
    <p class="muted"><?= e(t('login.no_account')) ?> <a href="register.php"><?= e(t('nav.register')) ?></a></p>
</section>
