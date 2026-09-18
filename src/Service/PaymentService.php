<?php

declare(strict_types=1);

namespace Befit\Service;

use Befit\Database\Database;
use Befit\Exception\ApiException;
use Befit\Repository\ActivityLogRepository;
use Befit\Repository\NotificationRepository;
use Befit\Repository\PaymentRepository;
use Befit\Repository\UserRepository;
use Befit\Support\InputValidator;
use DateTimeImmutable;
use DateTimeZone;

final class PaymentService
{
    private DateTimeZone $timezone;

    public function __construct(
        private readonly Database $database,
        private readonly PaymentRepository $payments,
        private readonly UserRepository $users,
        private readonly NotificationRepository $notifications,
        private readonly ActivityLogRepository $activity,
        string $timezone
    ) {
        $this->timezone = new DateTimeZone($timezone);
    }

    public function mine(int $userId): array
    {
        return $this->summary($userId, false);
    }

    public function adminForUser(int $userId): array
    {
        $user = $this->users->findById($userId);
        if (!$user) {
            throw ApiException::notFound('User not found.');
        }

        $summary = $this->summary($userId, true);
        $summary['user'] = [
            'id' => (int) $user['id'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'display_name' => trim($user['first_name'] . ' ' . $user['last_name']),
            'role' => $user['role'],
            'status' => $user['status'],
        ];
        return $summary;
    }

    public function record(int $userId, array $input, int $actorUserId): array
    {
        $user = $this->users->findById($userId);
        if (!$user) {
            throw ApiException::notFound('User not found.');
        }
        if ($user['role'] !== 'member') {
            throw ApiException::validation(['user_id' => ['Payments can only be recorded for member accounts.']]);
        }

        (new InputValidator($input))
            ->optionalDate('payment_date')
            ->optionalDate('valid_until')
            ->throwIfInvalid();

        $today = new DateTimeImmutable('today', $this->timezone);
        $paymentDate = !empty($input['payment_date'])
            ? new DateTimeImmutable((string) $input['payment_date'], $this->timezone)
            : $today;

        $currentPaidUntil = $this->payments->currentPaidUntil($userId);
        $anchor = $paymentDate;
        if ($currentPaidUntil !== null) {
            $current = new DateTimeImmutable($currentPaidUntil, $this->timezone);
            if ($current >= $paymentDate) {
                $anchor = $current;
            }
        }

        $validUntil = !empty($input['valid_until'])
            ? new DateTimeImmutable((string) $input['valid_until'], $this->timezone)
            : $this->addOneMonthClamped($anchor);

        if ($validUntil < $paymentDate) {
            throw ApiException::validation([
                'valid_until' => ['Payment validity cannot end before the payment date.']
            ]);
        }

        $paymentId = $this->database->transaction(function () use (
            $userId,
            $paymentDate,
            $validUntil,
            $actorUserId
        ): int {
            $id = $this->payments->create(
                $userId,
                $paymentDate->format('Y-m-d'),
                $validUntil->format('Y-m-d'),
                $actorUserId
            );

            $this->notifications->create(
                $userId,
                'payment_confirmed',
                'Payment confirmed',
                'Your membership payment is valid until ' . $validUntil->format('Y-m-d') . '.',
                ['payment_id' => $id, 'valid_until' => $validUntil->format('Y-m-d')]
            );
            $this->activity->log(
                $actorUserId,
                'payment.record',
                'member_payment',
                $id,
                [
                    'user_id' => $userId,
                    'payment_date' => $paymentDate->format('Y-m-d'),
                    'valid_until' => $validUntil->format('Y-m-d'),
                ]
            );
            return $id;
        });

        $payment = $this->payments->findById($paymentId);
        $summary = $this->adminForUser($userId);
        $summary['payment'] = $payment ? $this->normalizePayment($payment) : null;
        return $summary;
    }

    public function currentStatuses(array $userIds): array
    {
        $paidUntilByUser = $this->payments->currentForUsers($userIds);
        $today = (new DateTimeImmutable('today', $this->timezone))->format('Y-m-d');
        $result = [];
        foreach ($userIds as $userId) {
            $id = (int) $userId;
            $paidUntil = $paidUntilByUser[$id] ?? null;
            $result[$id] = [
                'paid_until' => $paidUntil,
                'payment_status' => $this->status($paidUntil, $today),
            ];
        }
        return $result;
    }

    private function summary(int $userId, bool $includeSuggested): array
    {
        $todayObj = new DateTimeImmutable('today', $this->timezone);
        $today = $todayObj->format('Y-m-d');
        $paidUntil = $this->payments->currentPaidUntil($userId);
        $history = array_map([$this, 'normalizePayment'], $this->payments->historyForUser($userId));

        $result = [
            'status' => $this->status($paidUntil, $today),
            'paid_until' => $paidUntil,
            'history' => $history,
        ];

        if ($includeSuggested) {
            $anchor = $todayObj;
            if ($paidUntil !== null) {
                $current = new DateTimeImmutable($paidUntil, $this->timezone);
                if ($current >= $todayObj) {
                    $anchor = $current;
                }
            }
            $result['suggested_payment_date'] = $today;
            $result['suggested_valid_until'] = $this->addOneMonthClamped($anchor)->format('Y-m-d');
        }

        return $result;
    }

    private function normalizePayment(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'user_id' => (int) $row['user_id'],
            'payment_date' => $row['payment_date'],
            'valid_until' => $row['valid_until'],
            'confirmed_by_user_id' => $row['confirmed_by_user_id'] !== null ? (int) $row['confirmed_by_user_id'] : null,
            'confirmed_by_name' => trim((string) ($row['confirmed_by_name'] ?? '')) ?: null,
            'created_at' => $row['created_at'],
        ];
    }

    private function status(?string $paidUntil, string $today): string
    {
        if ($paidUntil === null) {
            return 'unpaid';
        }
        return $paidUntil >= $today ? 'paid' : 'expired';
    }

    private function addOneMonthClamped(DateTimeImmutable $date): DateTimeImmutable
    {
        $year = (int) $date->format('Y');
        $month = (int) $date->format('n') + 1;
        if ($month === 13) {
            $month = 1;
            $year++;
        }
        $day = (int) $date->format('j');
        $lastDay = (int) (new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month), $this->timezone))
            ->modify('last day of this month')
            ->format('j');
        return new DateTimeImmutable(
            sprintf('%04d-%02d-%02d', $year, $month, min($day, $lastDay)),
            $this->timezone
        );
    }
}
