<?php

declare(strict_types=1);

namespace Befit\Repository;

use PDO;

final class NotificationRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function create(int $userId, string $type, string $title, string $body, ?array $data = null): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO notifications (user_id,type,title,body,data) VALUES (:user_id,:type,:title,:body,:data)');
        $stmt->execute([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data === null ? null : json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function forUser(int $userId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM notifications WHERE user_id=:user_id ORDER BY created_at DESC, id DESC LIMIT :limit');
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function markRead(int $id, int $userId): bool
    {
        $stmt = $this->pdo->prepare('UPDATE notifications SET read_at=COALESCE(read_at,CURRENT_TIMESTAMP) WHERE id=:id AND user_id=:user_id');
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        return $stmt->rowCount() > 0;
    }

    public function markAllRead(int $userId): void
    {
        $stmt = $this->pdo->prepare('UPDATE notifications SET read_at=COALESCE(read_at,CURRENT_TIMESTAMP) WHERE user_id=:user_id');
        $stmt->execute(['user_id' => $userId]);
    }

    public function unreadCount(int $userId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=:user_id AND read_at IS NULL');
        $stmt->execute(['user_id' => $userId]);
        return (int)$stmt->fetchColumn();
    }
}
