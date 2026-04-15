<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Member;
use PDO;

final readonly class MemberRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function add(int $groupId, string $userId): Member
    {
        $stmt = $this->pdo->prepare(
            'INSERT OR IGNORE INTO members (group_id, user_id) VALUES (:group_id, :user_id)'
        );
        $stmt->execute([
            'group_id' => $groupId,
            'user_id' => $userId,
        ]);

        $member = $this->find($groupId, $userId);

        if ($member === null) {
            throw new \RuntimeException(sprintf(
                'Failed to retrieve member after insert: group_id=%d, user_id=%s',
                $groupId,
                $userId
            ));
        }

        return $member;
    }

    public function remove(int $groupId, string $userId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM members WHERE group_id = :group_id AND user_id = :user_id'
        );
        $stmt->execute([
            'group_id' => $groupId,
            'user_id' => $userId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function isMember(int $groupId, string $userId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM members WHERE group_id = :group_id AND user_id = :user_id'
        );
        $stmt->execute([
            'group_id' => $groupId,
            'user_id' => $userId,
        ]);

        return $stmt->fetch() !== false;
    }

    public function find(int $groupId, string $userId): ?Member
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM members WHERE group_id = :group_id AND user_id = :user_id'
        );
        $stmt->execute([
            'group_id' => $groupId,
            'user_id' => $userId,
        ]);
        $row = $stmt->fetch();

        return $row ? Member::fromRow($row) : null;
    }

    /** @return Member[] */
    public function findByGroup(int $groupId, int $limit = 50, ?int $beforeId = null): array
    {
        $sql = 'SELECT * FROM members WHERE group_id = :group_id';
        $params = ['group_id' => $groupId];

        if ($beforeId !== null) {
            $sql .= ' AND id < :before_id';
            $params['before_id'] = $beforeId;
        }

        $sql .= ' ORDER BY id DESC LIMIT :limit';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(
            fn (array $row) => Member::fromRow($row),
            $stmt->fetchAll()
        );
    }
}
