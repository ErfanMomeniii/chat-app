<?php

declare(strict_types=1);

namespace Tests\Domain\Repository;

use App\Domain\Repository\GroupRepository;
use App\Infrastructure\Persistence\DatabaseFactory;
use PDO;
use PHPUnit\Framework\TestCase;

final class GroupRepositoryTest extends TestCase
{
    private PDO $pdo;
    private GroupRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = DatabaseFactory::createAndMigrate(':memory:');
        $this->repository = new GroupRepository($this->pdo);
    }

    public function testCreateAndFindById(): void
    {
        $group = $this->repository->create('general', 'General discussion', 'alice');

        $this->assertSame('general', $group->name);
        $this->assertSame('General discussion', $group->description);
        $this->assertSame('alice', $group->createdBy);
        $this->assertGreaterThan(0, $group->id);
        $this->assertNotEmpty($group->createdAt);

        $found = $this->repository->findById($group->id);
        $this->assertNotNull($found);
        $this->assertSame($group->id, $found->id);
        $this->assertSame($group->name, $found->name);
        $this->assertSame($group->description, $found->description);
        $this->assertSame($group->createdBy, $found->createdBy);
        $this->assertSame($group->createdAt, $found->createdAt);
    }

    public function testCreateWithEmptyDescription(): void
    {
        $group = $this->repository->create('test', '', 'alice');

        $this->assertSame('', $group->description);
    }

    public function testFindByIdReturnsNullForMissing(): void
    {
        $this->assertNull($this->repository->findById(999));
    }

    public function testDuplicateNameThrowsPDOException(): void
    {
        $this->repository->create('general', '', 'alice');

        $this->expectException(\PDOException::class);
        $this->repository->create('general', '', 'bob');
    }

    public function testFindAllReturnsAllGroups(): void
    {
        $this->repository->create('group-a', '', 'alice');
        $this->repository->create('group-b', '', 'bob');

        $groups = $this->repository->findAll();
        $this->assertCount(2, $groups);
    }

    public function testFindAllReturnsEmptyWhenNoGroups(): void
    {
        $groups = $this->repository->findAll();
        $this->assertSame([], $groups);
    }

    public function testFindAllReturnsNewestFirst(): void
    {
        $this->pdo->exec(
            "INSERT INTO groups (name, description, created_by, created_at) "
            . "VALUES ('old', '', 'alice', '2024-01-01T00:00:00Z')"
        );
        $this->pdo->exec(
            "INSERT INTO groups (name, description, created_by, created_at) "
            . "VALUES ('new', '', 'bob', '2024-06-01T00:00:00Z')"
        );

        $groups = $this->repository->findAll();
        $this->assertCount(2, $groups);
        $this->assertSame('new', $groups[0]->name);
        $this->assertSame('old', $groups[1]->name);
    }

    public function testCreateAutoGeneratesTimestamp(): void
    {
        $group = $this->repository->create('test', '', 'alice');

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $group->createdAt);
    }

    public function testCreateStoresDifferentCreators(): void
    {
        $groupA = $this->repository->create('group-a', '', 'alice');
        $groupB = $this->repository->create('group-b', '', 'bob');

        $this->assertSame('alice', $groupA->createdBy);
        $this->assertSame('bob', $groupB->createdBy);
    }

    public function testToArrayReturnsCorrectKeys(): void
    {
        $group = $this->repository->create('test', 'A description', 'alice');
        $array = $group->toArray();

        $this->assertSame(['id', 'name', 'description', 'created_by', 'created_at'], array_keys($array));
        $this->assertSame($group->id, $array['id']);
        $this->assertSame('test', $array['name']);
        $this->assertSame('A description', $array['description']);
        $this->assertSame('alice', $array['created_by']);
    }

    public function testAutoIncrementIds(): void
    {
        $a = $this->repository->create('group-a', '', 'alice');
        $b = $this->repository->create('group-b', '', 'alice');

        $this->assertGreaterThan($a->id, $b->id);
    }

    public function testForeignKeyCascadeDeletesMembers(): void
    {
        $group = $this->repository->create('cascade-test', '', 'alice');

        $this->pdo->prepare('INSERT INTO members (group_id, user_id) VALUES (?, ?)')
            ->execute([$group->id, 'alice']);
        $this->pdo->prepare('INSERT INTO messages (group_id, user_id, content) VALUES (?, ?, ?)')
            ->execute([$group->id, 'alice', 'test message']);

        $this->pdo->prepare('DELETE FROM groups WHERE id = ?')->execute([$group->id]);

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM members WHERE group_id = ?');
        $stmt->execute([$group->id]);
        $this->assertSame(0, (int) $stmt->fetchColumn());

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM messages WHERE group_id = ?');
        $stmt->execute([$group->id]);
        $this->assertSame(0, (int) $stmt->fetchColumn());
    }

    public function testDuplicateNameThrowsSqlstate23000(): void
    {
        $this->repository->create('general', '', 'alice');

        try {
            $this->repository->create('general', '', 'bob');
            $this->fail('Expected PDOException');
        } catch (\PDOException $e) {
            $this->assertSame('23000', $e->getCode());
        }
    }

    public function testFindAllWithLimit(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->repository->create("group-$i", '', 'alice');
        }

        $groups = $this->repository->findAll(3);
        $this->assertCount(3, $groups);
        $this->assertSame('group-5', $groups[0]->name);
        $this->assertSame('group-4', $groups[1]->name);
        $this->assertSame('group-3', $groups[2]->name);
    }

    public function testFindAllWithBeforeId(): void
    {
        $ids = [];
        for ($i = 1; $i <= 5; $i++) {
            $group = $this->repository->create("group-$i", '', 'alice');
            $ids[] = $group->id;
        }

        $groups = $this->repository->findAll(50, $ids[2]); // before group-3's id
        $this->assertCount(2, $groups);
        $this->assertSame('group-2', $groups[0]->name);
        $this->assertSame('group-1', $groups[1]->name);
    }

    public function testFindAllWithLimitAndBeforeId(): void
    {
        $ids = [];
        for ($i = 1; $i <= 5; $i++) {
            $group = $this->repository->create("group-$i", '', 'alice');
            $ids[] = $group->id;
        }

        $groups = $this->repository->findAll(1, $ids[3]); // before group-4's id, limit 1
        $this->assertCount(1, $groups);
        $this->assertSame('group-3', $groups[0]->name);
    }

    public function testUpdateGroupNameAndDescription(): void
    {
        $group = $this->repository->create('original', 'Old desc', 'alice');
        $updated = $this->repository->update($group->id, 'renamed', 'New desc');

        $this->assertSame($group->id, $updated->id);
        $this->assertSame('renamed', $updated->name);
        $this->assertSame('New desc', $updated->description);
        $this->assertSame('alice', $updated->createdBy);
        $this->assertSame($group->createdAt, $updated->createdAt);
    }

    public function testUpdateGroupDuplicateNameThrows(): void
    {
        $this->repository->create('taken', '', 'alice');
        $group = $this->repository->create('original', '', 'alice');

        $this->expectException(\PDOException::class);
        $this->repository->update($group->id, 'taken', '');
    }

    public function testDeleteGroupReturnsTrue(): void
    {
        $group = $this->repository->create('doomed', '', 'alice');

        $this->assertTrue($this->repository->delete($group->id));
        $this->assertNull($this->repository->findById($group->id));
    }

    public function testDeleteNonexistentGroupReturnsFalse(): void
    {
        $this->assertFalse($this->repository->delete(999));
    }
}
