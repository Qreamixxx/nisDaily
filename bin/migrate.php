<?php
declare(strict_types=1);

// Creates the database (if needed) and applies any new migrations.
//   XAMPP:  open the XAMPP Shell and run:   php bin/migrate.php
require dirname(__DIR__) . '/bootstrap.php';

$dsn = (string)env('DB_DSN', '');
if ($dsn === '') {
    $host = (string)env('DB_HOST', 'localhost');
    $name = (string)env('DB_NAME', 'nisdaily');
    if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        fwrite(STDERR, "DB_NAME may only contain letters, digits and underscores.\n");
        exit(1);
    }
    $server = App\Db::connect(sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, env('DB_PORT', '3306')), (string)env('DB_USER', 'root'), (string)env('DB_PASS', ''));
    $server->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
}

$applied = App\Migrator::run(App\Db::pdo(), dirname(__DIR__) . '/migrations');
echo $applied ? 'Applied: ' . implode(', ', $applied) . "\n" : "Database is up to date.\n";
