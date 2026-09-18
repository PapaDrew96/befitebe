<?php

declare(strict_types=1);

namespace Befit\Service;

use Befit\Database\Database;
use Befit\Exception\ApiException;
use Befit\Repository\ActivityLogRepository;
use Befit\Repository\PasswordResetRepository;
use Befit\Repository\TokenRepository;
use Befit\Repository\UserRepository;
use Befit\Support\InputValidator;
use DateTimeImmutable;
use DateTimeZone;

final class PasswordResetService
{
    private DateTimeZone $timezone;

    public function __construct(
        private readonly Database $database,
        private readonly UserRepository $users,
        private readonly PasswordResetRepository $resets,
        private readonly TokenRepository $tokens,
        private readonly ActivityLogRepository $activity,
        private readonly bool $debug,
        private readonly string $resetBaseUrl,
        private readonly int $ttlMinutes,
        private readonly bool $mailEnabled,
        private readonly string $mailFrom,
        private readonly string $mailFromName,
        string $timezone
    ) {
        $this->timezone = new DateTimeZone($timezone);
    }

    public function request(array $input): array
    {
        (new InputValidator($input))->requiredString('login', 1, 190)->throwIfInvalid();
        $user = $this->users->findByLogin(trim((string)$input['login']));

        // Always return the same public message to avoid account enumeration.
        if (!$user || $user['status'] !== 'active') {
            return [];
        }

        $raw = bin2hex(random_bytes(32));
        $hash = hash('sha256', $raw);
        $expires = (new DateTimeImmutable('now', $this->timezone))->modify('+' . $this->ttlMinutes . ' minutes');

        $this->database->transaction(function () use ($user, $hash, $expires): void {
            $this->resets->deleteForUser((int)$user['id']);
            $this->resets->create((int)$user['id'], $hash, $expires->format('Y-m-d H:i:s'));
            $this->activity->log((int)$user['id'], 'auth.password_reset_requested', 'user', (int)$user['id']);
        });

        $resetUrl = rtrim($this->resetBaseUrl, '/') . '/#/reset-password?token=' . rawurlencode($raw);
        if ($this->mailEnabled && !empty($user['email'])) {
            $subject = 'BE-FIT password reset';
            $message = "A password reset was requested for your BE-FIT account.

Open this link to reset your password:
{$resetUrl}

This link expires in {$this->ttlMinutes} minutes.

If you did not request this, ignore this message.";
            $headers = [
                'From: ' . $this->mailFromName . ' <' . $this->mailFrom . '>',
                'Content-Type: text/plain; charset=UTF-8',
            ];
            @mail((string)$user['email'], $subject, $message, implode("\r\n", $headers));
        }

        if (!$this->debug) {
            return [];
        }

        return [
            'dev_reset_token' => $raw,
            'dev_reset_url' => $resetUrl,
            'expires_at' => $expires->format(DATE_ATOM),
        ];
    }

    public function reset(array $input): void
    {
        (new InputValidator($input))
            ->requiredString('token', 64, 64)
            ->requiredString('password', 8, 255)
            ->throwIfInvalid();

        $hash = hash('sha256', (string)$input['token']);
        $now = new DateTimeImmutable('now', $this->timezone);

        $this->database->transaction(function () use ($hash, $input, $now): void {
            $reset = $this->resets->findValidForUpdate($hash, $now->format('Y-m-d H:i:s'));
            if (!$reset) {
                throw ApiException::validation(['token' => ['The password reset link is invalid or has expired.']]);
            }
            $userId = (int)$reset['user_id'];
            $this->users->updatePassword($userId, password_hash((string)$input['password'], PASSWORD_DEFAULT));
            $this->users->setMustChangePassword($userId, false);
            $this->tokens->deleteForUser($userId);
            $this->resets->markUsed((int)$reset['id'], $now->format('Y-m-d H:i:s'));
            $this->activity->log($userId, 'auth.password_reset_completed', 'user', $userId);
        });
    }
}
