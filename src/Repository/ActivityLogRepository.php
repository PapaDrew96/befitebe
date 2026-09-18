<?php

declare(strict_types=1);

namespace Befit\Repository;

use PDO;

final class ActivityLogRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function log(
        ?int $actorUserId,
        string $action,
        string $entityType,
        ?int $entityId,
        array $metadata = []
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO activity_logs (actor_user_id, action, entity_type, entity_id, metadata)
             VALUES (:actor_user_id, :action, :entity_type, :entity_id, :metadata)'
        );
        $stmt->execute([
            'actor_user_id' => $actorUserId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata' => $metadata === []
                ? null
                : json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);
    }
}
