<?php

declare(strict_types=1);

namespace App\Domain\Entity;

final readonly class Member
{
    public function __construct(
        public int $id,
        public int $groupId,
        public string $userId,
        public string $joinedAt,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            groupId: (int) $row['group_id'],
            userId: $row['user_id'],
            joinedAt: $row['joined_at'],
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'group_id' => $this->groupId,
            'user_id' => $this->userId,
            'joined_at' => $this->joinedAt,
        ];
    }
}
