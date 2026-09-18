<?php

declare(strict_types=1);

namespace Befit\Support;

final class UserPresenter
{
    public static function one(array $user): array
    {
        return [
            'id' => (int) $user['id'],
            'role' => $user['role'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'display_name' => trim($user['first_name'] . ' ' . $user['last_name']),
            'email' => $user['email'],
            'phone' => $user['phone'],
            'status' => $user['status'],
            'must_change_password' => (bool)($user['must_change_password'] ?? false),
            'last_login_at' => $user['last_login_at'] ?? null,
            'created_at' => $user['created_at'] ?? null,
            'updated_at' => $user['updated_at'] ?? null,
        ];
    }
}
