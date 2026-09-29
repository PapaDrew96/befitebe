<?php

declare(strict_types=1);

namespace Befit\Security;

final class PasswordHasher
{
    private const DUMMY_ARGON2ID_HASH = '$argon2id$v=19$m=65536,t=4,p=2$Z2dob081bGFmdWdDT1pNMw$X0N+E/le1bdY87ucAwj4hXfQ0lJ7GG1BOLah5KXXyuA';
    private const DUMMY_BCRYPT_HASH = '$2y$12$2TV8h9oAL.YRZbM/Lper7uas6U4g4aNtO744h8dguT7y0HjEbOaiK';

    public function __construct(
        private readonly int $argonMemoryKb = 65536,
        private readonly int $argonTimeCost = 4,
        private readonly int $argonThreads = 2,
        private readonly int $bcryptCost = 12
    ) {
    }

    public function hash(string $password): string
    {
        if (defined('PASSWORD_ARGON2ID')) {
            return password_hash($password, PASSWORD_ARGON2ID, [
                'memory_cost' => $this->argonMemoryKb,
                'time_cost' => $this->argonTimeCost,
                'threads' => $this->argonThreads,
            ]);
        }

        return password_hash($password, PASSWORD_BCRYPT, [
            'cost' => $this->bcryptCost,
        ]);
    }

    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public function verifyDummy(string $password): void
    {
        password_verify(
            $password,
            defined('PASSWORD_ARGON2ID') ? self::DUMMY_ARGON2ID_HASH : self::DUMMY_BCRYPT_HASH
        );
    }

    public function needsRehash(string $hash): bool
    {
        if (defined('PASSWORD_ARGON2ID')) {
            return password_needs_rehash($hash, PASSWORD_ARGON2ID, [
                'memory_cost' => $this->argonMemoryKb,
                'time_cost' => $this->argonTimeCost,
                'threads' => $this->argonThreads,
            ]);
        }

        return password_needs_rehash($hash, PASSWORD_BCRYPT, [
            'cost' => $this->bcryptCost,
        ]);
    }
}
