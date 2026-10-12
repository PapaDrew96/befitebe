<?php

declare(strict_types=1);

namespace Befit\Support;

final class UserPresenter
{
    public static function one(array $user): array
    {
        $firstName = trim((string) ($user['first_name'] ?? ''));
        $lastName = trim((string) ($user['last_name'] ?? ''));

        $name = trim($firstName . ' ' . $lastName);

        $displayName = $name !== ''
            ? $name
            : ((string) ($user['email'] ?? ''));

        return [
            'id' => (int) $user['id'],
            'role' => $user['role'],
            'first_name' => $firstName,
            'last_name' => $lastName,
            'display_name' => $displayName,
            'email' => $user['email'],
            'phone' => $user['phone'],
            'status' => $user['status'],
            'must_change_password' => (bool) ($user['must_change_password'] ?? false),
            'last_login_at' => $user['last_login_at'] ?? null,
            'created_at' => $user['created_at'] ?? null,
            'updated_at' => $user['updated_at'] ?? null,
        ];
    }
}