<section class="hero">
    <img class="hero-logo" src="assets/logo.png" width="960" height="288" alt="Nis Daily">
    <h1><?= e(t('home.h1')) ?></h1>
    <p class="lead"><?= e(t('home.lead')) ?></p>
    <p class="cta-row"><a class="btn btn-primary btn-big" href="login.php"><?= e(t('home.cta')) ?></a> <a class="btn btn-big" href="register.php"><?= e(t('home.cta2')) ?></a></p>
</section>

<section class="steps" id="how" aria-label="<?= e(t('home.how')) ?>">
    <h2><?= e(t('home.how')) ?></h2>
    <ol class="grid grid-3">
        <li class="card"><strong><?= e(t('home.s1.t')) ?></strong><p><?= e(t('home.s1.d')) ?></p></li>
        <li class="card"><strong><?= e(t('home.s2.t')) ?></strong><p><?= e(t('home.s2.d')) ?></p></li>
        <li class="card"><strong><?= e(t('home.s3.t')) ?></strong><p><?= e(t('home.s3.d')) ?></p></li>
    </ol>
</section>
<p class="muted center"><?= e(t('home.noaccount')) ?></p>
