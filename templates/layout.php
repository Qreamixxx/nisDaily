<?php
use App\I18n;

$lang = I18n::current();
$nav = nav_for($user);
$active = $page['active'] ?? '';
$title = (string)($page['title'] ?? '');
$crumbs = $page['crumbs'] ?? [];
$script = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
$qs = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY);
$back = $script . ($qs !== '' ? '?' . $qs : '');
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title !== '' ? $title . ' · Nis Daily' : 'Nis Daily') ?></title>
    <link rel="icon" href="assets/favicon.png" type="image/png">
    <link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
    <link rel="stylesheet" href="assets/styles.css?v=<?= (int)@filemtime(dirname(__DIR__) . '/assets/styles.css') ?>">
    <script src="assets/app.js?v=<?= (int)@filemtime(dirname(__DIR__) . '/assets/app.js') ?>" defer></script>
</head>
<body>
<a class="skip-link" href="#main"><?= e(t('a11y.skip')) ?></a>

<header class="site-header">
    <a class="logo" href="<?= $user ? 'dashboard.php' : 'index.php' ?>"><img src="assets/logo.png" width="960" height="288" alt="Nis Daily"></a>
    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav"><?= e(t('nav.menu')) ?></button>
    <nav id="site-nav" class="site-nav" aria-label="<?= e(t('nav.main')) ?>">
        <?php foreach ($nav as $key => [$href, $labelKey]): ?>
            <a href="<?= e($href) ?>" <?= $key === $active ? 'class="active" aria-current="page"' : '' ?>><?= e(t($labelKey)) ?><?php if (!empty($navBadges[$key])): ?> <span class="count"><?= (int)$navBadges[$key] ?></span><?php endif; ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="header-tools">
        <span class="lang-switch" role="group" aria-label="<?= e(t('nav.language')) ?>">
            <?php foreach (I18n::LANGS as $code => $name): ?>
                <a href="lang.php?to=<?= $code ?>&amp;back=<?= e(urlencode($back)) ?>" lang="<?= $code ?>"
                   <?= $code === $lang ? 'aria-current="true" class="current"' : '' ?>><?= $name ?></a>
            <?php endforeach; ?>
        </span>
        <?php if ($user): ?>
            <a class="bell-link" href="notifications.php" aria-label="<?= e(t('notif.title')) ?><?= $unread ? ' (' . $unread . ' ' . e(t('notif.new')) . ')' : '' ?>">
                <span aria-hidden="true">🔔</span><?php if ($unread): ?><span class="count"><?= (int)$unread ?></span><?php endif; ?>
            </a>
            <a class="who" href="profile.php"><?= e($user['full_name']) ?> <small>(<?= e($user['role']->label()) ?>)</small></a>
            <form method="post" action="logout.php" class="inline"><?= csrf_field() ?><button class="btn btn-small"><?= e(t('nav.logout')) ?></button></form>
        <?php else: ?>
            <?php if ($active !== 'login'): ?><a class="btn btn-small" href="login.php"><?= e(t('nav.login')) ?></a><?php endif; ?>
            <?php if ($active !== 'register'): ?><a class="btn btn-small btn-primary" href="register.php"><?= e(t('nav.register')) ?></a><?php endif; ?>
        <?php endif; ?>
    </div>
</header>

<?php if ($crumbs): ?>
<nav class="crumbs" aria-label="<?= e(t('nav.breadcrumb')) ?>">
    <ol>
        <?php foreach ($crumbs as [$label, $url]): ?>
            <li><?php if ($url): ?><a href="<?= e($url) ?>"><?= e($label) ?></a><?php else: ?><span aria-current="page"><?= e($label) ?></span><?php endif; ?></li>
        <?php endforeach; ?>
    </ol>
</nav>
<?php endif; ?>

<main id="main" class="page">
    <?php foreach ($flashes as $f): ?>
        <div class="flash flash-<?= e($f['type']) ?>" role="<?= $f['type'] === 'error' ? 'alert' : 'status' ?>">
            <span><?= e($f['message']) ?></span>
            <button class="flash-close" type="button" aria-label="<?= e(t('a11y.close')) ?>">×</button>
        </div>
    <?php endforeach; ?>
    <?= $content ?>
</main>

<footer class="site-footer" id="contact">
    <p><strong><?= e(t('footer.contact')) ?></strong> · contact@example.com · +7 700 000 00 00</p>
</footer>
</body>
</html>
