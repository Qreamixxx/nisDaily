<?php
declare(strict_types=1);

namespace App;

/**
 * Applies migrations/*.sql in order, once each, and remembers which ran (table schema_migrations).
 * To change the database later, add a NEW numbered file. Never edit one that has already run.
 */
final class Migrator
{
    /** @return string[] versions applied in this run */
    public static function run(\PDO $pdo, string $dir): array
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(100) PRIMARY KEY, applied_at DATETIME NOT NULL)');
        $done = $pdo->query('SELECT version FROM schema_migrations')->fetchAll(\PDO::FETCH_COLUMN);

        $files = glob($dir . '/*.sql') ?: [];
        sort($files);
        $applied = [];
        foreach ($files as $file) {
            $version = basename($file, '.sql');
            if (in_array($version, $done, true)) {
                continue;
            }
            foreach (self::statements((string)file_get_contents($file), $driver) as $stmt) {
                $pdo->exec($stmt);
            }
            $pdo->prepare('INSERT INTO schema_migrations (version, applied_at) VALUES (?, ?)')
                ->execute([$version, date('Y-m-d H:i:s')]);
            $applied[] = $version;
        }
        return $applied;
    }

    /** Split a .sql file into statements, adapting MySQL syntax for SQLite (used by the tests). */
    public static function statements(string $sql, string $driver): array
    {
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
        if ($driver === 'sqlite') {
            $sql = str_replace('INT AUTO_INCREMENT PRIMARY KEY', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
            $sql = str_replace(' ON UPDATE CURRENT_TIMESTAMP', '', $sql);
        }
        $out = [];
        foreach (explode(';', $sql) as $stmt) {
            $stmt = trim($stmt);
            if ($stmt !== '') {
                $out[] = $stmt;
            }
        }
        return $out;
    }
}
