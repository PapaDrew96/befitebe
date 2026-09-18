<?php

declare(strict_types=1);

namespace Befit\Repository;

use PDO;

final class ReportRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function attendanceSummary(string $from, string $to): array
    {
        $stmt = $this->pdo->prepare("SELECT
            COUNT(b.id) AS total_reservations,
            SUM(b.status='cancelled') AS cancelled,
            SUM(b.status='checked_in') AS attended,
            SUM(b.status='no_show') AS no_shows,
            SUM(b.status='booked') AS still_booked
          FROM bookings b INNER JOIN gym_sessions s ON s.id=b.session_id
          WHERE s.session_date BETWEEN :from_date AND :to_date");
        $stmt->execute(['from_date'=>$from,'to_date'=>$to]);
        return $stmt->fetch() ?: [];
    }

    public function popularHour(string $from, string $to): ?array
    {
        $stmt = $this->pdo->prepare("SELECT TIME_FORMAT(s.start_time,'%H:%i') AS start_time, COUNT(*) AS reservations
          FROM bookings b INNER JOIN gym_sessions s ON s.id=b.session_id
          WHERE s.session_date BETWEEN :from_date AND :to_date AND b.status<>'cancelled'
          GROUP BY s.start_time ORDER BY reservations DESC, s.start_time ASC LIMIT 1");
        $stmt->execute(['from_date'=>$from,'to_date'=>$to]);
        return $stmt->fetch() ?: null;
    }

    public function occupancy(string $from, string $to): array
    {
        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(x.booked_count),0) AS booked_total, COALESCE(SUM(x.capacity),0) AS capacity_total
          FROM (
            SELECT s.id,s.capacity,COUNT(b.id) AS booked_count
            FROM gym_sessions s LEFT JOIN bookings b ON b.session_id=s.id AND b.status IN ('booked','checked_in','no_show')
            WHERE s.session_date BETWEEN :from_date AND :to_date AND s.status<>'cancelled'
            GROUP BY s.id,s.capacity
          ) x");
        $stmt->execute(['from_date'=>$from,'to_date'=>$to]);
        return $stmt->fetch() ?: ['booked_total'=>0,'capacity_total'=>0];
    }

    public function daily(string $from, string $to): array
    {
        $stmt = $this->pdo->prepare("SELECT s.session_date AS date,
          COUNT(b.id) AS reservations,
          SUM(b.status='checked_in') AS attended,
          SUM(b.status='no_show') AS no_shows,
          SUM(b.status='cancelled') AS cancelled
          FROM gym_sessions s LEFT JOIN bookings b ON b.session_id=s.id
          WHERE s.session_date BETWEEN :from_date AND :to_date
          GROUP BY s.session_date ORDER BY s.session_date");
        $stmt->execute(['from_date'=>$from,'to_date'=>$to]);
        return $stmt->fetchAll();
    }
}
