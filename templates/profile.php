<h1><?= e(t('profile.title')) ?></h1>
<?php if ($forced): ?><p class="flash flash-warn" role="alert"><?= e(t('profile.forced')) ?></p><?php endif; ?>

<div class="grid grid-2">
    <section class="card" aria-labelledby="pw-h">
        <h2 id="pw-h"><?= e(t('profile.change')) ?></h2>
        <?php if ($error): ?><p class="form-error" role="alert"><?= e($error) ?></p><?php endif; ?>
        <form method="post" class="stack">
            <?= csrf_field() ?>
            <div class="field"><label for="current"><?= e(t('profile.current')) ?></label><input id="current" name="current" type="password" required autocomplete="current-password"></div>
            <div class="field"><label for="new"><?= e(t('profile.new')) ?></label><input id="new" name="new" type="password" required minlength="<?= App\Auth::MIN_PASSWORD ?>" autocomplete="new-password"><small><?= e(t('profile.rule', ['min' => App\Auth::MIN_PASSWORD])) ?></small></div>
            <div class="field"><label for="confirm"><?= e(t('profile.confirm')) ?></label><input id="confirm" name="confirm" type="password" required autocomplete="new-password"></div>
            <button class="btn btn-primary"><?= e(t('profile.save')) ?></button>
        </form>
    </section>
    <section class="card" aria-labelledby="who-h">
        <h2 id="who-h"><?= e(t('profile.account')) ?></h2>
        <dl class="facts">
            <dt><?= e(t('col.name')) ?></dt><dd><?= e($user['full_name']) ?></dd>
            <dt><?= e(t('login.iin')) ?></dt><dd><?= e($user['iin']) ?></dd>
            <dt><?= e(t('col.role')) ?></dt><dd><?= e($user['role']->label()) ?></dd>
        </dl>
    </section>
</div>
