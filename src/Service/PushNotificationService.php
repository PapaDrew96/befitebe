<?php

declare(strict_types=1);

namespace Befit\Service;

use Befit\Repository\PushDeviceRepository;
use Psr\Log\LoggerInterface;
use Throwable;

final class PushNotificationService
{
    public function __construct(
        private readonly PushDeviceRepository $devices,
        private readonly FcmService $fcm,
        private readonly LoggerInterface $logger
    ) {}

    public function sendToUser(
        int $userId,
        string $title,
        string $body,
        array $data = []
    ): array {
        $summary = [
            'attempted' => 0,
            'sent' => 0,
            'failed' => 0,
            'removed' => 0,
        ];

        if (!$this->fcm->isEnabled()) {
            return $summary;
        }

        try {
            $devices = $this->devices->forUser($userId);
        } catch (Throwable $e) {
            $this->logger->error('Unable to load push devices.', [
                'user_id' => $userId,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return $summary;
        }

        foreach ($devices as $device) {
            $summary['attempted']++;

            try {
                $result = $this->fcm->send(
                    (string)$device['token'],
                    $title,
                    $body,
                    $data
                );

                if ($result['sent']) {
                    $summary['sent']++;
                    continue;
                }

                $summary['failed']++;

                if ($result['invalid_token']) {
                    $this->devices->deleteByToken((string)$device['token']);
                    $summary['removed']++;

                    $this->logger->info('Removed invalid FCM registration.', [
                        'user_id' => $userId,
                        'push_device_id' => (int)$device['id'],
                        'platform' => $device['platform'],
                        'fcm_error' => $result['error_code'],
                    ]);

                    continue;
                }

                $this->logger->warning('Push notification delivery failed.', [
                    'user_id' => $userId,
                    'push_device_id' => (int)$device['id'],
                    'platform' => $device['platform'],
                    'http_status' => $result['status'],
                    'fcm_error' => $result['error_code'],
                    'retryable' => $result['retryable'],
                ]);
            } catch (Throwable $e) {
                $summary['failed']++;

                $this->logger->error('Unexpected push-device delivery failure.', [
                    'user_id' => $userId,
                    'push_device_id' => (int)($device['id'] ?? 0),
                    'platform' => $device['platform'] ?? null,
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $summary;
    }
}
