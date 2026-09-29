<?php

declare(strict_types=1);

namespace Befit\Service;

use Befit\Database\Database;
use Befit\Exception\ApiException;
use Befit\Repository\ActivityLogRepository;
use Befit\Repository\TokenRepository;
use Befit\Repository\UserRepository;
use Befit\Security\PasswordHasher;
use Befit\Support\InputValidator;
use Befit\Support\UserPresenter;
use DateTimeImmutable;

final class AuthService
{
    public function __construct(
        private readonly Database $database,
        private readonly UserRepository $users,
        private readonly TokenRepository $tokens,
        private readonly ActivityLogRepository $activity,
        private readonly PasswordHasher $passwords,
        private readonly int $tokenTtlDays,
        private readonly int $maxActiveTokens
    ) {
    }

    public function login(array $input, string $tokenName = 'pwa'): array
    {
        (new InputValidator($input))
            ->requiredString('login', 1, 190)
            ->requiredString('password', 1, 255)
            ->throwIfInvalid();

        $login = trim((string) $input['login']);
        $password = (string) $input['password'];
        $user = $this->users->findByLogin($login);

        if (!$user) {
            // Perform a real password verification even for unknown accounts to reduce timing leaks.
            $this->passwords->verifyDummy($password);
            throw ApiException::unauthorized('Invalid login credentials.');
        }

        if (!$this->passwords->verify($password, (string) $user['password_hash'])) {
            throw ApiException::unauthorized('Invalid login credentials.');
        }

        if ($user['status'] !== 'active') {
            throw ApiException::forbidden('This account is inactive.');
        }

        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $now = new DateTimeImmutable();
        $expiresAt = $now->modify('+' . $this->tokenTtlDays . ' days');
        $needsRehash = $this->passwords->needsRehash((string) $user['password_hash']);

        $this->database->transaction(function () use (
            $user,
            $password,
            $needsRehash,
            $tokenHash,
            $tokenName,
            $now,
            $expiresAt
        ): void {
            $userId = (int) $user['id'];

            if ($needsRehash) {
                $this->users->updatePassword($userId, $this->passwords->hash($password));
            }

            $this->tokens->deleteExpiredForUser($userId, $now->format('Y-m-d H:i:s'));
            $this->tokens->create(
                $userId,
                $tokenHash,
                mb_substr($tokenName, 0, 100),
                $expiresAt->format('Y-m-d H:i:s')
            );
            $this->tokens->trimForUser($userId, $this->maxActiveTokens);

            $this->users->updateLastLogin(
                $userId,
                $now->format('Y-m-d H:i:s')
            );
            $this->activity->log(
                $userId,
                'auth.login',
                'user',
                $userId,
                $needsRehash ? ['password_hash_upgraded' => true] : []
            );
        });

        $fresh = $this->users->findById((int) $user['id']);

        return [
            'token' => $rawToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt->format(DATE_ATOM),
            'user' => UserPresenter::one($fresh ?? $user),
        ];
    }

    public function logout(int $userId, string $tokenHash): void
    {
        $this->database->transaction(function () use ($userId, $tokenHash): void {
            $this->tokens->deleteByHash($tokenHash);
            $this->activity->log($userId, 'auth.logout', 'user', $userId);
        });
    }
}
