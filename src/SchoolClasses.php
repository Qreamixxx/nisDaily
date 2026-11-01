<?php
declare(strict_types=1);

namespace App;

/** Create and remove classes (10A, 12B ...). Head curator only. */
final class SchoolClasses
{
    public static function add(array $actor, string $name): int
    {
        if (!Policy::canAdminister($actor)) {
            throw new RuleException('err.forbidden');
        }
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 20 || preg_match('/[<>]/', $name)) {
            throw new RuleException('classes.err.name');
        }
        if (Db::val('SELECT 1 FROM classes WHERE name = ?', [$name])) {
            throw new RuleException('classes.err.exists', ['name' => $name]);
        }
        return Db::insert('INSERT INTO classes (name) VALUES (?)', [$name]);
    }

    /** Only an empty class can be removed, so no student is left without a class by accident. */
    public static function delete(array $actor, int $id): void
    {
        if (!Policy::canAdminister($actor)) {
            throw new RuleException('err.forbidden');
        }
        if (Db::val('SELECT 1 FROM students WHERE class_id = ?', [$id])) {
            throw new RuleException('classes.err.not_empty');
        }
        Db::run('DELETE FROM classes WHERE id = ?', [$id]);
    }
}
