<?php

declare(strict_types=1);

namespace App\Domain\Entity;

final readonly class Group
{
    public function __construct(
        public int $id,
        public string $name,
        public string $description,
        public string $createdBy,
        public string $createdAt,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            name: $row['name'],
            description: $row['description'],
            createdBy: $row['created_by'],
            createdAt: $row['created_at'],
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'created_by' => $this->createdBy,
            'created_at' => $this->createdAt,
        ];
    }
}
