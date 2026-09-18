<?php

declare(strict_types=1);

namespace Befit\Service;

use Befit\Exception\ApiException;
use Befit\Repository\ReportRepository;
use DateTimeImmutable;
use DateTimeZone;

final class ReportService
{
    private DateTimeZone $timezone;

    public function __construct(private readonly ReportRepository $reports, string $timezone)
    {
        $this->timezone = new DateTimeZone($timezone);
    }

    public function attendance(array $query): array
    {
        $today = new DateTimeImmutable('today', $this->timezone);
        $from = $this->date((string)($query['from'] ?? $today->modify('first day of this month')->format('Y-m-d')), 'from');
        $to = $this->date((string)($query['to'] ?? $today->format('Y-m-d')), 'to');
        if ($to < $from) throw ApiException::validation(['to' => ['The end date must be on or after the start date.']]);
        if ($from->diff($to)->days > 366) throw ApiException::validation(['to' => ['Report range may not exceed 366 days.']]);

        $summary = $this->reports->attendanceSummary($from->format('Y-m-d'), $to->format('Y-m-d'));
        $occupancy = $this->reports->occupancy($from->format('Y-m-d'), $to->format('Y-m-d'));
        $capacity = (int)($occupancy['capacity_total'] ?? 0);
        $booked = (int)($occupancy['booked_total'] ?? 0);

        return [
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'summary' => [
                'total_reservations' => (int)($summary['total_reservations'] ?? 0),
                'cancelled' => (int)($summary['cancelled'] ?? 0),
                'attended' => (int)($summary['attended'] ?? 0),
                'no_shows' => (int)($summary['no_shows'] ?? 0),
                'still_booked' => (int)($summary['still_booked'] ?? 0),
                'average_occupancy_percent' => $capacity > 0 ? round(($booked / $capacity) * 100, 1) : 0.0,
                'popular_hour' => $this->reports->popularHour($from->format('Y-m-d'), $to->format('Y-m-d')),
            ],
            'daily' => array_map(static fn(array $r): array => [
                'date' => $r['date'],
                'reservations' => (int)$r['reservations'],
                'attended' => (int)$r['attended'],
                'no_shows' => (int)$r['no_shows'],
                'cancelled' => (int)$r['cancelled'],
            ], $this->reports->daily($from->format('Y-m-d'), $to->format('Y-m-d'))),
        ];
    }

    private function date(string $value, string $field): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $this->timezone);
        if (!$date || $date->format('Y-m-d') !== $value) throw ApiException::validation([$field => ['Must use YYYY-MM-DD format.']]);
        return $date;
    }
}
