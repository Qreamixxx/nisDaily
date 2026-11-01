<?php
declare(strict_types=1);

// Small global helpers used by every page and template.

/** Read a setting from the real environment, then from the .env file, then use the default. */
function env(string $key, mixed $default = null): mixed
{
    static $file = null;
    if ($file === null) {
        $file = [];
        $path = dirname(__DIR__) . '/.env';
        if (is_file($path)) {
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                    continue;
                }
                [$k, $v] = explode('=', $line, 2);
                $file[trim($k)] = trim(trim($v), "\"'");
            }
        }
    }
    $real = getenv($key);
    if ($real !== false) {
        return $real;
    }
    return $file[$key] ?? $default;
}

/** Escape text for HTML. Every value printed into a page goes through this (stops XSS). */
function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/** Translate a key into the current language. */
function t(string $key, array $params = []): string
{
    return App\I18n::t($key, $params);
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

// ---------- CSRF ----------
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function check_csrf(): void
{
    if (!hash_equals(csrf_token(), (string)($_POST['csrf'] ?? ''))) {
        abort(400, 'error.csrf');
    }
}

/** Only allow local, relative redirect targets (stops "open redirect" tricks via ?next=). */
function safe_local_path(?string $path, string $fallback = 'dashboard.php'): string
{
    $path = (string)$path;
    if ($path === '' || $path[0] === '/' || str_contains($path, '//') || str_contains($path, '\\')
        || preg_match('#^[a-z][a-z0-9+.-]*:#i', $path) || preg_match('/[\r\n]/', $path)) {
        return $fallback;
    }
    return $path;
}

// ---------- Flash messages (shown once after a redirect) ----------
function flash(string $key, array $params = [], string $type = 'ok'): void
{
    $_SESSION['flash'][] = ['key' => $key, 'params' => $params, 'type' => $type];
}

function take_flash(): array
{
    $all = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return array_map(fn($f) => ['message' => t($f['key'], $f['params']), 'type' => $f['type']], $all);
}

// ---------- Dates ----------
function week_start(string $ymd): string
{
    $d = new DateTimeImmutable($ymd);
    return $d->modify('-' . ((int)$d->format('N') - 1) . ' days')->format('Y-m-d');
}

function add_days(string $ymd, int $days): string
{
    return (new DateTimeImmutable($ymd))->modify(($days >= 0 ? '+' : '') . $days . ' days')->format('Y-m-d');
}

function valid_date(?string $ymd): bool
{
    if ($ymd === null || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
        return false;
    }
    $d = DateTimeImmutable::createFromFormat('Y-m-d', $ymd);
    return $d !== false && $d->format('Y-m-d') === $ymd;
}

/** "Mon, 3 Nov" in the current language. */
function fmt_date(string $ymd): string
{
    $d = new DateTimeImmutable($ymd);
    return t('dayshort.' . $d->format('N')) . ', ' . $d->format('j') . ' ' . t('monthshort.' . $d->format('n'));
}

function fmt_date_long(string $ymd): string
{
    $d = new DateTimeImmutable($ymd);
    return t('day.' . $d->format('N')) . ', ' . $d->format('j') . ' ' . t('month.' . $d->format('n')) . ' ' . $d->format('Y');
}

function fmt_range(string $start, string $end): string
{
    return $start . '–' . $end;
}

/** Subject names are translated through lang files (subject.<slug>); the DB name is the fallback. */
function subject_label(string $slug, string $name): string
{
    $key = 'subject.' . $slug;
    $tr = t($key);
    return $tr === $key ? $name : $tr;
}

/** Link to the current page with some query parameters changed. */
function url_with(array $changes): string
{
    $q = array_merge($_GET, $changes);
    $q = array_filter($q, fn($v) => $v !== null && $v !== '');
    $path = basename((string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
    return $path . ($q ? '?' . http_build_query($q) : '');
}

// ---------- Navigation ----------
function nav_for(?array $user): array
{
    if (!$user) {
        return [
            'home'    => ['index.php', 'nav.home'],
            'how'     => ['index.php#how', 'nav.how'],
            'contact' => ['#contact', 'nav.contact'],
        ];
    }
    $home = ['home' => ['dashboard.php', 'nav.home']];
    return match ($user['role']) {
        App\Role::Student => $home + [
            'booking'      => ['subjects.php', 'nav.booking'],
            'appointments' => ['appointments.php', 'nav.appointments'],
            'stats'        => ['statistics.php', 'nav.stats'],
        ],
        App\Role::Parent => $home + [
            'appointments' => ['appointments.php', 'nav.appointments'],
            'stats'        => ['statistics.php', 'nav.stats'],
        ],
        App\Role::Teacher => $home + [
            'slots'   => ['slots.php', 'nav.slots'],
            'lessons' => ['lessons.php', 'nav.lessons'],
            'exams'   => ['exams.php', 'nav.exams'],
            'stats'   => ['statistics.php', 'nav.stats'],
        ],
        App\Role::Curator => $home + [
            'booking'      => ['subjects.php', 'nav.timetable'],
            'appointments' => ['appointments.php', 'nav.appointments'],
            'exams'        => ['exams.php', 'nav.exams'],
            'stats'        => ['statistics.php', 'nav.stats'],
            'users'        => ['users.php', 'nav.users'],
            'registrations' => ['registrations.php', 'nav.registrations'],
        ] + (App\Scope::isHead($user) ? [
            'classes'      => ['classes.php', 'nav.classes'],
            'bell'         => ['bell.php', 'nav.bell'],
        ] : []),
    };
}

// ---------- Rendering ----------
/**
 * Render templates/<name>.php inside the shared layout.
 * $page keys: title (string), crumbs ([[label, url|null], ...]), active (nav key), status (HTTP code)
 */
function render(string $template, array $vars = [], array $page = []): void
{
    $user = null;
    $unread = 0;
    $navBadges = [];
    try {
        $user = App\Auth::user();
        $unread = $user ? App\Notify::unread((int)$user['id']) : 0;
        if ($user && $user['role'] === App\Role::Curator) {
            $navBadges['registrations'] = App\Registration::pendingCount($user);
        }
    } catch (Throwable) {
        // Database down: still show the page (as a logged-out visitor) instead of crashing.
    }
    if (isset($page['status']) && !headers_sent()) {
        http_response_code((int)$page['status']);
    }
    $vars['user'] = $user;
    extract($vars, EXTR_OVERWRITE);
    ob_start();
    require dirname(__DIR__) . '/templates/' . $template . '.php';
    $content = ob_get_clean();
    $flashes = take_flash();
    require dirname(__DIR__) . '/templates/layout.php';
}

/** Stop and show a styled error page with a way back (403, 404, 400, 500). */
function abort(int $code, ?string $messageKey = null): never
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    render('error', ['code' => $code, 'message' => t($messageKey ?? 'error.' . $code)], [
        'title'  => t('error.title.' . ($code === 403 || $code === 404 || $code === 400 ? $code : 500)),
        'status' => $code,
    ]);
    exit;
}

function pager(int $total, int $page, int $per): array
{
    $pages = max(1, (int)ceil($total / $per));
    return ['page' => min(max(1, $page), $pages), 'pages' => $pages, 'total' => $total, 'per' => $per];
}

/** Page guards. Call at the top of a page to make it members-only. */
function require_login(App\Role ...$roles): array
{
    $user = App\Auth::user();
    if (!$user) {
        $next = basename((string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
        $q = (string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY);
        redirect('login.php?next=' . urlencode($next . ($q !== '' ? '?' . $q : '')));
    }
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if ($user['must_change_password'] && !in_array($script, ['profile.php', 'logout.php', 'lang.php'], true)) {
        redirect('profile.php?force=1');
    }
    if ($roles && !in_array($user['role'], $roles, true)) {
        abort(403);
    }
    return $user;
}

// ---------- Small view helpers ----------
function when_label(string $date, string $start, string $end): string
{
    return fmt_date($date) . ', ' . fmt_range($start, $end);
}

/** A status shown as TEXT plus colour (never colour alone). */
function status_badge(string $status, bool $active = true): string
{
    $key = $active ? $status : 'cancelled';
    return '<span class="badge badge-' . e($key) . '">' . e(t('status.' . $key)) . '</span>';
}

function role_label(string $role): string
{
    return t('role.' . $role);
}

/** Pagination links for list pages. */
function pager_html(array $pg): string
{
    if ($pg['pages'] <= 1) {
        return '';
    }
    $out = '<nav class="pager" aria-label="' . e(t('pager.label')) . '">';
    if ($pg['page'] > 1) {
        $out .= '<a class="btn btn-small" href="' . e(url_with(['page' => $pg['page'] - 1])) . '">← ' . e(t('pager.prev')) . '</a>';
    }
    $out .= '<span>' . e(t('pager.of', ['page' => $pg['page'], 'pages' => $pg['pages']])) . '</span>';
    if ($pg['page'] < $pg['pages']) {
        $out .= '<a class="btn btn-small" href="' . e(url_with(['page' => $pg['page'] + 1])) . '">' . e(t('pager.next')) . ' →</a>';
    }
    return $out . '</nav>';
}

/** Format a number for display: at most one decimal, no trailing zeros ("100", "64.7"). */
function num(int|float|string|null $v, int $decimals = 1): string
{
    if ($v === null || $v === '') {
        return '';
    }
    $s = number_format((float)$v, $decimals, '.', '');
    return str_contains($s, '.') ? rtrim(rtrim($s, '0'), '.') : $s;
}
