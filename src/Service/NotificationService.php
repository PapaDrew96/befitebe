<?php

declare(strict_types=1);

namespace Befit\Service;

use Befit\Exception\ApiException;
use Befit\Repository\NotificationRepository;

final class NotificationService
{
    public function __construct(private readonly NotificationRepository $notifications) {}

    public function mine(int $userId): array
    {
        $items = array_map(function (array $row): array {
            return [
                'id' => (int)$row['id'],
                'type' => $row['type'],
                'title' => $row['title'],
                'body' => $row['body'],
                'data' => $row['data'] ? json_decode($row['data'], true) : null,
                'read_at' => $row['read_at'],
                'created_at' => $row['created_at'],
            ];
        }, $this->notifications->forUser($userId));

        return ['items' => $items, 'unread_count' => $this->notifications->unreadCount($userId)];
    }

    public function markRead(int $id, int $userId): void
    {
        if (!$this->notifications->markRead($id, $userId)) {
            throw ApiException::notFound('Notification not found.');
        }
    }

    public function markAllRead(int $userId): void
    {
        $this->notifications->markAllRead($userId);
    }
}
