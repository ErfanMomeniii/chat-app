<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\Group;
use App\Domain\Exception\ForbiddenException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Repository\GroupRepository;
use App\Domain\Repository\MemberRepository;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;

final readonly class GroupService
{
    public function __construct(
        private PDO $pdo,
        private GroupRepository $groupRepository,
        private MemberRepository $memberRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function getOrFail(int $id): Group
    {
        return $this->groupRepository->findById($id)
            ?? throw new NotFoundException('Group not found');
    }

    /**
     * Create a group and add the creator as the first member atomically.
     *
     * Uses a transaction to guarantee no orphaned groups exist without
     * their creator as a member. Relies on the UNIQUE constraint to
     * reject duplicate names (no TOCTOU race).
     */
    public function createWithMember(
        string $name,
        string $description,
        string $createdBy,
    ): Group {
        $this->pdo->beginTransaction();
        try {
            $group = $this->groupRepository->create(
                $name,
                $description,
                $createdBy
            );
            $this->memberRepository->add($group->id, $createdBy);
            $this->pdo->commit();

            $this->logger->info('Group created', [
                'group_id' => $group->id,
                'name' => $name,
                'created_by' => $createdBy,
            ]);

            return $group;
        } catch (PDOException $e) {
            $this->pdo->rollBack();

            if ($e->getCode() === '23000') {
                $this->logger->notice('Duplicate group name rejected', [
                    'name' => $name,
                    'attempted_by' => $createdBy,
                ]);
                throw new ValidationException(
                    'A group with this name already exists'
                );
            }

            $this->logger->error('Group creation failed', [
                'name' => $name,
                'created_by' => $createdBy,
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'sqlstate' => $e->getCode(),
            ]);

            throw $e;
        }
    }

    public function update(
        int $id,
        string $name,
        string $description,
        string $userId,
    ): Group {
        $group = $this->getOrFail($id);

        if ($group->createdBy !== $userId) {
            throw new ForbiddenException(
                'Only the group creator can update this group'
            );
        }

        try {
            $updated = $this->groupRepository->update(
                $id,
                $name,
                $description
            );

            $this->logger->info('Group updated', [
                'group_id' => $id,
                'name' => $name,
                'updated_by' => $userId,
            ]);

            return $updated;
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $this->logger->notice('Duplicate group name rejected', [
                    'name' => $name,
                    'attempted_by' => $userId,
                ]);
                throw new ValidationException(
                    'A group with this name already exists'
                );
            }

            throw $e;
        }
    }

    public function delete(int $id, string $userId): void
    {
        $group = $this->getOrFail($id);

        if ($group->createdBy !== $userId) {
            throw new ForbiddenException(
                'Only the group creator can delete this group'
            );
        }

        $this->groupRepository->delete($id);

        $this->logger->info('Group deleted', [
            'group_id' => $id,
            'deleted_by' => $userId,
        ]);
    }
}
