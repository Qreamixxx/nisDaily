<?php
declare(strict_types=1);

namespace App;

final class Auth
{
    public const MIN_PASSWORD = 8;
    private const MAX_FAILS_PER_IIN = 5;
    private const MAX_FAILS_PER_IP  = 20;
    private const WINDOW_SECONDS    = 900; // 15 minutes

    private static bool $loaded = false;
    private static ?array $user = null;

    /**
     * The logged-in user, loaded fresh from the database on every request.
     * Only the user id is kept in the session, so deactivating or changing an account
     * takes effect immediately instead of after the person logs out.
     */
    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }
        self::$loaded = true;
        $id = $_SESSION['uid'] ?? null;
        if (!$id) {
            return null;
        }
        $row = Db::one(
            'SELECT id, iin, full_name, role, lang, is_active, must_change_password FROM users WHERE id = ?',
            [(int)$id]
        );
        if (!$row || !$row['is_active']) {
            unset($_SESSION['uid']);
            return null;
        }
        $row['role'] = Role::from($row['role']);
        $row['children'] = [];
        $row['subject_ids'] = [];
        if ($row['role'] === Role::Parent) {
            $row['children'] = array_map('intval', array_column(
                Db::all('SELECT student_id FROM parent_student WHERE parent_id = ?', [$row['id']]),
                'student_id'
            ));
        } elseif ($row['role'] === Role::Teacher) {
            $row['subject_ids'] = array_map('intval', array_column(
                Db::all('SELECT subject_id FROM teacher_subjects WHERE teacher_id = ?', [$row['id']]),
                'subject_id'
            ));
        }
        $row['id'] = (int)$row['id'];
        $row['class_ids'] = [];
        if ($row['role'] === Role::Curator) {
            $row['class_ids'] = array_map('intval', array_column(
                Db::all('SELECT class_id FROM curator_classes WHERE curator_id = ?', [$row['id']]),
                'class_id'
            ));
        }
        return self::$user = $row;
    }

    public static function forget(): void
    {
        self::$loaded = false;
        self::$user = null;
    }

    /** @throws RuleException when the login fails or is locked out */
    public static function attempt(string $iin, string $password, string $ip): array
    {
        $now = Clock::now();
        $since = date('Y-m-d H:i:s', $now->getTimestamp() - self::WINDOW_SECONDS);
        $byIin = (int)Db::val('SELECT COUNT(*) FROM login_attempts WHERE iin = ? AND attempted_at >= ?', [$iin, $since]);
        $byIp  = (int)Db::val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at >= ?', [$ip, $since]);
        if ($byIin >= self::MAX_FAILS_PER_IIN || $byIp >= self::MAX_FAILS_PER_IP) {
            throw new RuleException('login.locked');
        }

        $row = Db::one('SELECT id, password_hash, is_active FROM users WHERE iin = ?', [$iin]);
        // Always run one password check, so a missing account takes as long as a wrong password.
        static $dummy = null;
        $dummy ??= password_hash('not-a-real-password', PASSWORD_DEFAULT);
        $hash = $row['password_hash'] ?? $dummy;
        $ok = password_verify($password, $hash);

        if (!$row || !$ok || !$row['is_active']) {
            Db::run('INSERT INTO login_attempts (iin, ip, attempted_at) VALUES (?, ?, ?)', [mb_substr($iin, 0, 20), $ip, $now->format('Y-m-d H:i:s')]);
            Db::run('DELETE FROM login_attempts WHERE attempted_at < ?', [date('Y-m-d H:i:s', $now->getTimestamp() - 86400)]);
            throw new RuleException('login.wrong');
        }

        Db::run('DELETE FROM login_attempts WHERE iin = ?', [$iin]);
        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            Db::run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $row['id']]);
        }
        return self::start((int)$row['id']);
    }

    /** Mark the browser session as logged in as $userId. */
    public static function start(int $userId): array
    {
        if (PHP_SAPI !== 'cli') {
            session_regenerate_id(true);   // stops "session fixation"
        }
        $lang = $_SESSION['lang'] ?? null;
        $_SESSION['uid'] = $userId;
        $_SESSION['csrf'] = bin2hex(random_bytes(32)); // new CSRF token per login
        self::forget();
        $user = self::user();
        if ($lang && $user && $user['lang'] !== $lang) {
            // Keep the language they picked on the login page.
            Db::run('UPDATE users SET lang = ? WHERE id = ?', [$lang, $userId]);
            self::forget();
            $user = self::user();
        }
        return $user;
    }

    public static function logout(): void
    {
        unset($_SESSION['uid']);
        if (PHP_SAPI !== 'cli') {
            session_regenerate_id(true);
        }
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        self::forget();
    }

    public static function setPassword(int $userId, string $password, bool $mustChange = false): void
    {
        Db::run(
            'UPDATE users SET password_hash = ?, must_change_password = ? WHERE id = ?',
            [password_hash($password, PASSWORD_DEFAULT), $mustChange ? 1 : 0, $userId]
        );
    }

    /** A readable one-time password (no look-alike characters like 0/O or 1/l). */
    public static function tempPassword(int $length = 10): string
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return $out;
    }
}
