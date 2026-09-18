<?php

declare(strict_types=1);

namespace Befit\Repository;

use PDO;

final class PaymentRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function currentPaidUntil(int $userId): ?string
    {
        $stmt = $this->pdo->prepare(
            'SELECT MAX(valid_until) FROM member_payments WHERE user_id = :user_id'
        );
        $stmt->execute(['user_id' => $userId]);
        $value = $stmt->fetchColumn();
        return $value === false || $value === null ? null : (string) $value;
    }

    public function historyForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.id, p.user_id, p.payment_date, p.valid_until, p.confirmed_by_user_id,
                    p.created_at,
                    CONCAT(COALESCE(a.first_name, \'\'), \' \', COALESCE(a.last_name, \'\')) AS confirmed_by_name
             FROM member_payments p
             LEFT JOIN users a ON a.id = p.confirmed_by_user_id
             WHERE p.user_id = :user_id
             ORDER BY p.payment_date DESC, p.id DESC'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function create(
        int $userId,
        string $paymentDate,
        string $validUntil,
        int $confirmedByUserId
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO member_payments
                (user_id, payment_date, valid_until, confirmed_by_user_id)
             VALUES
                (:user_id, :payment_date, :valid_until, :confirmed_by_user_id)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'payment_date' => $paymentDate,
            'valid_until' => $validUntil,
            'confirmed_by_user_id' => $confirmedByUserId,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.id, p.user_id, p.payment_date, p.valid_until, p.confirmed_by_user_id,
                    p.created_at,
                    CONCAT(COALESCE(a.first_name, \'\'), \' \', COALESCE(a.last_name, \'\')) AS confirmed_by_name
             FROM member_payments p
             LEFT JOIN users a ON a.id = p.confirmed_by_user_id
             WHERE p.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function currentForUsers(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $ids = array_values(array_unique(array_map('intval', $userIds)));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT user_id, MAX(valid_until) AS paid_until
             FROM member_payments
             WHERE user_id IN ({$placeholders})
             GROUP BY user_id"
        );
        $stmt->execute($ids);

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[(int) $row['user_id']] = $row['paid_until'];
        }
        return $result;
    }
}
