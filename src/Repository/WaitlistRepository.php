<?php

declare(strict_types=1);

namespace Befit\Repository;

use PDO;

final class WaitlistRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function findBySessionAndUser(int $sessionId, int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM waitlist_entries WHERE session_id = :session_id AND user_id = :user_id LIMIT 1');
        $stmt->execute(['session_id' => $sessionId, 'user_id' => $userId]);
        return $stmt->fetch() ?: null;
    }

    public function findByIdForUser(int $id, int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM waitlist_entries WHERE id = :id AND user_id = :user_id LIMIT 1');
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        return $stmt->fetch() ?: null;
    }

    public function join(int $sessionId, int $userId): int
    {
        $existing = $this->findBySessionAndUser($sessionId, $userId);
        if ($existing) {
            $stmt = $this->pdo->prepare("UPDATE waitlist_entries SET status='waiting', joined_at=CURRENT_TIMESTAMP, left_at=NULL, promoted_at=NULL, promoted_booking_id=NULL WHERE id=:id");
            $stmt->execute(['id' => $existing['id']]);
            return (int) $existing['id'];
        }
        $stmt = $this->pdo->prepare("INSERT INTO waitlist_entries (session_id,user_id,status) VALUES (:session_id,:user_id,'waiting')");
        $stmt->execute(['session_id' => $sessionId, 'user_id' => $userId]);
        return (int) $this->pdo->lastInsertId();
    }

    public function leave(int $id): void
    {
        $stmt = $this->pdo->prepare("UPDATE waitlist_entries SET status='left', left_at=CURRENT_TIMESTAMP WHERE id=:id AND status='waiting'");
        $stmt->execute(['id' => $id]);
    }

    public function nextWaitingForUpdate(int $sessionId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT w.*, u.status AS user_status FROM waitlist_entries w INNER JOIN users u ON u.id=w.user_id WHERE w.session_id=:session_id AND w.status='waiting' ORDER BY w.joined_at ASC, w.id ASC LIMIT 1 FOR UPDATE");
        $stmt->execute(['session_id' => $sessionId]);
        return $stmt->fetch() ?: null;
    }

    public function markPromoted(int $id, int $bookingId): void
    {
        $stmt = $this->pdo->prepare("UPDATE waitlist_entries SET status='promoted', promoted_booking_id=:booking_id, promoted_at=CURRENT_TIMESTAMP WHERE id=:id");
        $stmt->execute(['booking_id' => $bookingId, 'id' => $id]);
    }

    public function countWaitingForSession(int $sessionId): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM waitlist_entries WHERE session_id=:session_id AND status='waiting'");
        $stmt->execute(['session_id' => $sessionId]);
        return (int) $stmt->fetchColumn();
    }


    public function adminList(string $from, string $to, string $status = 'waiting'): array
    {
        $stmt = $this->pdo->prepare("SELECT
                w.id,
                w.session_id,
                w.user_id,
                w.status,
                w.joined_at,
                w.left_at,
                w.promoted_at,
                w.promoted_booking_id,
                s.session_date,
                s.start_time,
                s.end_time,
                s.status AS session_status,
                u.first_name,
                u.last_name,
                u.email,
                u.phone
            FROM waitlist_entries w
            INNER JOIN gym_sessions s ON s.id = w.session_id
            INNER JOIN users u ON u.id = w.user_id
            WHERE s.session_date BETWEEN :from_date AND :to_date
              AND (:status = '' OR w.status = :status_filter)
            ORDER BY s.session_date ASC, s.start_time ASC, w.joined_at ASC, w.id ASC");
        $stmt->execute([
            'from_date' => $from,
            'to_date' => $to,
            'status' => $status,
            'status_filter' => $status,
        ]);
        return $stmt->fetchAll();
    }

    public function waitingForSessions(array $sessionIds): array
    {
        if ($sessionIds === []) return [];
        $placeholders = implode(',', array_fill(0, count($sessionIds), '?'));
        $stmt = $this->pdo->prepare("SELECT id,session_id,user_id,status,joined_at FROM waitlist_entries WHERE status='waiting' AND session_id IN ({$placeholders}) ORDER BY joined_at");
        foreach (array_values($sessionIds) as $i => $id) $stmt->bindValue($i + 1, (int)$id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
