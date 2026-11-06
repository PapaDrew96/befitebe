<?php

declare(strict_types=1);

namespace Befit\Repository;

use PDO;

final class PushDeviceRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function upsert(int $userId, string $token, string $platform): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO push_devices (user_id,token,platform)
             VALUES (:user_id,:token,:platform)
             ON DUPLICATE KEY UPDATE
                user_id=VALUES(user_id),
                platform=VALUES(platform),
                updated_at=CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            'user_id' => $userId,
            'token' => $token,
            'platform' => $platform,
        ]);
    }

    public function deleteForUserAndToken(int $userId, string $token): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM push_devices WHERE user_id=:user_id AND token=:token'
        );
        $stmt->execute([
            'user_id' => $userId,
            'token' => $token,
        ]);
    }

    public function deleteForUser(int $userId): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM push_devices WHERE user_id=:user_id'
        );
        $stmt->execute(['user_id' => $userId]);
    }

    public function deleteByToken(string $token): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM push_devices WHERE token=:token'
        );
        $stmt->execute(['token' => $token]);
    }

    public function forUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id,user_id,token,platform,created_at,updated_at
             FROM push_devices
             WHERE user_id=:user_id
             ORDER BY updated_at DESC,id DESC'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }
}
