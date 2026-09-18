<?php

declare(strict_types=1);

namespace Befit\Service;

use Befit\Exception\ApiException;
use Befit\Repository\DashboardRepository;
use DateTimeImmutable;
use DateTimeZone;

final class DashboardService
{
    private DateTimeZone $timezone;

    public function __construct(
        private readonly DashboardRepository $dashboard,
        private readonly ScheduleService $schedule,
        string $timezone
    ) {
        $this->timezone = new DateTimeZone($timezone);
    }

    public function summary(?string $requestedDate, int $currentUserId): array
    {
        $dateString = $requestedDate ?: (new DateTimeImmutable('today', $this->timezone))->format('Y-m-d');
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $dateString, $this->timezone);
        if (!$date || $date->format('Y-m-d') !== $dateString) {
            throw ApiException::validation(['date' => ['Date must use YYYY-MM-DD format.']]);
        }

        // Calling week materializes recurring sessions and applies full-day closures.
        $week = $this->schedule->week($dateString, $currentUserId);
        $sessions = [];
        foreach ($week['days'] as $day) {
            if ($day['date'] !== $dateString) {
                continue;
            }
            $sessions = array_map(static fn (array $session): array => [
                'id' => $session['id'],
                'date' => $session['date'],
                'start_time' => $session['start_time'],
                'end_time' => $session['end_time'],
                'capacity' => $session['capacity'],
                'booked_count' => $session['booked_count'],
                'available_count' => $session['available_count'],
                'status' => $session['status'],
                'closure_reason' => $session['closure_reason'],
                'note' => $session['note'],
            ], $day['sessions']);
            break;
        }

        $now = new DateTimeImmutable('now', $this->timezone);
        $next = null;
        foreach ($sessions as $session) {
            $startsAt = new DateTimeImmutable($session['date'] . ' ' . $session['start_time'], $this->timezone);
            if ($startsAt >= $now && $session['status'] === 'open') {
                $next = $session;
                break;
            }
        }

        return [
            'date' => $dateString,
            'active_members' => $this->dashboard->activeMemberCount(),
            'bookings' => $this->dashboard->bookedCountForDate($dateString),
            'sessions_count' => count($sessions),
            'next_session' => $next,
            'sessions' => $sessions,
        ];
    }
}
