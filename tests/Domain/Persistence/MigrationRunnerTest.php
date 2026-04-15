<?php

declare(strict_types=1);

namespace Tests\Domain\Persistence;

use App\Infrastructure\Persistence\DatabaseFactory;
use App\Infrastructure\Persistence\MigrationRunner;
use PDO;
use PHPUnit\Framework\TestCase;

final class MigrationRunnerTest extends TestCase
{
    private PDO $pdo;
    private string $migrationsPath;

    protected function setUp(): void
    {
        $this->pdo = DatabaseFactory::create(':memory:');
        $this->migrationsPath = dirname(__DIR__, 3)
            . '/database/migrations';
    }

    public function testMigrateRunsAllMigrations(): void
    {
        $runner = new MigrationRunner($this->pdo, $this->migrationsPath);
        $ran = $runner->migrate();

        $this->assertNotEmpty($ran);
        $this->assertContains('001_create_groups.sql', $ran);
        $this->assertContains('002_create_members.sql', $ran);
        $this->assertContains('003_create_messages.sql', $ran);
    }

    public function testMigrateIsIdempotent(): void
    {
        $runner = new MigrationRunner($this->pdo, $this->migrationsPath);
        $first = $runner->migrate();
        $second = $runner->migrate();

        $this->assertNotEmpty($first);
        $this->assertSame([], $second);
    }

    public function testSchemaIsFullyFunctionalAfterMigrations(): void
    {
        $runner = new MigrationRunner($this->pdo, $this->migrationsPath);
        $runner->migrate();

        // Insert a group
        $this->pdo->exec(
            "INSERT INTO groups (name, created_by) "
            . "VALUES ('test', 'alice')"
        );
        $groupId = (int) $this->pdo->lastInsertId();
        $this->assertGreaterThan(0, $groupId);

        // Insert a member
        $this->pdo->exec(
            "INSERT INTO members (group_id, user_id) "
            . "VALUES ({$groupId}, 'alice')"
        );

        // Insert a message
        $this->pdo->exec(
            "INSERT INTO messages (group_id, user_id, content) "
            . "VALUES ({$groupId}, 'alice', 'hello')"
        );

        // Verify foreign key cascade
        $this->pdo->exec(
            "DELETE FROM groups WHERE id = {$groupId}"
        );
        $count = $this->pdo->query(
            'SELECT COUNT(*) FROM members'
        )->fetchColumn();
        $this->assertSame(0, (int) $count);
    }

    public function testGetAppliedMigrationsTracksState(): void
    {
        $runner = new MigrationRunner($this->pdo, $this->migrationsPath);

        $this->assertSame([], $runner->getAppliedMigrations());

        $runner->migrate();
        $applied = $runner->getAppliedMigrations();

        $this->assertContains('001_create_groups.sql', $applied);
        $this->assertContains('002_create_members.sql', $applied);
        $this->assertContains('003_create_messages.sql', $applied);
    }

    public function testMigrationOrderIsSequential(): void
    {
        $runner = new MigrationRunner($this->pdo, $this->migrationsPath);
        $ran = $runner->migrate();

        // Migrations must run in sorted filename order
        $sorted = $ran;
        sort($sorted);
        $this->assertSame($sorted, $ran);
    }
}
