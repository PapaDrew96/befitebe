<?php

declare(strict_types=1);

namespace Befit\Service;

use Befit\Database\Database;
use Befit\Exception\ApiException;
use Befit\Repository\ActivityLogRepository;
use Befit\Repository\BookingRepository;
use Befit\Repository\NotificationRepository;
use Befit\Repository\ScheduleRepository;
use Befit\Repository\SettingRepository;
use Befit\Repository\WaitlistRepository;
use Befit\Support\InputValidator;
use DateTimeImmutable;
use DateTimeZone;
use PDOException;

final class ScheduleService
{
    private DateTimeZone $timezone;

    public function __construct(
        private readonly Database $database,
        private readonly ScheduleRepository $schedule,
        private readonly BookingRepository $bookings,
        private readonly SettingRepository $settings,
        private readonly WaitlistRepository $waitlist,
        private readonly NotificationRepository $notifications,
        private readonly ActivityLogRepository $activity,
        private readonly int $maxDaysAhead,
        string $timezone
    ) {
        $this->timezone = new DateTimeZone($timezone);
    }

    public function week(?string $requestedDate, int $currentUserId): array
    {
        $date = $this->parseDate(
            $requestedDate
                ?: (new DateTimeImmutable('now', $this->timezone))->format('Y-m-d'),
            'date'
        );

        $this->assertWithinFutureHorizon($date);

        $weekStart = $date->modify('monday this week');
        $weekEnd = $weekStart->modify('+6 days');

        $this->ensureSessions($weekStart, $weekEnd);

        $sessions = $this->schedule->sessionsBetween(
            $weekStart->format('Y-m-d'),
            $weekEnd->format('Y-m-d')
        );
        $closures = $this->schedule->closuresBetween(
            $weekStart->format('Y-m-d'),
            $weekEnd->format('Y-m-d')
        );

        $closureMap = [];
        foreach ($closures as $closure) {
            $closureMap[$closure['closure_date']] = $closure;
        }

        $sessionIds = array_map(
            static fn (array $session): int => (int) $session['id'],
            $sessions
        );

        $attendeeRows = $this->bookings->attendeesForSessions($sessionIds);
        $attendeesBySession = [];
        foreach ($attendeeRows as $row) {
            $attendeesBySession[(int) $row['session_id']][] = $row;
        }

        $waitlistRows = $this->waitlist->waitingForSessions($sessionIds);
        $waitlistBySession = [];
        foreach ($waitlistRows as $row) {
            $waitlistBySession[(int) $row['session_id']][] = $row;
        }

        $showNames = $this->settingBool('show_attendee_names', false);
        $waitlistEnabled = $this->settingBool('waitlist_enabled', true);
        $bookingDaysAhead = max(0, (int)($this->settings->get('booking_days_ahead', '30') ?? 30));
        $bookingCutoff = max(0, (int)($this->settings->get('booking_cutoff_minutes', '60') ?? 60));
        $now = new DateTimeImmutable('now', $this->timezone);
        $byDate = [];

        foreach ($sessions as $session) {
            $sessionId = (int) $session['id'];
            $dateKey = $session['session_date'];
            $attendees = $attendeesBySession[$sessionId] ?? [];
            $closure = $closureMap[$dateKey] ?? null;
            $effectiveStatus = $closure ? 'closed' : $session['status'];

            $startsAt = new DateTimeImmutable(
                $dateKey . ' ' . $session['start_time'],
                $this->timezone
            );

            $myBookingId = null;
            foreach ($attendees as $attendee) {
                if ((int) $attendee['user_id'] === $currentUserId) {
                    $myBookingId = (int) $attendee['booking_id'];
                    break;
                }
            }

            $sessionWaitlist = $waitlistBySession[$sessionId] ?? [];
            $myWaitlistId = null;
            foreach ($sessionWaitlist as $entry) {
                if ((int)$entry['user_id'] === $currentUserId) {
                    $myWaitlistId = (int)$entry['id'];
                    break;
                }
            }


            $bookedCount = count($attendees);
            $capacity = (int) $session['capacity'];
            $isFull = $bookedCount >= $capacity;

            $publicAttendees = [];
            if ($showNames) {
                $publicAttendees = array_map(
                    static fn (array $attendee): array => [
                        'user_id' => (int) $attendee['user_id'],
                        'booking_id' => (int) $attendee['booking_id'],
                        'display_name' => trim(
                            $attendee['first_name'] . ' ' . $attendee['last_name']
                        ),
                    ],
                    $attendees
                );
            }

            $byDate[$dateKey][] = [
                'id' => $sessionId,
                'date' => $dateKey,
                'start_time' => substr($session['start_time'], 0, 5),
                'end_time' => $session['end_time']
                    ? substr($session['end_time'], 0, 5)
                    : null,
                'capacity' => $capacity,
                'booked_count' => $bookedCount,
                'available_count' => max(0, $capacity - $bookedCount),
                'is_full' => $isFull,
                'status' => $effectiveStatus,
                'session_status' => $session['status'],
                'closure_reason' => $closure['reason'] ?? null,
                'note' => $session['note'],
                'attendees' => $publicAttendees,
                'is_booked_by_me' => $myBookingId !== null,
                'my_booking_id' => $myBookingId,
                'waitlist_count' => count($sessionWaitlist),
                'is_waitlisted_by_me' => $myWaitlistId !== null,
                'my_waitlist_id' => $myWaitlistId,
                'waitlist_enabled' => $waitlistEnabled,
                'booking_allowed' =>
                    $effectiveStatus === 'open'
                    && !$isFull
                    && $myBookingId === null
                    && $startsAt > $now
                    && ($bookingDaysAhead === 0 || $startsAt <= $now->modify('+' . $bookingDaysAhead . ' days'))
                    && ($bookingCutoff === 0 || $now <= $startsAt->modify('-' . $bookingCutoff . ' minutes')),
                'waitlist_allowed' =>
                    $waitlistEnabled
                    && $effectiveStatus === 'open'
                    && $isFull
                    && $myBookingId === null
                    && $myWaitlistId === null
                    && $startsAt > $now
                    && ($bookingDaysAhead === 0 || $startsAt <= $now->modify('+' . $bookingDaysAhead . ' days'))
                    && ($bookingCutoff === 0 || $now <= $startsAt->modify('-' . $bookingCutoff . ' minutes')),
            ];
        }

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $current = $weekStart->modify("+{$i} days");
            $key = $current->format('Y-m-d');

            $days[] = [
                'date' => $key,
                'weekday' => (int) $current->format('N'),
                'weekday_name' => $current->format('l'),
                'is_closed' => isset($closureMap[$key]),
                'closure_reason' => $closureMap[$key]['reason'] ?? null,
                'sessions' => $byDate[$key] ?? [],
            ];
        }

