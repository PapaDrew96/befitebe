<?php

declare(strict_types=1);

namespace Befit\Service;

use JsonException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

final class FcmService
{
    private ?array $credentials = null;
    private ?string $accessToken = null;
    private int $accessTokenExpiresAt = 0;

    public function __construct(
        private readonly bool $enabled,
        private readonly string $credentialsPath,
        private readonly string $projectId,
        private readonly LoggerInterface $logger
    ) {}

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function send(string $token, string $title, string $body, array $data = []): array
    {
        if (!$this->enabled) {
            return [
                'sent' => false,
                'skipped' => true,
                'status' => null,
                'message_id' => null,
                'error_code' => null,
                'invalid_token' => false,
                'retryable' => false,
            ];
        }

        try {
            $accessToken = $this->accessToken();
            $projectId = $this->resolvedProjectId();

            $payload = [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => $this->normalizeData($data),
                    'android' => [
                        'priority' => 'high',
                    ],
                ],
            ];

            $response = $this->request(
                sprintf(
                    'https://fcm.googleapis.com/v1/projects/%s/messages:send',
                    rawurlencode($projectId)
                ),
                [
                    'Authorization: Bearer ' . $accessToken,
                    'Content-Type: application/json; charset=UTF-8',
                ],
                json_encode(
                    $payload,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                )
            );

            if ($response['status'] >= 200 && $response['status'] < 300) {
                $decoded = json_decode($response['body'], true);

                return [
                    'sent' => true,
                    'skipped' => false,
                    'status' => $response['status'],
                    'message_id' => is_array($decoded) ? ($decoded['name'] ?? null) : null,
                    'error_code' => null,
                    'invalid_token' => false,
                    'retryable' => false,
                ];
            }

            $errorCode = $this->extractErrorCode($response['body']);
            $invalidToken = in_array($errorCode, ['UNREGISTERED'], true);
            $retryable = in_array($response['status'], [429, 500, 503], true)
                || in_array($errorCode, ['QUOTA_EXCEEDED', 'UNAVAILABLE', 'INTERNAL'], true);

            return [
                'sent' => false,
                'skipped' => false,
                'status' => $response['status'],
                'message_id' => null,
                'error_code' => $errorCode,
                'invalid_token' => $invalidToken,
                'retryable' => $retryable,
            ];
        } catch (Throwable $e) {
            $this->logger->error('FCM send failed.', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return [
                'sent' => false,
                'skipped' => false,
                'status' => null,
                'message_id' => null,
                'error_code' => 'LOCAL_ERROR',
                'invalid_token' => false,
                'retryable' => true,
            ];
        }
    }

    private function credentials(): array
    {
        if ($this->credentials !== null) {
            return $this->credentials;
        }

        if ($this->credentialsPath === '' || !is_readable($this->credentialsPath)) {
            throw new RuntimeException('Firebase credentials file is not readable.');
        }

        try {
            $credentials = json_decode(
                (string)file_get_contents($this->credentialsPath),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $e) {
            throw new RuntimeException('Firebase credentials file is not valid JSON.', 0, $e);
        }

        if (
            !is_array($credentials)
            || empty($credentials['client_email'])
            || empty($credentials['private_key'])
            || empty($credentials['project_id'])
        ) {
            throw new RuntimeException('Firebase service-account credentials are incomplete.');
        }

        if (
            $this->projectId !== ''
            && $credentials['project_id'] !== $this->projectId
        ) {
            throw new RuntimeException('Firebase project ID does not match service-account credentials.');
        }

        return $this->credentials = $credentials;
    }

    private function resolvedProjectId(): string
    {
        if ($this->projectId !== '') {
            return $this->projectId;
        }

        return (string)$this->credentials()['project_id'];
    }

    private function accessToken(): string
    {
        if (
            $this->accessToken !== null
            && time() < ($this->accessTokenExpiresAt - 60)
        ) {
            return $this->accessToken;
        }

        $credentials = $this->credentials();
        $now = time();
        $tokenUri = (string)($credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token');

        $header = $this->base64UrlEncode(json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
        ], JSON_THROW_ON_ERROR));

        $claims = $this->base64UrlEncode(json_encode([
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => $tokenUri,
            'iat' => $now,
            'exp' => $now + 3600,
        ], JSON_THROW_ON_ERROR));

        $unsignedJwt = $header . '.' . $claims;
        $signature = '';

        if (!openssl_sign(
            $unsignedJwt,
            $signature,
            $credentials['private_key'],
            OPENSSL_ALGO_SHA256
        )) {
            throw new RuntimeException('Unable to sign Firebase OAuth JWT.');
        }

        $jwt = $unsignedJwt . '.' . $this->base64UrlEncode($signature);

        $response = $this->request(
            $tokenUri,
            ['Content-Type: application/x-www-form-urlencoded'],
            http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ])
        );

        if ($response['status'] !== 200) {
            throw new RuntimeException(
                'Firebase OAuth request failed with HTTP ' . $response['status'] . '.'
            );
        }

        $decoded = json_decode($response['body'], true);

        if (!is_array($decoded) || empty($decoded['access_token'])) {
            throw new RuntimeException('Firebase OAuth response did not contain an access token.');
        }

        $this->accessToken = (string)$decoded['access_token'];
        $this->accessTokenExpiresAt = $now + max(60, (int)($decoded['expires_in'] ?? 3600));

        return $this->accessToken;
    }

    private function request(string $url, array $headers, string $body): array
    {
        $ch = curl_init($url);

        if ($ch === false) {
            throw new RuntimeException('Unable to initialize cURL.');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
        ]);

        $responseBody = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($responseBody === false) {
            throw new RuntimeException('FCM HTTP request failed: ' . $error);
        }

        return [
            'status' => $status,
            'body' => (string)$responseBody,
        ];
    }

    private function extractErrorCode(string $body): ?string
    {
        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            return null;
        }

        foreach (($decoded['error']['details'] ?? []) as $detail) {
            if (
                is_array($detail)
                && isset($detail['errorCode'])
                && is_string($detail['errorCode'])
            ) {
                return $detail['errorCode'];
            }
        }

        return isset($decoded['error']['status'])
            ? (string)$decoded['error']['status']
            : null;
    }

    private function normalizeData(array $data): array
    {
        $normalized = [];

        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }

            if (is_bool($value)) {
                $normalized[(string)$key] = $value ? 'true' : 'false';
                continue;
            }

            if (is_scalar($value)) {
                $normalized[(string)$key] = (string)$value;
                continue;
            }

            $normalized[(string)$key] = json_encode(
                $value,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        }

        return $normalized;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
