<?php

declare(strict_types=1);

namespace Befit\Repository;

use PDO;

final class DashboardRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function activeMemberCount(): int
    {
        return (int) $this->pdo->query(
            "SELECT COUNT(*) FROM users WHERE role = 'member' AND status = 'active'"
        )->fetchColumn();
    }

    public function bookedCountForDate(string $date): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM bookings b
             INNER JOIN gym_sessions s ON s.id = b.session_id
             WHERE s.session_date = :session_date AND b.status IN ('booked','checked_in','no_show')"
        );
        $stmt->execute(['session_date' => $date]);
        return (int) $stmt->fetchColumn();
    }

}
