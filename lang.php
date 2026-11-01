<?php
require __DIR__ . '/bootstrap.php';

$to = (string)($_GET['to'] ?? 'en');
if (isset(App\I18n::LANGS[$to])) {
    $_SESSION['lang'] = $to;
    setcookie('lang', $to, ['expires' => time() + 31536000, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => is_https()]);
    $user = App\Auth::user();
    if ($user) {
        App\Db::run('UPDATE users SET lang = ? WHERE id = ?', [$to, $user['id']]);
    }
}
redirect(safe_local_path($_GET['back'] ?? null, 'index.php'));
