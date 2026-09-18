<?php

declare(strict_types=1);

namespace Befit\Repository;

use DateTimeImmutable;
use PDO;
use Throwable;

final class RateLimitRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function consume(string $keyHash, int $limit, int $windowSeconds, DateTimeImmutable $now): array
    {
        $this->pdo->beginTransaction();

        try {
            // Ensure the row exists first. INSERT IGNORE is safe when two first requests race.
            $insert = $this->pdo->prepare(
                'INSERT IGNORE INTO rate_limits (key_hash, window_start, attempts)
                 VALUES (:key_hash, :window_start, 0)'
            );
            $insert->execute([
                'key_hash' => $keyHash,
                'window_start' => $now->format('Y-m-d H:i:s'),
            ]);

            // Serialize increments for this bucket.
            $stmt = $this->pdo->prepare(
                'SELECT * FROM rate_limits WHERE key_hash = :key_hash FOR UPDATE'
            );
            $stmt->execute(['key_hash' => $keyHash]);
            $row = $stmt->fetch();

            $existingStart = new DateTimeImmutable($row['window_start']);
            $expired = $existingStart->getTimestamp() + $windowSeconds <= $now->getTimestamp();
            $windowStart = $expired ? $now : $existingStart;
            $attempts = $expired ? 1 : ((int) $row['attempts'] + 1);

            $write = $this->pdo->prepare(
                'UPDATE rate_limits
                 SET window_start = :window_start, attempts = :attempts
                 WHERE key_hash = :key_hash'
            );
            $write->execute([
                'key_hash' => $keyHash,
                'window_start' => $windowStart->format('Y-m-d H:i:s'),
                'attempts' => $attempts,
            ]);

            $this->pdo->commit();

            return [
                'allowed' => $attempts <= $limit,
                'remaining' => max(0, $limit - $attempts),
                'retry_after' => max(
                    0,
                    ($windowStart->getTimestamp() + $windowSeconds) - $now->getTimestamp()
                ),
            ];
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }
}
