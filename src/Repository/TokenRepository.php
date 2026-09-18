<?php

declare(strict_types=1);

namespace Befit\Repository;

use PDO;

final class TokenRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(int $userId, string $tokenHash, string $name, string $expiresAt): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO api_tokens (user_id, token_hash, name, expires_at)
             VALUES (:user_id, :token_hash, :name, :expires_at)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'name' => $name,
            'expires_at' => $expiresAt,
        ]);
    }

    public function findValidUserByHash(string $tokenHash, string $now): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.*, t.id AS token_id, t.token_hash, t.expires_at AS token_expires_at
             FROM api_tokens t
             INNER JOIN users u ON u.id = t.user_id
             WHERE t.token_hash = :token_hash AND t.expires_at > :now
             LIMIT 1'
        );
        $stmt->execute(['token_hash' => $tokenHash, 'now' => $now]);
        return $stmt->fetch() ?: null;
    }

    public function touch(string $tokenHash, string $dateTime): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE api_tokens SET last_used_at = :last_used_at WHERE token_hash = :token_hash'
        );
        $stmt->execute(['last_used_at' => $dateTime, 'token_hash' => $tokenHash]);
    }

    public function deleteByHash(string $tokenHash): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM api_tokens WHERE token_hash = :token_hash');
        $stmt->execute(['token_hash' => $tokenHash]);
    }

    public function deleteForUser(int $userId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM api_tokens WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
    }
}
