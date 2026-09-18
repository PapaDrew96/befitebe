<?php

declare(strict_types=1);

namespace Befit\Repository;

use PDO;

final class PasswordResetRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function deleteForUser(int $userId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id=:user_id');
        $stmt->execute(['user_id' => $userId]);
    }

    public function create(int $userId, string $tokenHash, string $expiresAt): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO password_reset_tokens (user_id,token_hash,expires_at) VALUES (:user_id,:token_hash,:expires_at)');
        $stmt->execute(['user_id'=>$userId,'token_hash'=>$tokenHash,'expires_at'=>$expiresAt]);
    }

    public function findValidForUpdate(string $tokenHash, string $now): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM password_reset_tokens WHERE token_hash=:token_hash AND used_at IS NULL AND expires_at>:now LIMIT 1 FOR UPDATE');
        $stmt->execute(['token_hash'=>$tokenHash,'now'=>$now]);
        return $stmt->fetch() ?: null;
    }

    public function markUsed(int $id, string $usedAt): void
    {
        $stmt = $this->pdo->prepare('UPDATE password_reset_tokens SET used_at=:used_at WHERE id=:id');
        $stmt->execute(['used_at'=>$usedAt,'id'=>$id]);
    }
}
