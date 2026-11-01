<?php
declare(strict_types=1);

namespace App;

/** Account management for the curator: create, edit, import, reset passwords, deactivate. */
final class Users
{
    public static function find(int $id): ?array
    {
        $u = Db::one(
            'SELECT u.id, u.iin, u.full_name, u.role, u.lang, u.is_active, c.name AS class_name, t.room
               FROM users u
               LEFT JOIN students s ON s.user_id = u.id LEFT JOIN classes c ON c.id = s.class_id
               LEFT JOIN teachers t ON t.user_id = u.id
              WHERE u.id = ?',
            [$id]
        );
        if (!$u) {
            return null;
        }
        $u['subject_ids'] = array_map('intval', array_column(Db::all('SELECT subject_id FROM teacher_subjects WHERE teacher_id = ?', [$id]), 'subject_id'));
        $u['child_ids'] = array_map('intval', array_column(Db::all('SELECT student_id FROM parent_student WHERE parent_id = ?', [$id]), 'student_id'));
        $u['class_ids'] = array_map('intval', array_column(Db::all('SELECT class_id FROM curator_classes WHERE curator_id = ?', [$id]), 'class_id'));
        return $u;
    }

    /**
     * Create an account with a one-time password. $data keys: iin, full_name, role, class, room, subject_ids[], child_ids[]
     * @return array{id:int, password:string}
     */
    public static function create(array $actor, array $data): array
    {
        if (!Policy::canManageUsers($actor)) {
            throw new RuleException('err.forbidden');
        }
        $iin = trim((string)($data['iin'] ?? ''));
        $name = trim((string)($data['full_name'] ?? ''));
        $role = Role::tryFrom((string)($data['role'] ?? ''));
        if (!preg_match('/^\d{12}$/', $iin)) {
            throw new RuleException('user.err.iin');
        }
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            throw new RuleException('user.err.name');
        }
        if (!$role) {
            throw new RuleException('user.err.role');
        }
        if (!Policy::canCreateUser($actor, $role, Scope::classIdByName((string)($data['class'] ?? '')), array_map('intval', (array)($data['child_ids'] ?? [])))) {
            throw new RuleException('scope.err.create');
        }
        if (Db::val('SELECT 1 FROM users WHERE iin = ?', [$iin])) {
            throw new RuleException('user.err.exists', ['iin' => $iin]);
        }
        $password = Auth::tempPassword();
        $id = (int)Db::transaction(function () use ($actor, $iin, $name, $role, $data, $password): int {
            $id = Db::insert(
                'INSERT INTO users (iin, full_name, password_hash, role, lang, is_active, must_change_password, created_at)
                 VALUES (?, ?, ?, ?, ?, 1, 1, ?)',
                [$iin, $name, password_hash($password, PASSWORD_DEFAULT), $role->value, I18n::current(), Clock::now()->format('Y-m-d H:i:s')]
            );
            self::saveProfile($id, $role, $data);
            if ($role === Role::Curator && Scope::isHead($actor)) {
                self::setCuratorClasses($id, (array)($data['class_ids'] ?? []));
            }
            return $id;
        });
        return ['id' => $id, 'password' => $password];
    }

    /** Edit name / profile. The IIN and role can't change after creation. */
    public static function update(array $actor, int $id, array $data): void
    {
        if (!Policy::canManageUsers($actor)) {
            throw new RuleException('err.forbidden');
        }
        $u = self::find($id);
        if (!$u) {
            throw new RuleException('err.notfound');
        }
        if (!Policy::canManageUser($actor, $u)) {
            throw new RuleException('err.forbidden');
        }
        $name = trim((string)($data['full_name'] ?? ''));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            throw new RuleException('user.err.name');
        }
        $role = Role::from($u['role']);
        // A scoped curator can't move a student to a class (or link a parent to a child) outside their own classes.
        if (!Policy::canCreateUser($actor, $role, Scope::classIdByName((string)($data['class'] ?? '')), array_map('intval', (array)($data['child_ids'] ?? [])))) {
            throw new RuleException('scope.err.create');
        }
        Db::transaction(function () use ($actor, $id, $name, $role, $data): void {
            Db::run('UPDATE users SET full_name = ? WHERE id = ?', [$name, $id]);
            self::saveProfile($id, $role, $data);
            if ($role === Role::Curator && Scope::isHead($actor)) {
                self::setCuratorClasses($id, (array)($data['class_ids'] ?? []));
                Scope::assertHeadExists();
            }
        });
    }

    /** Replace the classes a curator looks after (empty list = head curator, all classes). */
    private static function setCuratorClasses(int $curatorId, array $classIds): void
    {
        Db::run('DELETE FROM curator_classes WHERE curator_id = ?', [$curatorId]);
        foreach (array_unique(array_map('intval', $classIds)) as $cid) {
            if (Db::val('SELECT 1 FROM classes WHERE id = ?', [$cid])) {
                Db::run('INSERT INTO curator_classes (curator_id, class_id) VALUES (?, ?)', [$curatorId, $cid]);
            }
        }
    }

    public static function saveProfile(int $id, Role $role, array $data): void
    {
        if ($role === Role::Student) {
            $class = trim((string)($data['class'] ?? ''));
            $classId = null;
            if ($class !== '') {
                $class = mb_substr($class, 0, 20);
                $classId = Db::val('SELECT id FROM classes WHERE name = ?', [$class]);
                if (!$classId) {
                    $classId = Db::insert('INSERT INTO classes (name) VALUES (?)', [$class]);
                }
            }
            if (Db::val('SELECT 1 FROM students WHERE user_id = ?', [$id])) {
                Db::run('UPDATE students SET class_id = ? WHERE user_id = ?', [$classId, $id]);
            } else {
                Db::run('INSERT INTO students (user_id, class_id) VALUES (?, ?)', [$id, $classId]);
            }
        } elseif ($role === Role::Teacher) {
            $room = trim((string)($data['room'] ?? ''));
            $room = $room === '' ? null : mb_substr($room, 0, 20);
            if (Db::val('SELECT 1 FROM teachers WHERE user_id = ?', [$id])) {
                Db::run('UPDATE teachers SET room = ? WHERE user_id = ?', [$room, $id]);
            } else {
                Db::run('INSERT INTO teachers (user_id, room) VALUES (?, ?)', [$id, $room]);
            }
            Db::run('DELETE FROM teacher_subjects WHERE teacher_id = ?', [$id]);
            $valid = array_column(Repo::subjects(), 'id');
            foreach (array_unique(array_map('intval', (array)($data['subject_ids'] ?? []))) as $sid) {
                if (in_array($sid, array_map('intval', $valid), true)) {
                    Db::run('INSERT INTO teacher_subjects (teacher_id, subject_id) VALUES (?, ?)', [$id, $sid]);
                }
            }
        } elseif ($role === Role::Parent) {
            Db::run('DELETE FROM parent_student WHERE parent_id = ?', [$id]);
            foreach (array_unique(array_map('intval', (array)($data['child_ids'] ?? []))) as $cid) {
                if (Db::val("SELECT 1 FROM users WHERE id = ? AND role = 'student'", [$cid])) {
                    Db::run('INSERT INTO parent_student (parent_id, student_id) VALUES (?, ?)', [$id, $cid]);
                }
            }
        }
    }

    public static function resetPassword(array $actor, int $id): string
    {
        if (!Policy::canManageUsers($actor)) {
            throw new RuleException('err.forbidden');
        }
        $target = self::find($id);
        if (!$target) {
            throw new RuleException('err.notfound');
        }
        if (!Policy::canManageUser($actor, $target)) {
            throw new RuleException('err.forbidden');
        }
        $password = Auth::tempPassword();
        Auth::setPassword($id, $password, true);
        Db::run('DELETE FROM login_attempts WHERE iin = (SELECT iin FROM users WHERE id = ?)', [$id]);
        return $password;
    }

    public static function setActive(array $actor, int $id, bool $active): void
    {
        if (!Policy::canManageUsers($actor)) {
            throw new RuleException('err.forbidden');
        }
        if ($id === $actor['id']) {
            throw new RuleException('user.err.self');
        }
        $target = self::find($id);
        if (!$target) {
            throw new RuleException('err.notfound');
        }
        if (!Policy::canManageUser($actor, $target)) {
            throw new RuleException('err.forbidden');
        }
        Db::transaction(function () use ($id, $active): void {
            Db::run('UPDATE users SET is_active = ? WHERE id = ?', [$active ? 1 : 0, $id]);
            if (!$active) {
                Scope::assertHeadExists();   // can't switch off the last head curator
            }
        });
    }

    public static function search(string $q, string $role, string $status, int $page, int $per = 25, ?array $viewer = null): array
    {
        $where = [];
        $params = [];
        $scope = $viewer ? Scope::classIds($viewer) : null;
        if ($scope) {   // a scoped curator: their students, and the parents of those students
            $in = Scope::marks($scope);
            $where[] = "((u.role = 'student' AND s.class_id IN ($in))
                      OR (u.role = 'parent' AND EXISTS (SELECT 1 FROM parent_student ps JOIN students s2 ON s2.user_id = ps.student_id
                                                         WHERE ps.parent_id = u.id AND s2.class_id IN ($in))))";
            array_push($params, ...$scope, ...$scope);
        }
        if ($q !== '') {
            $where[] = "(u.full_name LIKE ? ESCAPE '!' OR u.iin LIKE ? ESCAPE '!')";
            $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q) . '%';
            array_push($params, $like, $like);
        }
        if (Role::tryFrom($role)) {
            $where[] = 'u.role = ?';
            $params[] = $role;
        }
        if ($status === 'active' || $status === 'inactive') {
            $where[] = 'u.is_active = ?';
            $params[] = $status === 'active' ? 1 : 0;
        }
        $w = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $total = (int)Db::val('SELECT COUNT(*) FROM users u LEFT JOIN students s ON s.user_id = u.id' . $w, $params);
        $rows = Db::all(
            'SELECT u.id, u.iin, u.full_name, u.role, u.is_active, c.name AS class_name
               FROM users u LEFT JOIN students s ON s.user_id = u.id LEFT JOIN classes c ON c.id = s.class_id' . $w .
            ' ORDER BY u.role, u.full_name LIMIT ' . $per . ' OFFSET ' . (max(0, $page - 1) * $per),
            $params
        );
        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Bulk-create accounts from CSV text. Header row required: iin,full_name,role
     * optional columns: class, room, subjects (slugs separated by |), children (child IINs separated by |)
     * @return array{created: array<int, array{iin:string,name:string,role:string,password:string}>, errors: array<int, string>}
     */
    public static function importCsv(array $actor, string $csv): array
    {
        if (!Policy::canManageUsers($actor)) {
            throw new RuleException('err.forbidden');
        }
        $lines = preg_split('/\r\n|\r|\n/', trim($csv)) ?: [];
        $header = array_map(fn($h) => strtolower(trim((string)$h, " \t\xEF\xBB\xBF\"")), str_getcsv((string)array_shift($lines), ',', '"', ''));
        foreach (['iin', 'full_name', 'role'] as $need) {
            if (!in_array($need, $header, true)) {
                throw new RuleException('import.err.header');
            }
        }
        $slugToId = array_column(Repo::subjects(), 'id', 'slug');
        $created = $errors = [];
        foreach ($lines as $n => $line) {
            if (trim($line) === '') {
                continue;
            }
            $cells = str_getcsv($line, ',', '"', '');
            $row = [];
            foreach ($header as $i => $h) {
                $row[$h] = trim((string)($cells[$i] ?? ''));
            }
            $row['subject_ids'] = [];
            foreach (array_filter(explode('|', (string)($row['subjects'] ?? ''))) as $slug) {
                if (isset($slugToId[trim($slug)])) {
                    $row['subject_ids'][] = (int)$slugToId[trim($slug)];
                }
            }
            $row['child_ids'] = [];
            foreach (array_filter(explode('|', (string)($row['children'] ?? ''))) as $childIin) {
                $cid = Db::val("SELECT id FROM users WHERE iin = ? AND role = 'student'", [trim($childIin)]);
                if ($cid) {
                    $row['child_ids'][] = (int)$cid;
                }
            }
            try {
                $res = self::create($actor, $row);
                $created[] = ['iin' => $row['iin'], 'name' => $row['full_name'], 'role' => $row['role'], 'password' => $res['password']];
            } catch (RuleException $e) {
                $errors[] = t('import.line', ['n' => $n + 2]) . ' ' . t($e->key, $e->params);
            }
        }
        return ['created' => $created, 'errors' => $errors];
    }
}
