<?php
declare(strict_types=1);

// Included first by every page: autoloading, settings, security headers, session, error handling.

require __DIR__ . '/src/helpers.php';

spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

date_default_timezone_set((string)env('APP_TZ', 'Asia/Almaty'));

define('DEBUG', filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN));
ini_set('display_errors', DEBUG ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

if (PHP_SAPI === 'cli') {
    $_SESSION = $_SESSION ?? [];
    return;
}

// Security headers: no inline scripts or styles, no framing, no content-type guessing.
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
if (is_https()) {
    header('Strict-Transport-Security: max-age=15552000');
}

// Session cookie: not readable from JavaScript, not sent from other sites, HTTPS-only when on HTTPS.
ini_set('session.use_strict_mode', '1');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => is_https()]);
session_name('nisdaily');
session_start();

// Anything uncaught (database down, a bug) becomes a friendly error page, not a white screen.
set_exception_handler(function (Throwable $e): void {
    error_log((string)$e);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    try {
        render('error', [
            'code'    => 500,
            'message' => DEBUG ? $e->getMessage() : t('error.500'),
        ], ['title' => t('error.title.500'), 'status' => 500]);
    } catch (Throwable) {
        http_response_code(500);
        echo 'Server error';
    }
});
