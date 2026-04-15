<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use PDO;

final class DatabaseFactory
{
    public static function create(string $path): PDO
    {
        $dir = dirname($path);
        if ($path !== ':memory:' && !is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $pdo = new PDO('sqlite:' . $path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(
            PDO::ATTR_DEFAULT_FETCH_MODE,
            PDO::FETCH_ASSOC
        );
        $pdo->exec('PRAGMA journal_mode=WAL');
        $pdo->exec('PRAGMA foreign_keys=ON');

        return $pdo;
    }

    /**
     * Create a PDO connection and run all pending migrations.
     *
     * Replaces the old createWithSchema() that loaded raw SQL.
     * Now uses versioned migration files tracked in schema_migrations.
     */
    public static function createAndMigrate(string $path): PDO
    {
        $pdo = self::create($path);

        $migrationsPath = dirname(__DIR__, 3) . '/database/migrations';
        $runner = new MigrationRunner($pdo, $migrationsPath);
        $runner->migrate();

        return $pdo;
    }
}
