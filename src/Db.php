<?php
declare(strict_types=1);

namespace App;

/** Thin wrapper around PDO. All SQL elsewhere uses prepared statements through these helpers. */
final class Db
{
    private static ?\PDO $pdo = null;

    public static function pdo(): \PDO
    {
        if (self::$pdo === null) {
            $dsn = (string)env('DB_DSN', '');
            if ($dsn === '') {
                $dsn = sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                    env('DB_HOST', 'localhost'),
                    env('DB_PORT', '3306'),
                    env('DB_NAME', 'nisdaily')
                );
            }
            self::$pdo = self::connect($dsn, (string)env('DB_USER', 'root'), (string)env('DB_PASS', ''));
        }
        return self::$pdo;
    }

    public static function connect(string $dsn, ?string $user = null, ?string $pass = null): \PDO
    {
        $pdo = new \PDO($dsn, $user, $pass, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        if ($pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $pdo->exec('PRAGMA foreign_keys = ON');
        }
        return $pdo;
    }

    public static function setPdo(?\PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    public static function driver(): string
    {
        return self::pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);
    }

    /** Row-lock clause for MySQL; SQLite locks the whole database in a transaction anyway. */
    public static function forUpdate(): string
    {
        return self::driver() === 'mysql' ? ' FOR UPDATE' : '';
    }

    public static function all(string $sql, array $params = []): array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        $row = $st->fetch();
        return $row === false ? null : $row;
    }

    public static function val(string $sql, array $params = []): mixed
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        $v = $st->fetchColumn();
        return $v === false ? null : $v;
    }

    public static function run(string $sql, array $params = []): int
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->rowCount();
    }

    public static function insert(string $sql, array $params = []): int
    {
        self::run($sql, $params);
        return (int)self::pdo()->lastInsertId();
    }

    /** Run $fn in a transaction: all of it happens, or none of it. */
    public static function transaction(callable $fn): mixed
    {
        $pdo = self::pdo();
        if ($pdo->inTransaction()) {
            return $fn();
        }
        $pdo->beginTransaction();
        try {
            $result = $fn();
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