        return [
            'week_start' => $weekStart->format('Y-m-d'),
            'week_end' => $weekEnd->format('Y-m-d'),
            'days' => $days,
        ];
    }

    public function templates(): array
    {
        return array_map(
            [$this, 'normalizeTemplate'],
            $this->schedule->templates(false)
        );
    }

    public function createTemplate(array $input, int $actorUserId): array
    {
        $this->validateTemplate($input, true);
        $data = $this->normalizeTemplateInput($input, true);
        $this->assertEndAfterStart($data['start_time'], $data['end_time'] ?? null);

        try {
            $id = $this->database->transaction(
                function () use ($data, $actorUserId): int {
                    $id = $this->schedule->createTemplate($data);
                    $this->activity->log(
                        $actorUserId,
                        'schedule.template_create',
                        'schedule_template',
                        $id,
                        $data
                    );
                    return $id;
                }
            );
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                throw ApiException::conflict(
                    'A schedule template already exists for that weekday and start time.'
                );
            }
            throw $exception;
        }

        return $this->normalizeTemplate(
            $this->schedule->findTemplate($id)
        );
    }

    public function updateTemplate(
        int $id,
        array $input,
        int $actorUserId
    ): array {
        $existing = $this->schedule->findTemplate($id);
        if (!$existing) {
            throw ApiException::notFound('Schedule template not found.');
        }

        $this->validateTemplate($input, false);
        $data = $this->normalizeTemplateInput($input, false);
        $this->assertEndAfterStart(
            $data['start_time'] ?? $existing['start_time'],
            array_key_exists('end_time', $data) ? $data['end_time'] : $existing['end_time']
        );

        try {
            $this->database->transaction(
                function () use ($id, $data, $actorUserId): void {
                    $this->schedule->updateTemplate($id, $data);
                    $this->activity->log(
                        $actorUserId,
                        'schedule.template_update',
                        'schedule_template',
                        $id,
                        ['fields' => array_keys($data)]
                    );
                }
            );
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                throw ApiException::conflict(
                    'A schedule template already exists for that weekday and start time.'
                );
            }
            throw $exception;
        }

        return $this->normalizeTemplate(
            $this->schedule->findTemplate($id)
        );
    }

    public function deleteTemplate(int $id, int $actorUserId): void
    {
        if (!$this->schedule->findTemplate($id)) {
            throw ApiException::notFound('Schedule template not found.');
        }

        $this->database->transaction(
            function () use ($id, $actorUserId): void {
                $this->schedule->deactivateTemplate($id);
                $this->activity->log(
                    $actorUserId,
                    'schedule.template_deactivate',
                    'schedule_template',
                    $id
                );
            }
        );
    }

    public function adminSessions(string $from, string $to): array
    {
        $fromDate = $this->parseDate($from, 'from');
        $toDate = $this->parseDate($to, 'to');

        if ($toDate < $fromDate) {
            throw ApiException::validation([
                'to' => [
                    'The end date must be on or after the start date.'
                ]
            ]);
        }

        if ($fromDate->diff($toDate)->days > 93) {
            throw ApiException::validation([
                'to' => ['The requested range may not exceed 93 days.']
            ]);
        }

        $this->ensureSessions($fromDate, $toDate);
        $sessions = $this->schedule->sessionsBetween($from, $to);

        $ids = array_map(
            static fn (array $session): int => (int) $session['id'],
            $sessions
        );
        $attendees = $this->bookings->attendeesForSessions($ids);

        $counts = [];
        foreach ($attendees as $attendee) {
            $sessionId = (int) $attendee['session_id'];
            $counts[$sessionId] = ($counts[$sessionId] ?? 0) + 1;
        }

        return array_map(
            static function (array $session) use ($counts): array {
                $id = (int) $session['id'];

                return [
                    'id' => $id,
                    'template_id' => $session['template_id'] !== null
                        ? (int) $session['template_id']
                        : null,
                    'date' => $session['session_date'],
                    'start_time' => substr($session['start_time'], 0, 5),
                    'end_time' => $session['end_time']
                        ? substr($session['end_time'], 0, 5)
                        : null,
                    'capacity' => (int) $session['capacity'],
                    'booked_count' => $counts[$id] ?? 0,
                    'status' => $session['status'],
                    'note' => $session['note'],
                    'created_at' => $session['created_at'],
                    'updated_at' => $session['updated_at'],
                ];
            },
            $sessions
        );
    }

    public function createSession(array $input, int $actorUserId): array
    {
        (new InputValidator($input))
            ->requiredDate('date')
            ->requiredTime('start_time')
            ->optionalTime('end_time')
            ->requiredInt('capacity', 1, 100)
            ->oneOf(
                'status',
                ['open', 'closed', 'cancelled']
            )
            ->optionalString('note', 0, 500)
            ->throwIfInvalid();

        $data = [
            'session_date' => $input['date'],
            'start_time' => $this->secondsTime($input['start_time']),
            'end_time' => !empty($input['end_time'])
                ? $this->secondsTime($input['end_time'])
                : null,
            'capacity' => (int) $input['capacity'],
            'status' => $input['status'] ?? 'open',
            'note' => isset($input['note'])
                ? trim((string) $input['note'])
                : null,
            'created_by' => $actorUserId,
        ];
        $this->assertEndAfterStart($data['start_time'], $data['end_time']);

        try {
            $id = $this->database->transaction(
                function () use ($data, $actorUserId): int {
                    $id = $this->schedule->createManualSession($data);
                    $this->activity->log(
                        $actorUserId,
                        'schedule.session_create',
                        'gym_session',
                        $id,
                        $data
                    );
                    return $id;
                }
            );
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                throw ApiException::conflict(
                    'A session already exists at that date and time.'
                );
            }
            throw $exception;
        }

        return $this->normalizeSession(
            $this->schedule->findSession($id)
        );
    }

    public function updateSession(
        int $id,
        array $input,
        int $actorUserId
    ): array {
        $existing = $this->schedule->findSession($id);
        if (!$existing) {
            throw ApiException::notFound('Session not found.');
        }

        $validator = new InputValidator($input);
        $validator
            ->optionalDate('date')
            ->optionalTime('start_time')
            ->optionalTime('end_time')
            ->optionalInt('capacity', 1, 100)
            ->oneOf(
                'status',
                ['open', 'closed', 'cancelled']
            )
            ->optionalString('note', 0, 500);
        $validator->throwIfInvalid();

        $data = [];

        if (array_key_exists('date', $input)) {
            $data['session_date'] = $input['date'];
        }
        if (array_key_exists('start_time', $input)) {
            $data['start_time'] = $this->secondsTime(
                $input['start_time']
            );
        }
        if (array_key_exists('end_time', $input)) {
            $data['end_time'] = $input['end_time']
                ? $this->secondsTime($input['end_time'])
                : null;
        }
        if (array_key_exists('capacity', $input)) {
            $data['capacity'] = (int) $input['capacity'];
        }
        if (array_key_exists('status', $input)) {
            $data['status'] = $input['status'];
        }
        if (array_key_exists('note', $input)) {
            $data['note'] = $input['note'] === null
                ? null
                : trim((string) $input['note']);
        }

        $this->assertEndAfterStart(
            $data['start_time'] ?? $existing['start_time'],
            array_key_exists('end_time', $data) ? $data['end_time'] : $existing['end_time']
        );

        try {
            $this->database->transaction(
                function () use ($id, $data, $actorUserId, $existing): void {
                    // Lock the dated session so capacity/status changes serialize with bookings.
                    if (!$this->schedule->findSessionForUpdate($id)) {
                        throw ApiException::notFound('Session not found.');
                    }

                    if (
                        isset($data['capacity'])
                        && $data['capacity']
                            < $this->bookings->countBookedForSession($id)
                    ) {
                        throw ApiException::conflict(
                            'Capacity cannot be lower than the current number of booked members.'
                        );
                    }

                    $this->schedule->updateSession($id, $data);

                    $becameUnavailable =
                        isset($data['status'])
                        && in_array($data['status'], ['closed', 'cancelled'], true)
                        && $existing['status'] !== $data['status'];
                    $dateOrTimeChanged =
                        (isset($data['session_date']) && $data['session_date'] !== $existing['session_date'])
                        || (isset($data['start_time']) && $data['start_time'] !== $existing['start_time'])
                        || (array_key_exists('end_time', $data) && $data['end_time'] !== $existing['end_time']);

                    if ($becameUnavailable || $dateOrTimeChanged) {
                        $sessionDate = $data['session_date'] ?? $existing['session_date'];
                        $startTime = $data['start_time'] ?? $existing['start_time'];
                        $label = substr((string)$startTime, 0, 5);
                        $status = $data['status'] ?? $existing['status'];
                        $title = $becameUnavailable
                            ? ($status === 'cancelled' ? 'Training session cancelled' : 'Training session closed')
                            : 'Training session updated';
                        $body = $becameUnavailable
                            ? 'Your ' . $sessionDate . ' training session at ' . $label . ' is no longer open. Please review your bookings or contact the gym.'
                            : 'A booked training session has changed. The new date/time is ' . $sessionDate . ' at ' . $label . '. Please review your booking.';
                        $affectedBookings = $this->bookings->activeUsersForSession($id);
                        foreach ($affectedBookings as $booking) {
                            $this->notifications->create(
                                (int)$booking['user_id'],
                                'session_changed',
                                $title,
                                $body,
                                ['booking_id'=>(int)$booking['booking_id'],'session_id'=>$id,'date'=>$sessionDate,'status'=>$status]
                            );
                            if ($status === 'cancelled') {
                                $this->bookings->cancel(
                                    (int)$booking['booking_id'],
                                    $actorUserId,
                                    (new DateTimeImmutable('now', $this->timezone))->format('Y-m-d H:i:s')
                                );
                            }
                        }

                        if ($status === 'cancelled') {
                            foreach ($this->waitlist->waitingForSessions([$id]) as $entry) {
                                $this->waitlist->leave((int)$entry['id']);
                                $this->notifications->create(
                                    (int)$entry['user_id'],
                                    'waitlist_released',
                                    'Waiting list closed',
                                    'The session you were waiting for on ' . $sessionDate . ' at ' . $label . ' was cancelled.',
                                    ['session_id'=>$id,'date'=>$sessionDate]
                                );
                            }
                        }
                    }

                    $this->activity->log(
                        $actorUserId,
                        'schedule.session_update',
                        'gym_session',
                        $id,
                        ['fields' => array_keys($data)]
                    );
                }
            );
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                throw ApiException::conflict(
                    'Another session already exists at that date and time.'
                );
            }
            throw $exception;
        }

        return $this->normalizeSession(
            $this->schedule->findSession($id)
        );
    }

    public function closures(string $from, string $to): array
    {
        $fromDate = $this->parseDate($from, 'from');
        $toDate = $this->parseDate($to, 'to');

        if ($toDate < $fromDate) {
            throw ApiException::validation([
                'to' => [
                    'The end date must be on or after the start date.'
                ]
            ]);
        }

        return array_map(
            [$this, 'normalizeClosure'],
            $this->schedule->closuresBetween($from, $to)
        );
    }

    public function createClosure(array $input, int $actorUserId): array
    {
        (new InputValidator($input))
            ->requiredDate('date')
            ->optionalString('reason', 0, 500)
            ->throwIfInvalid();

        try {
            $id = $this->database->transaction(
                function () use ($input, $actorUserId): int {
                    $id = $this->schedule->createClosure(
                        $input['date'],
                        isset($input['reason'])
                            ? trim((string) $input['reason'])
                            : null,
                        $actorUserId
                    );

                    $reason = isset($input['reason']) && trim((string)$input['reason']) !== ''
                        ? trim((string)$input['reason'])
                        : 'The gym is closed on this date.';
                    $cancelledAt = (new DateTimeImmutable('now', $this->timezone))->format('Y-m-d H:i:s');
                    $sessions = $this->schedule->sessionsBetween($input['date'], $input['date']);
                    $sessionIds = [];
                    foreach ($sessions as $session) {
                        $this->schedule->findSessionForUpdate((int)$session['id']);
                        $sessionIds[] = (int)$session['id'];
                    }
                    foreach ($this->bookings->activeBookingsForDate($input['date']) as $booking) {
                        $this->notifications->create(
                            (int)$booking['user_id'],
                            'gym_closed',
                            'Gym closed on ' . $input['date'],
                            $reason . ' Your reservation has been cancelled.',
                            ['booking_id'=>(int)$booking['booking_id'],'session_id'=>(int)$booking['session_id'],'date'=>$input['date']]
                        );
                        $this->bookings->cancel((int)$booking['booking_id'], $actorUserId, $cancelledAt);
                    }
                    foreach ($this->waitlist->waitingForSessions($sessionIds) as $entry) {
                        $this->waitlist->leave((int)$entry['id']);
                        $this->notifications->create(
                            (int)$entry['user_id'],
                            'waitlist_released',
                            'Waiting list closed',
                            'The gym is closed on ' . $input['date'] . '. Your waiting-list entry was released.',
                            ['session_id'=>(int)$entry['session_id'],'date'=>$input['date']]
                        );
                    }

                    $this->activity->log(
                        $actorUserId,
                        'schedule.closure_create',
                        'schedule_closure',
                        $id,
                        ['date' => $input['date']]
                    );
                    return $id;
                }
            );
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                throw ApiException::conflict(
                    'That date is already marked as closed.'
                );
            }
            throw $exception;
        }

        return $this->normalizeClosure(
            $this->schedule->findClosure($id)
        );
    }

    public function deleteClosure(int $id, int $actorUserId): void
    {
        if (!$this->schedule->findClosure($id)) {
            throw ApiException::notFound('Closure not found.');
        }

        $this->database->transaction(
            function () use ($id, $actorUserId): void {
                $this->schedule->deleteClosure($id);
                $this->activity->log(
                    $actorUserId,
                    'schedule.closure_delete',
                    'schedule_closure',
                    $id
                );
            }
        );
    }

    private function ensureSessions(
        DateTimeImmutable $from,
        DateTimeImmutable $to
    ): void {
        $templates = $this->schedule->templates(true);

        for (
            $date = $from;
            $date <= $to;
            $date = $date->modify('+1 day')
        ) {
            $weekday = (int) $date->format('N');

            foreach ($templates as $template) {
                if ((int) $template['weekday'] === $weekday) {
                    $this->schedule->createFromTemplateIfMissing(
                        $template,
                        $date->format('Y-m-d')
                    );
                }
            }
        }
    }

    private function validateTemplate(
        array $input,
        bool $creating
    ): void {
        $validator = new InputValidator($input);

        if ($creating) {
            $validator
                ->requiredInt('weekday', 1, 7)
                ->requiredTime('start_time')
                ->requiredInt('capacity', 1, 100);
        } else {
            $validator
                ->optionalInt('weekday', 1, 7)
                ->optionalTime('start_time')
                ->optionalInt('capacity', 1, 100);
        }

        $validator
            ->optionalTime('end_time')
            ->optionalBool('is_active')
            ->throwIfInvalid();
    }

    private function normalizeTemplateInput(
        array $input,
        bool $creating
    ): array {
        $data = [];

        if ($creating || array_key_exists('weekday', $input)) {
            $data['weekday'] = (int) $input['weekday'];
        }
        if ($creating || array_key_exists('start_time', $input)) {
            $data['start_time'] = $this->secondsTime(
                $input['start_time']
            );
        }
        if (array_key_exists('end_time', $input)) {
            $data['end_time'] = $input['end_time']
                ? $this->secondsTime($input['end_time'])
                : null;
        } elseif ($creating) {
            $data['end_time'] = null;
        }
        if ($creating || array_key_exists('capacity', $input)) {
            $data['capacity'] = (int) $input['capacity'];
        }
        if (array_key_exists('is_active', $input)) {
            $data['is_active'] = filter_var(
                $input['is_active'],
                FILTER_VALIDATE_BOOL
            ) ? 1 : 0;
        } elseif ($creating) {
            $data['is_active'] = 1;
        }

        return $data;
    }

    private function normalizeTemplate(?array $template): array
    {
        if (!$template) {
            throw ApiException::notFound('Schedule template not found.');
        }

        return [
            'id' => (int) $template['id'],
            'weekday' => (int) $template['weekday'],
            'start_time' => substr($template['start_time'], 0, 5),
            'end_time' => $template['end_time']
                ? substr($template['end_time'], 0, 5)
                : null,
            'capacity' => (int) $template['capacity'],
            'is_active' => (bool) $template['is_active'],
            'created_at' => $template['created_at'],
            'updated_at' => $template['updated_at'],
        ];
    }

    private function normalizeSession(?array $session): array
    {
        if (!$session) {
            throw ApiException::notFound('Session not found.');
        }

        return [
            'id' => (int) $session['id'],
            'template_id' => $session['template_id'] !== null
                ? (int) $session['template_id']
                : null,
            'date' => $session['session_date'],
            'start_time' => substr($session['start_time'], 0, 5),
            'end_time' => $session['end_time']
                ? substr($session['end_time'], 0, 5)
                : null,
            'capacity' => (int) $session['capacity'],
            'status' => $session['status'],
            'note' => $session['note'],
            'created_at' => $session['created_at'] ?? null,
            'updated_at' => $session['updated_at'] ?? null,
        ];
    }

    private function normalizeClosure(?array $closure): array
    {
        if (!$closure) {
            throw ApiException::notFound('Closure not found.');
        }

        return [
            'id' => (int) $closure['id'],
            'date' => $closure['closure_date'],
            'reason' => $closure['reason'],
            'created_by' => $closure['created_by'] !== null
                ? (int) $closure['created_by']
                : null,
            'created_at' => $closure['created_at'],
        ];
    }

    private function parseDate(
        string $date,
        string $field
    ): DateTimeImmutable {
        $parsed = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $date,
            $this->timezone
        );

        if (!$parsed || $parsed->format('Y-m-d') !== $date) {
            throw ApiException::validation([
                $field => ['Date must use YYYY-MM-DD format.']
            ]);
        }

        return $parsed;
    }

    private function assertWithinFutureHorizon(
        DateTimeImmutable $date
    ): void {
        $today = new DateTimeImmutable('today', $this->timezone);

        if (
            $date
                > $today->modify('+' . $this->maxDaysAhead . ' days')
        ) {
            throw ApiException::validation([
                'date' => [
                    "Schedule may only be requested {$this->maxDaysAhead} days ahead."
                ]
            ]);
        }
    }

    private function settingBool(
        string $key,
        bool $default
    ): bool {
        $value = $this->settings->get($key);

        if ($value === null) {
            return $default;
        }

        return filter_var(
            $value,
            FILTER_VALIDATE_BOOL,
            FILTER_NULL_ON_FAILURE
        ) ?? $default;
    }

    private function assertEndAfterStart(string $startTime, ?string $endTime): void
    {
        if ($endTime !== null && $endTime <= $startTime) {
            throw ApiException::validation([
                'end_time' => ['End time must be later than start time.']
            ]);
        }
    }

    private function secondsTime(string $time): string
    {
        return strlen($time) === 5 ? $time . ':00' : $time;
    }
}
