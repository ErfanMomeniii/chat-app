<?php

declare(strict_types=1);

namespace Tests\Domain\Repository;

use App\Domain\Repository\GroupRepository;
use App\Domain\Repository\MessageRepository;
use App\Infrastructure\Persistence\DatabaseFactory;
use PDO;
use PHPUnit\Framework\TestCase;

final class MessageRepositoryTest extends TestCase
{
    private PDO $pdo;
    private GroupRepository $groupRepository;
    private MessageRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = DatabaseFactory::createAndMigrate(':memory:');
        $this->groupRepository = new GroupRepository($this->pdo);
        $this->repository = new MessageRepository($this->pdo);
    }

    public function testCreateMessage(): void
    {
        $group = $this->groupRepository->create('test', '', 'alice');
        $message = $this->repository->create($group->id, 'alice', 'Hello world');

        $this->assertSame($group->id, $message->groupId);
        $this->assertSame('alice', $message->userId);
        $this->assertSame('Hello world', $message->content);
        $this->assertGreaterThan(0, $message->id);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $message->createdAt);
    }

    public function testCreateMessageFieldTypes(): void
    {
        $group = $this->groupRepository->create('test', '', 'alice');
        $message = $this->repository->create($group->id, 'alice', 'Hello');

        $this->assertIsInt($message->id);
        $this->assertIsInt($message->groupId);
        $this->assertIsString($message->userId);
        $this->assertIsString($message->content);
        $this->assertIsString($message->createdAt);
    }

    public function testCreateMessageAutoIncrementIds(): void
    {
        $group = $this->groupRepository->create('test', '', 'alice');
        $msg1 = $this->repository->create($group->id, 'alice', 'First');
        $msg2 = $this->repository->create($group->id, 'alice', 'Second');

        $this->assertGreaterThan($msg1->id, $msg2->id);
    }

    public function testToArrayReturnsCorrectKeys(): void
    {
        $group = $this->groupRepository->create('test', '', 'alice');
        $message = $this->repository->create($group->id, 'alice', 'Hello');
        $array = $message->toArray();

        $this->assertSame(['id', 'group_id', 'user_id', 'content', 'created_at'], array_keys($array));
    }

    public function testFindByGroupReturnsDescendingOrder(): void
    {
        $group = $this->groupRepository->create('test', '', 'alice');
        $this->repository->create($group->id, 'alice', 'First');
        $this->repository->create($group->id, 'alice', 'Second');
        $this->repository->create($group->id, 'alice', 'Third');

        $messages = $this->repository->findByGroup($group->id);

        $this->assertCount(3, $messages);
        $this->assertSame('Third', $messages[0]->content);
        $this->assertSame('Second', $messages[1]->content);
        $this->assertSame('First', $messages[2]->content);
    }

    public function testFindByGroupWithLimit(): void
    {
        $group = $this->groupRepository->create('test', '', 'alice');
        $this->repository->create($group->id, 'alice', 'First');
        $this->repository->create($group->id, 'alice', 'Second');
        $this->repository->create($group->id, 'alice', 'Third');

        $messages = $this->repository->findByGroup($group->id, limit: 2);
        $this->assertCount(2, $messages);
        $this->assertSame('Third', $messages[0]->content);
        $this->assertSame('Second', $messages[1]->content);
    }

    public function testFindByGroupWithCursorPagination(): void
    {
        $group = $this->groupRepository->create('test', '', 'alice');
        $this->repository->create($group->id, 'alice', 'First');
        $msg2 = $this->repository->create($group->id, 'alice', 'Second');
        $this->repository->create($group->id, 'alice', 'Third');

        $messages = $this->repository->findByGroup($group->id, beforeId: $msg2->id);

        $this->assertCount(1, $messages);
        $this->assertSame('First', $messages[0]->content);
    }

    public function testFindByGroupCursorExcludesExactId(): void
    {
        $group = $this->groupRepository->create('test', '', 'alice');
        $msg1 = $this->repository->create($group->id, 'alice', 'First');
        $this->repository->create($group->id, 'alice', 'Second');

        // before_id should return messages with id STRICTLY less than beforeId
        $messages = $this->repository->findByGroup($group->id, beforeId: $msg1->id);
        $this->assertSame([], $messages);
    }

    public function testFindByGroupReturnsEmptyForNoMessages(): void
    {
        $group = $this->groupRepository->create('test', '', 'alice');

        $messages = $this->repository->findByGroup($group->id);
        $this->assertSame([], $messages);
    }

    public function testFindByGroupIsolatesBetweenGroups(): void
    {
        $groupA = $this->groupRepository->create('group-a', '', 'alice');
        $groupB = $this->groupRepository->create('group-b', '', 'bob');

        $this->repository->create($groupA->id, 'alice', 'Message in A');
        $this->repository->create($groupB->id, 'bob', 'Message in B');

        $messagesA = $this->repository->findByGroup($groupA->id);
        $this->assertCount(1, $messagesA);
        $this->assertSame('Message in A', $messagesA[0]->content);

        $messagesB = $this->repository->findByGroup($groupB->id);
        $this->assertCount(1, $messagesB);
        $this->assertSame('Message in B', $messagesB[0]->content);
    }

    public function testFindByGroupWithLimitAndBeforeId(): void
    {
        $group = $this->groupRepository->create('test', '', 'alice');
        $this->repository->create($group->id, 'alice', 'First');
        $this->repository->create($group->id, 'alice', 'Second');
        $this->repository->create($group->id, 'alice', 'Third');
        $msg4 = $this->repository->create($group->id, 'alice', 'Fourth');

        $messages = $this->repository->findByGroup($group->id, limit: 2, beforeId: $msg4->id);
        $this->assertCount(2, $messages);
        $this->assertSame('Third', $messages[0]->content);
        $this->assertSame('Second', $messages[1]->content);
    }

    public function testFindByGroupWithLargeBeforeIdReturnsAll(): void
    {
        $group = $this->groupRepository->create('test', '', 'alice');
        $this->repository->create($group->id, 'alice', 'First');
        $this->repository->create($group->id, 'alice', 'Second');

        $messages = $this->repository->findByGroup($group->id, beforeId: 99999);
        $this->assertCount(2, $messages);
    }

    public function testCreateMessagePreservesUnicode(): void
    {
        $group = $this->groupRepository->create('test', '', 'alice');
        $message = $this->repository->create($group->id, 'alice', '你好世界 🌍');

        $this->assertSame('你好世界 🌍', $message->content);
    }
}
