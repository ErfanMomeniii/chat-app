<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Group;
use PDO;

final readonly class GroupRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(string $name, string $description, string $createdBy): Group
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO groups (name, description, created_by) VALUES (:name, :description, :created_by)'
        );
        $stmt->execute([
            'name' => $name,
            'description' => $description,
            'created_by' => $createdBy,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        $row = $this->fetchById($id);

        if ($row === false) {
            throw new \RuntimeException(sprintf(
                'Failed to retrieve group after insert: id=%d, name=%s, created_by=%s',
                $id,
                $name,
                $createdBy
            ));
        }

        return Group::fromRow($row);
    }

    public function findById(int $id): ?Group
    {
        $row = $this->fetchById($id);

        return $row !== false ? Group::fromRow($row) : null;
    }

    /** @return Group[] */
    public function findAll(int $limit = 50, ?int $beforeId = null): array
    {
        $sql = 'SELECT * FROM groups';
        $params = [];

        if ($beforeId !== null) {
            $sql .= ' WHERE id < :before_id';
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
            fn (array $row) => Group::fromRow($row),
            $stmt->fetchAll()
        );
    }

    public function update(int $id, string $name, string $description): Group
    {
        $stmt = $this->pdo->prepare(
            'UPDATE groups SET name = :name, description = :description WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'name' => $name,
            'description' => $description,
        ]);

        $row = $this->fetchById($id);

        if ($row === false) {
            throw new \RuntimeException(sprintf(
                'Failed to retrieve group after update: id=%d',
                $id
            ));
        }

        return Group::fromRow($row);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM groups WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    private function fetchById(int $id): array|false
    {
        $stmt = $this->pdo->prepare('SELECT * FROM groups WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch();
    }
}
