<?php

declare(strict_types=1);

namespace Tests\Domain\Service;

use App\Domain\Entity\Group;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Repository\GroupRepository;
use App\Domain\Repository\MemberRepository;
use App\Domain\Service\GroupService;
use App\Infrastructure\Persistence\DatabaseFactory;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class GroupServiceTest extends TestCase
{
    private PDO $pdo;
    private GroupService $service;
    private GroupRepository $groupRepository;
    private MemberRepository $memberRepository;

    protected function setUp(): void
    {
        $this->pdo = DatabaseFactory::createAndMigrate(':memory:');
        $this->groupRepository = new GroupRepository($this->pdo);
        $this->memberRepository = new MemberRepository($this->pdo);
        $this->service = new GroupService(
            $this->pdo,
            $this->groupRepository,
            $this->memberRepository,
            new NullLogger(),
        );
    }

    public function testGetOrFailReturnsExistingGroup(): void
    {
        $group = $this->groupRepository->create(
            'general',
            'A description',
            'alice'
        );

        $found = $this->service->getOrFail($group->id);

        $this->assertSame($group->id, $found->id);
        $this->assertSame('general', $found->name);
        $this->assertSame('A description', $found->description);
        $this->assertSame('alice', $found->createdBy);
    }

    public function testGetOrFailThrowsForMissingGroup(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Group not found');

        $this->service->getOrFail(999);
    }

    public function testGetOrFailThrowsForZeroId(): void
    {
        $this->expectException(NotFoundException::class);

        $this->service->getOrFail(0);
    }

    public function testGetOrFailThrowsForNegativeId(): void
    {
        $this->expectException(NotFoundException::class);

        $this->service->getOrFail(-1);
    }

    public function testCreateWithMemberCreatesGroupAndAddsCreator(): void
    {
        $group = $this->service->createWithMember(
            'engineering',
            'Engineering team',
            'alice'
        );

        $this->assertInstanceOf(Group::class, $group);
        $this->assertSame('engineering', $group->name);
        $this->assertSame('Engineering team', $group->description);
        $this->assertSame('alice', $group->createdBy);

        // Creator is a member
        $this->assertTrue(
            $this->memberRepository->isMember($group->id, 'alice')
        );
    }

    public function testCreateWithMemberIsAtomic(): void
    {
        $this->service->createWithMember('original', '', 'alice');

        // Duplicate name should fail and not leave partial state
        try {
            $this->service->createWithMember('original', '', 'bob');
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('already exists', $e->getMessage());
        }

        // Only one group exists
        $groups = $this->groupRepository->findAll();
        $this->assertCount(1, $groups);
        $this->assertSame('original', $groups[0]->name);
    }

    public function testCreateWithMemberDuplicateNameThrowsValidation(): void
    {
        $this->service->createWithMember('taken', '', 'alice');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('already exists');

        $this->service->createWithMember('taken', '', 'bob');
    }
}
