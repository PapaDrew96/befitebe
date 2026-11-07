<?php

declare(strict_types=1);

namespace Befit\Service;

use Befit\Database\Database;
use Befit\Repository\NotificationRepository;

final class NotificationDispatcher
{
    public function __construct(
        private readonly Database $database,
        private readonly NotificationRepository $notifications,
        private readonly PushNotificationService $push
    ) {}

    public function create(
        int $userId,
        string $type,
        string $title,
        string $body,
        ?array $data = null
    ): int {
        $id = $this->notifications->create(
            $userId,
            $type,
            $title,
            $body,
            $data
        );

        $pushData = $data ?? [];
        $pushData['notification_id'] = $id;
        $pushData['type'] = $type;

        $this->database->afterCommit(function () use (
            $userId,
            $title,
            $body,
            $pushData
        ): void {
            $this->push->sendToUser(
                $userId,
                $title,
                $body,
                $pushData
            );
        });

        return $id;
    }

    public function deleteForUser(int $userId): void
    {
        $this->notifications->deleteForUser($userId);
    }
}
