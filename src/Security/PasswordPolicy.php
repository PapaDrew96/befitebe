<?php

declare(strict_types=1);

namespace Befit\Security;

final class PasswordPolicy
{
    public function __construct(private readonly int $minimumLength = 12)
    {
    }

    /** @return string[] */
    public function errors(string $password): array
    {
        $errors = [];

        if (mb_strlen($password) < $this->minimumLength) {
            $errors[] = 'Password must be at least ' . $this->minimumLength . ' characters.';
        }
        if (mb_strlen($password) > 255) {
            $errors[] = 'Password must not exceed 255 characters.';
        }
        if (!preg_match('/[a-z]/u', $password)) {
            $errors[] = 'Password must include at least one lowercase letter.';
        }
        if (!preg_match('/[A-Z]/u', $password)) {
            $errors[] = 'Password must include at least one uppercase letter.';
        }
        if (!preg_match('/\d/u', $password)) {
            $errors[] = 'Password must include at least one number.';
        }

        $normalized = mb_strtolower(trim($password));
        $blocked = [
            'password1234',
            'password12345',
            'qwerty123456',
            'admin123456',
            'befit123456',
            '123456789012',
        ];
        if (in_array($normalized, $blocked, true)) {
            $errors[] = 'Choose a less predictable password.';
        }

        return $errors;
    }
}
