<?php
declare(strict_types=1);

namespace App;

/**
 * Self-registration for students and parents.
 * A request is only a row in registration_requests; a curator approves it and only then an account exists.
 * Teachers and curators are never self-registered: anyone could claim to be one.
 */
final class Registration
{
    public const ROLES = ['student', 'parent'];
    private const MAX_PER_IP_PER_HOUR = 5;

    /**
     * Store a request. If the IIN already has an account or a pending request we still say "received"
     * and store nothing, so this form can't be used to find out who is registered.
     * @throws RuleException when the form is filled in wrongly
     */
    public static function submit(array $d, string $ip): void
    {
        if (trim((string)($d['website'] ?? '')) !== '') {
            return;   // hidden field that only bots fill in
        }
        $iin = trim((string)($d['iin'] ?? ''));
        $name = trim((string)($d['full_name'] ?? ''));
        $role = (string)($d['role'] ?? '');
        $class = trim((string)($d['class'] ?? ''));
        $child = trim((string)($d['child_iin'] ?? ''));
        $pw = (string)($d['password'] ?? '');

        if (!in_array($role, self::ROLES, true)) {
            throw new RuleException('reg.err.role');
        }
        if (!preg_match('/^\d{12}$/', $iin)) {
            throw new RuleException('user.err.iin');
        }
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            throw new RuleException('user.err.name');
        }
        if ($role === 'parent' && (!preg_match('/^\d{12}$/', $child) || $child === $iin)) {
            throw new RuleException('reg.err.child');
        }
        if (mb_strlen($class) > 20) {
            throw new RuleException('reg.err.class');
        }
        if (mb_strlen($pw) < Auth::MIN_PASSWORD) {
            throw new RuleException('reg.err.pw_short', ['min' => Auth::MIN_PASSWORD]);
        }
        if (!hash_equals($pw, (string)($d['password2'] ?? ''))) {
            throw new RuleException('reg.err.pw_match');
        }
        $now = Clock::now();
        $since = $now->modify('-1 hour')->format('Y-m-d H:i:s');
        if ((int)Db::val('SELECT COUNT(*) FROM registration_requests WHERE ip = ? AND created_at >= ?', [$ip, $since]) >= self::MAX_PER_IP_PER_HOUR) {
            throw new RuleException('reg.err.rate');
        }
        if (Db::val('SELECT 1 FROM users WHERE iin = ?', [$iin]) || Db::val('SELECT 1 FROM registration_requests WHERE iin = ?', [$iin])) {
            return;
        }
        try {
            Db::run(
                'INSERT INTO registration_requests (iin, full_name, role, class_name, child_iin, lang, password_hash, ip, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$iin, $name, $role, $role === 'student' && $class !== '' ? $class : null, $role === 'parent' ? $child : null,
                 I18n::current(), password_hash($pw, PASSWORD_DEFAULT), mb_substr($ip, 0, 45), $now->format('Y-m-d H:i:s')]
            );
        } catch (\PDOException) {
            return;   // two identical requests at once: the UNIQUE key kept one
        }
        $classId = self::requestClassId(['role' => $role, 'class_name' => $class, 'child_iin' => $child]);
        foreach (Db::all("SELECT id FROM users WHERE role = 'curator' AND is_active = 1") as $c) {
            $mine = array_map('intval', array_column(Db::all('SELECT class_id FROM curator_classes WHERE curator_id = ?', [$c['id']]), 'class_id'));
            if (!$mine || ($classId !== null && in_array($classId, $mine, true))) {   // head curators, or the curator of that class
                Notify::to((int)$c['id'], 'n.registration', ['name' => $name, 'role' => $role], 'registrations.php');
            }
        }
    }

    /** The class a request belongs to: the student's own class, or the class of the parent's child. */
    private static function requestClassId(array $r): ?int
    {
        if ($r['role'] === 'student') {
            return trim((string)$r['class_name']) === '' ? null : Scope::classIdByName((string)$r['class_name']);
        }
        $child = Db::val("SELECT id FROM users WHERE iin = ? AND role = 'student'", [$r['child_iin']]);
        return $child ? Scope::studentClassId((int)$child) : null;
    }

    /** May this curator deal with this request? Head curators: all. Scoped curators: requests for their own classes. */
    private static function inScope(array $actor, array $r): bool
    {
        return !Scope::isScoped($actor) || Scope::classInScope($actor, self::requestClassId($r));
    }

    public static function pendingCount(?array $actor = null): int
    {
        if ($actor && Scope::isScoped($actor)) {
            return count(self::pending($actor));
        }
        return (int)Db::val('SELECT COUNT(*) FROM registration_requests');
    }

    /** @return array<int, array<string, mixed>> oldest first, with the child's name for parents */
    public static function pending(array $actor): array
    {
        if (!Policy::canManageUsers($actor)) {
            throw new RuleException('err.forbidden');
        }
        $rows = Db::all(
            "SELECT r.id, r.iin, r.full_name, r.role, r.class_name, r.child_iin, r.created_at, cu.full_name AS child_name
               FROM registration_requests r LEFT JOIN users cu ON cu.iin = r.child_iin AND cu.role = 'student'
              ORDER BY r.created_at, r.id"
        );
        return array_values(array_filter($rows, fn($r) => self::inScope($actor, $r)));
    }

    /** Create the real account from a request. @return int the new user id */
    public static function approve(array $actor, int $id): int
    {
        if (!Policy::canManageUsers($actor)) {
            throw new RuleException('err.forbidden');
        }
        return (int)Db::transaction(function () use ($actor, $id): int {
            $r = Db::one('SELECT * FROM registration_requests WHERE id = ?', [$id]);
            if (!$r) {
                throw new RuleException('err.notfound');
            }
            if (!self::inScope($actor, $r)) {
                throw new RuleException('err.forbidden');
            }
            if (Db::val('SELECT 1 FROM users WHERE iin = ?', [$r['iin']])) {
                Db::run('DELETE FROM registration_requests WHERE id = ?', [$id]);
                throw new RuleException('user.err.exists', ['iin' => $r['iin']]);
            }
            $data = ['class' => (string)$r['class_name']];
            if ($r['role'] === 'parent') {
                $childId = Db::val("SELECT id FROM users WHERE iin = ? AND role = 'student'", [$r['child_iin']]);
                if (!$childId) {
                    throw new RuleException('reg.err.child_missing', ['iin' => $r['child_iin']]);
                }
                $data = ['child_ids' => [(int)$childId]];
            }
            $uid = Db::insert(
                'INSERT INTO users (iin, full_name, password_hash, role, lang, is_active, must_change_password, created_at)
                 VALUES (?, ?, ?, ?, ?, 1, 0, ?)',
                [$r['iin'], $r['full_name'], $r['password_hash'], $r['role'], $r['lang'], Clock::now()->format('Y-m-d H:i:s')]
            );
            Users::saveProfile($uid, Role::from($r['role']), $data);
            Db::run('DELETE FROM registration_requests WHERE id = ?', [$id]);
            return $uid;
        });
    }

    public static function reject(array $actor, int $id): void
    {
        if (!Policy::canManageUsers($actor)) {
            throw new RuleException('err.forbidden');
        }
        $r = Db::one('SELECT * FROM registration_requests WHERE id = ?', [$id]);
        if ($r && !self::inScope($actor, $r)) {
            throw new RuleException('err.forbidden');
        }
        Db::run('DELETE FROM registration_requests WHERE id = ?', [$id]);
    }

    /** True when this IIN + password belong to a request that is still waiting (so we can say "wait", not "wrong password"). */
    public static function matchesPending(string $iin, string $password): bool
    {
        $hash = Db::val('SELECT password_hash FROM registration_requests WHERE iin = ?', [$iin]);
        return is_string($hash) && password_verify($password, $hash);
    }
}
