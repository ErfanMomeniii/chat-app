<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Message;
use PDO;

final readonly class MessageRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(int $groupId, string $userId, string $content): Message
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO messages (group_id, user_id, content) VALUES (:group_id, :user_id, :content)'
        );
        $stmt->execute([
            'group_id' => $groupId,
            'user_id' => $userId,
            'content' => $content,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        $stmt = $this->pdo->prepare('SELECT * FROM messages WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if ($row === false) {
            throw new \RuntimeException(sprintf(
                'Failed to retrieve message after insert: id=%d, group_id=%d, user_id=%s',
                $id,
                $groupId,
                $userId
            ));
        }

        return Message::fromRow($row);
    }

    /** @return Message[] */
    public function findByGroup(int $groupId, int $limit = 50, ?int $beforeId = null): array
    {
        $sql = 'SELECT * FROM messages WHERE group_id = :group_id';
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
            fn (array $row) => Message::fromRow($row),
            $stmt->fetchAll()
        );
    }
}
