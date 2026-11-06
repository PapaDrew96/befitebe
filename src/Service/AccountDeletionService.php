<?php

declare(strict_types=1);

namespace Befit\Service;

use Befit\Database\Database;
use Befit\Exception\ApiException;
use Befit\Repository\ActivityLogRepository;
use Befit\Repository\BookingRepository;
use Befit\Repository\NotificationRepository;
use Befit\Repository\PasswordResetRepository;
use Befit\Repository\PaymentRepository;
use Befit\Repository\ScheduleRepository;
use Befit\Repository\SettingRepository;
use Befit\Repository\TokenRepository;
use Befit\Repository\UserRepository;
use Befit\Repository\WaitlistRepository;
use Befit\Security\PasswordHasher;
use Befit\Support\InputValidator;
use Befit\Repository\PushDeviceRepository;
use DateTimeImmutable;
use DateTimeZone;

final class AccountDeletionService
{
    private DateTimeZone $timezone;

    public function __construct(
        private readonly Database $database,
        private readonly UserRepository $users,
        private readonly TokenRepository $tokens,
        private readonly PasswordResetRepository $passwordResets,
        private readonly BookingRepository $bookings,
        private readonly ScheduleRepository $schedule,
        private readonly SettingRepository $settings,
        private readonly WaitlistRepository $waitlist,
        private readonly NotificationRepository $notifications,
        private readonly PushDeviceRepository $pushDevices,
        private readonly PaymentRepository $payments,
        private readonly ActivityLogRepository $activity,
        private readonly PasswordHasher $passwords,
        string $timezone
    ) {
        $this->timezone = new DateTimeZone($timezone);
    }

    public function deleteOwnAccount(int $userId, array $input): array
    {
        $validator = new InputValidator($input);
        $validator
            ->requiredString('current_password', 1, 255)
            ->oneOf('confirm', [true], true);
        $validator->throwIfInvalid();

        $now = new DateTimeImmutable('now', $this->timezone);
        $deletedAt = $now->format('Y-m-d H:i:s');

        $result = $this->database->transaction(function () use ($userId, $input, $deletedAt): array {
            $user = $this->users->findByIdForUpdate($userId);
            if (!$user) {
                throw ApiException::notFound('User not found.');
            }

            if (!$this->passwords->verify((string) $input['current_password'], (string) $user['password_hash'])) {
                throw ApiException::validation([
                    'current_password' => ['Current password is incorrect.'],
                ]);
            }

            if ($user['role'] === 'admin' && $this->users->countOtherActiveAdmins($userId) === 0) {
                throw ApiException::conflict(
                    'The last active administrator account cannot be deleted. Create or activate another administrator first.'
                );
            }

            $futureBookings = $this->bookings->futureActiveForUserForUpdate($userId, $deletedAt);
            $affectedSessionIds = [];

            foreach ($futureBookings as $booking) {
                $bookingId = (int) $booking['id'];
                $sessionId = (int) $booking['session_id'];

                $this->bookings->cancel($bookingId, $userId, $deletedAt);
                $affectedSessionIds[$sessionId] = true;
            }

            // An account that no longer exists must not remain in any queue.
            $this->waitlist->deleteForUser($userId);

            // Free future places immediately and preserve the existing automatic
            // waiting-list promotion behaviour for the remaining members.
            foreach (array_keys($affectedSessionIds) as $sessionId) {
                if ($this->settingBool('waitlist_enabled', true)
                    && $this->settingBool('auto_promote_waitlist', true)) {
                    $this->promoteNextWaitlisted((int) $sessionId);
                }
            }

            // Data that is no longer needed after account deletion is removed.
            $this->notifications->deleteForUser($userId);
            $this->pushDevices->deleteForUser($userId);
            $this->passwordResets->deleteForUser($userId);
            $this->payments->deleteForUser($userId);
            $this->tokens->deleteForUser($userId);

            // Historical bookings/attendance retain their FK for aggregate gym
            // reporting, but the linked account is irreversibly stripped of PII.
            $replacementPassword = $this->passwords->hash(bin2hex(random_bytes(32)));
            $this->users->anonymizeForDeletion($userId, $replacementPassword, $deletedAt);

            $this->activity->log(
                null,
                'account.delete',
                'user',
                $userId,
                [
                    'previous_role' => $user['role'],
                    'future_bookings_cancelled' => count($futureBookings),
                    'deleted_at' => $deletedAt,
                ]
            );

            return [
                'deleted' => true,
                'deleted_at' => $deletedAt,
            ];
        });

        return $result;
    }

    private function promoteNextWaitlisted(int $sessionId): void
    {
        $session = $this->schedule->findSessionForUpdate($sessionId);
        if (!$session || $session['status'] !== 'open') {
            return;
        }

        while ($this->bookings->countBookedForSession($sessionId) < (int) $session['capacity']) {
            $entry = $this->waitlist->nextWaitingForUpdate($sessionId);
            if (!$entry) {
                return;
            }

            if ($entry['user_status'] !== 'active') {
                $this->waitlist->leave((int) $entry['id']);
                continue;
            }

            $existing = $this->bookings->findBySessionAndUser($sessionId, (int) $entry['user_id']);
            if ($existing && in_array($existing['status'], ['booked', 'checked_in'], true)) {
                $this->waitlist->markPromoted((int) $entry['id'], (int) $existing['id']);
                continue;
            }

            $bookingId = $this->bookings->upsertBooked(
                $sessionId,
                (int) $entry['user_id'],
                (int) $entry['user_id']
            );
            $this->waitlist->markPromoted((int) $entry['id'], $bookingId);
            $this->notifications->create(
                (int) $entry['user_id'],
                'waitlist_promoted',
                'A place became available',
                'You were automatically moved from the waiting list into the session.',
                ['booking_id' => $bookingId, 'session_id' => $sessionId]
            );
            $this->activity->log(
                null,
                'waitlist.promote',
                'booking',
                $bookingId,
                [
                    'waitlist_id' => (int) $entry['id'],
                    'user_id' => (int) $entry['user_id'],
                    'reason' => 'account_deletion',
                ]
            );
            return;
        }
    }

    private function settingBool(string $key, bool $default): bool
    {
        return filter_var(
            $this->settings->get($key, $default ? '1' : '0'),
            FILTER_VALIDATE_BOOL
        );
    }
}
