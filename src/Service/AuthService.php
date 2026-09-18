<?php

declare(strict_types=1);

namespace Befit\Service;

use Befit\Database\Database;
use Befit\Exception\ApiException;
use Befit\Repository\ActivityLogRepository;
use Befit\Repository\TokenRepository;
use Befit\Repository\UserRepository;
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
        private readonly int $tokenTtlDays
    ) {
    }

    public function login(array $input, string $tokenName = 'pwa'): array
    {
        (new InputValidator($input))
            ->requiredString('login', 1, 190)
            ->requiredString('password', 8, 255)
            ->throwIfInvalid();

        $user = $this->users->findByLogin(trim($input['login']));

        if (!$user || !password_verify($input['password'], $user['password_hash'])) {
            throw ApiException::unauthorized('Invalid login credentials.');
        }

        if ($user['status'] !== 'active') {
            throw ApiException::forbidden('This account is inactive.');
        }

        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $now = new DateTimeImmutable();
        $expiresAt = $now->modify('+' . $this->tokenTtlDays . ' days');

        $this->database->transaction(function () use (
            $user,
            $tokenHash,
            $tokenName,
            $now,
            $expiresAt
        ): void {
            $this->tokens->create(
                (int) $user['id'],
                $tokenHash,
                mb_substr($tokenName, 0, 100),
                $expiresAt->format('Y-m-d H:i:s')
            );
            $this->users->updateLastLogin(
                (int) $user['id'],
                $now->format('Y-m-d H:i:s')
            );
            $this->activity->log(
                (int) $user['id'],
                'auth.login',
                'user',
                (int) $user['id']
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
