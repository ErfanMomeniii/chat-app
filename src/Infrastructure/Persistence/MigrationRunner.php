<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use PDO;
use RuntimeException;

/**
 * Sequential SQL migration runner for SQLite.
 *
 * Tracks applied migrations in a `schema_migrations` table.
 * Migrations are plain .sql files named with a numeric prefix
 * (e.g. 001_create_groups.sql) and executed in sorted order.
 *
 * Each migration runs inside a transaction. If a migration fails,
 * the transaction rolls back and no partial state is recorded.
 *
 * SQLite constraints respected:
 * - No ALTER TABLE DROP COLUMN before 3.35.0
 * - For destructive schema changes, use the create-copy-drop-rename pattern
 * - PRAGMA foreign_keys must be OFF during table renames (handled per migration)
 */
final readonly class MigrationRunner
{
    public function __construct(
        private PDO    $pdo,
        private string $migrationsPath,
    ) {
    }

    /**
     * Run all pending migrations in order.
     *
     * @return string[] List of migration filenames that were applied
     */
    public function migrate(): array
    {
        $this->ensureMigrationsTable();

        $applied = $this->getAppliedMigrations();
        $pending = $this->getPendingMigrations($applied);
        $ran = [];

        foreach ($pending as $filename) {
            $this->runMigration($filename);
            $ran[] = $filename;
        }

        return $ran;
    }

    /**
     * Get list of all applied migration filenames.
     *
     * @return string[]
     */
    public function getAppliedMigrations(): array
    {
        $this->ensureMigrationsTable();

        $stmt = $this->pdo->query(
            'SELECT filename FROM schema_migrations ORDER BY filename'
        );

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function ensureMigrationsTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                filename TEXT PRIMARY KEY,
                applied_at TEXT NOT NULL DEFAULT (strftime(\'%Y-%m-%dT%H:%M:%SZ\', \'now\'))
            )'
        );
    }

    /**
     * @param string[] $applied
     * @return string[]
     */
    private function getPendingMigrations(array $applied): array
    {
        $files = glob($this->migrationsPath . '/*.sql');
        if ($files === false) {
            return [];
        }

        $filenames = array_map('basename', $files);
        sort($filenames);

        return array_values(
            array_diff($filenames, $applied)
        );
    }

    private function runMigration(string $filename): void
    {
        $path = $this->migrationsPath . '/' . $filename;
        $sql = file_get_contents($path);

        if ($sql === false) {
            throw new RuntimeException(
                "Cannot read migration file: {$filename}"
            );
        }

        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec($sql);

            $stmt = $this->pdo->prepare(
                'INSERT INTO schema_migrations (filename) VALUES (:filename)'
            );
            $stmt->execute(['filename' => $filename]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw new RuntimeException(
                "Migration {$filename} failed: {$e->getMessage()}",
                0,
                $e
            );
        }
    }
}
