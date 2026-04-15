<?php

declare(strict_types=1);

namespace Tests\Domain\Repository;

use App\Domain\Repository\GroupRepository;
use App\Domain\Repository\MemberRepository;
use App\Infrastructure\Persistence\DatabaseFactory;
use PDO;
use PHPUnit\Framework\TestCase;

final class MemberRepositoryTest extends TestCase
{
    private PDO $pdo;
    private GroupRepository $groupRepository;
    private MemberRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = DatabaseFactory::createAndMigrate(':memory:');
        $this->groupRepository = new GroupRepository($this->pdo);
        $this->repository = new MemberRepository($this->pdo);
    }

    public function testAddAndCheckMembership(): void
    {
        $group = $this->groupRepository->create('test', '', 'alice');
        $member = $this->repository->add($group->id, 'bob');

        $this->assertSame($group->id, $member->groupId);
        $this->assertSame('bob', $member->userId);
        $this->assertGreaterThan(0, $member->id);
        $this->assertTrue($this->repository->isMember($group->id, 'bob'));
    }

    public function testAddReturnsCorrectFieldTypes(): void
    {
        $group = $this->groupRepository->create('test', '', 'alice');
        $member = $this->repository->add($group->id, 'bob');

        $this->assertIsInt($member->id);
        $this->assertIsInt($member->groupId);
        $this->assertIsString($member->userId);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $member->joinedAt);
    }

    public function testIsNotMember(): void
    {
        $group = $this->groupRepository->create('test', '', 'alice');

        $this->assertFalse($this->repository->isMember($group->id, 'stranger'));
    }

    public function testIsMemberForNonexistentGroup(): void
    {
        $this->assertFalse($this->repository->isMember(999, 'alice'));
    }

    public function testAddDuplicateIsIdempotent(): void
    {
        $group = $this->groupRepository->create('test', '', 'alice');

        $first = $this->repository->add($group->id, 'bob');
        $second = $this->repository->add($group->id, 'bob');

        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->userId, $second->userId);
        $this->assertSame($first->groupId, $second->groupId);
        $this->assertSame($first->joinedAt, $second->joinedAt);

        $members = $this->repository->findByGroup($group->id);
        $this->assertCount(1, $members);
    }

    public function testRemoveMember(): void
    {
        $group = $this->groupRepository->create('test', '', 'alice');
        $this->repository->add($group->id, 'bob');

        $this->assertTrue($this->repository->remove($group->id, 'bob'));
        $this->assertFalse($this->repository->isMember($group->id, 'bob'));
    }

    public function testRemoveNonMemberReturnsFalse(): void
    {
        $group = $this->groupRepository->create('test', '', 'alice');

        $this->assertFalse($this->repository->remove($group->id, 'nobody'));
    }

    public function testFindByGroup(): void
    {
        $group = $this->groupRepository->create('test', '', 'alice');
        $this->repository->add($group->id, 'alice');
        $this->repository->add($group->id, 'bob');

        $members = $this->repository->findByGroup($group->id);
        $this->assertCount(2, $members);

        $userIds = array_column(
            array_map(fn ($m) => $m->toArray(), $members),
            'user_id',
        );
        $this->assertContains('alice', $userIds);
        $this->assertContains('bob', $userIds);
    }

    public function testFindByGroupReturnsEmptyForNoMembers(): void
    {
        $group = $this->groupRepository->create('test', '', 'alice');

        $this->assertSame([], $this->repository->findByGroup($group->id));
    }

    public function testFindByGroupReturnsNewestFirst(): void
    {
        $group = $this->groupRepository->create('test', '', 'alice');

        $this->repository->add($group->id, 'first');
        $this->repository->add($group->id, 'second');

        $members = $this->repository->findByGroup($group->id);
        // ORDER BY id DESC: second added has higher id
        $this->assertSame('second', $members[0]->userId);
        $this->assertSame('first', $members[1]->userId);
    }

    public function testFindReturnsNullForMissing(): void
    {
        $group = $this->groupRepository->create('test', '', 'alice');

        $this->assertNull($this->repository->find($group->id, 'nobody'));
    }

    public function testFindReturnsExistingMember(): void
    {
        $group = $this->groupRepository->create('test', '', 'alice');
        $this->repository->add($group->id, 'bob');

        $member = $this->repository->find($group->id, 'bob');
        $this->assertNotNull($member);
        $this->assertSame('bob', $member->userId);
        $this->assertSame($group->id, $member->groupId);
    }

    public function testToArrayReturnsCorrectKeys(): void
    {
        $group = $this->groupRepository->create('test', '', 'alice');
        $member = $this->repository->add($group->id, 'bob');
        $array = $member->toArray();

        $this->assertSame(['id', 'group_id', 'user_id', 'joined_at'], array_keys($array));
    }

    public function testAddAndRemoveThenReAdd(): void
    {
        $group = $this->groupRepository->create('test', '', 'alice');
        $this->repository->add($group->id, 'bob');
        $this->repository->remove($group->id, 'bob');

        $member = $this->repository->add($group->id, 'bob');
        $this->assertTrue($this->repository->isMember($group->id, 'bob'));
        $this->assertSame('bob', $member->userId);
    }

    public function testFindByGroupWithLimit(): void
    {
        $group = $this->groupRepository->create('test', '', 'alice');
        foreach (['alice', 'bob', 'charlie', 'dave'] as $user) {
            $this->repository->add($group->id, $user);
        }

        $members = $this->repository->findByGroup($group->id, 2);
        $this->assertCount(2, $members);
    }

    public function testFindByGroupWithBeforeId(): void
    {
        $group = $this->groupRepository->create('test', '', 'alice');
        $m1 = $this->repository->add($group->id, 'alice');
        $m2 = $this->repository->add($group->id, 'bob');
        $m3 = $this->repository->add($group->id, 'charlie');

        $members = $this->repository->findByGroup($group->id, 50, $m3->id);
        $this->assertCount(2, $members);
        $this->assertSame('bob', $members[0]->userId);
        $this->assertSame('alice', $members[1]->userId);
    }

    public function testFindByGroupWithLimitAndBeforeId(): void
    {
        $group = $this->groupRepository->create('test', '', 'alice');
        $m1 = $this->repository->add($group->id, 'alice');
        $m2 = $this->repository->add($group->id, 'bob');
        $m3 = $this->repository->add($group->id, 'charlie');
        $m4 = $this->repository->add($group->id, 'dave');

        $members = $this->repository->findByGroup($group->id, 1, $m4->id);
        $this->assertCount(1, $members);
        $this->assertSame('charlie', $members[0]->userId);
    }
}
