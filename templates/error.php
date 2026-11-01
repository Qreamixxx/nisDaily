<section class="card narrow center">
    <p class="big-code" aria-hidden="true"><?= (int)$code ?></p>
    <h1><?= e($message) ?></h1>
    <p class="actions">
        <a class="btn btn-primary" href="<?= $user ? 'dashboard.php' : 'index.php' ?>"><?= e(t('error.home')) ?></a>
        <?php if (!$user): ?><a class="btn" href="login.php"><?= e(t('nav.login')) ?></a><?php endif; ?>
    </p>
</section>
