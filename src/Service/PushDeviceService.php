<?php

declare(strict_types=1);

namespace Befit\Service;

use Befit\Exception\ApiException;
use Befit\Repository\PushDeviceRepository;

final class PushDeviceService
{
    public function __construct(private readonly PushDeviceRepository $devices) {}

    public function register(int $userId, array $input): array
    {
        $token = trim((string)($input['token'] ?? ''));
        $platform = strtolower(trim((string)($input['platform'] ?? '')));
        $language = strtolower(trim((string)($input['language'] ?? 'el')));

        $errors = [];

        if ($token === '') {
            $errors['token'][] = 'Push token is required.';
        } elseif (strlen($token) > 512) {
            $errors['token'][] = 'Push token is too long.';
        }

        if (!in_array($platform, ['android', 'ios'], true)) {
            $errors['platform'][] = 'Platform must be android or ios.';
        }

        if (!in_array($language, ['el', 'en'], true)) {
            $errors['language'][] = 'Language must be el or en.';
        }

        if ($errors !== []) {
            throw ApiException::validation($errors);
        }

        $this->devices->upsert($userId, $token, $platform, $language);

        return [
            'registered' => true,
            'platform' => $platform,
            'language' => $language,
        ];
    }

    public function unregister(int $userId, array $input): array
    {
        $token = trim((string)($input['token'] ?? ''));

        if ($token === '') {
            throw ApiException::validation([
                'token' => ['Push token is required.'],
            ]);
        }

        $this->devices->deleteForUserAndToken($userId, $token);

        return [
            'registered' => false,
        ];
    }
}
