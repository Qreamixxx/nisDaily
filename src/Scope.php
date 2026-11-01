<?php
declare(strict_types=1);

namespace App;

/**
 * Which students a curator may manage.
 * A curator assigned to some classes is "scoped" to them; a curator with no classes is the "head curator" (everything).
 * Everyone who is not a curator is unaffected (their own rules live in Policy).
 */
final class Scope
{
    /** Class ids a scoped curator is limited to; null means no limit. */
    public static function classIds(array $u): ?array
    {
        return $u['role'] === Role::Curator && !empty($u['class_ids']) ? array_map('intval', $u['class_ids']) : null;
    }

    public static function isScoped(array $u): bool
    {
        return self::classIds($u) !== null;
    }

    /** A curator with no class assignments: sees everything and manages classes, lesson times and other curators. */
    public static function isHead(array $u): bool
    {
        return $u['role'] === Role::Curator && empty($u['class_ids']);
    }

    public static function studentClassId(int $studentId): ?int
    {
        $c = Db::val('SELECT class_id FROM students WHERE user_id = ?', [$studentId]);
        return $c === null || $c === false ? null : (int)$c;
    }

    public static function classInScope(array $u, ?int $classId): bool
    {
        $ids = self::classIds($u);
        return $ids === null || ($classId !== null && in_array($classId, $ids, true));
    }

    public static function studentInScope(array $u, int $studentId): bool
    {
        return self::classIds($u) === null || self::classInScope($u, self::studentClassId($studentId));
    }

    public static function classIdByName(string $name): ?int
    {
        $id = Db::val('SELECT id FROM classes WHERE name = ?', [trim($name)]);
        return $id ? (int)$id : null;
    }

    /** "?, ?, ?" for an IN (...) list. */
    public static function marks(array $ids): string
    {
        return implode(',', array_fill(0, count($ids), '?'));
    }

    /** Names of the classes a curator looks after (empty = all). */
    public static function classNames(array $u): array
    {
        $ids = self::classIds($u);
        if ($ids === null) {
            return [];
        }
        return array_column(Db::all('SELECT name FROM classes WHERE id IN (' . self::marks($ids) . ') ORDER BY name', $ids), 'name');
    }

    /** Make sure at least one active head curator exists, so nobody can lock the school out of admin pages. */
    public static function assertHeadExists(): void
    {
        $n = (int)Db::val(
            "SELECT COUNT(*) FROM users u WHERE u.role = 'curator' AND u.is_active = 1
                AND NOT EXISTS (SELECT 1 FROM curator_classes cc WHERE cc.curator_id = u.id)"
        );
        if ($n < 1) {
            throw new RuleException('scope.err.last_head');
        }
    }
}
