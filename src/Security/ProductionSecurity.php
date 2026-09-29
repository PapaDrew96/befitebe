<?php

declare(strict_types=1);

namespace Befit\Security;

use RuntimeException;

final class ProductionSecurity
{
    public static function assertSafe(array $config): void
    {
        if (($config['app']['env'] ?? '') !== 'production') {
            return;
        }

        $errors = [];

        if (!empty($config['app']['debug'])) {
            $errors[] = 'APP_DEBUG must be false in production.';
        }

        if (strlen((string) ($config['security']['app_key'] ?? '')) < 32) {
            $errors[] = 'APP_KEY must contain at least 32 characters in production.';
        }

        foreach (['url' => 'APP_URL'] as $key => $label) {
            $url = (string) ($config['app'][$key] ?? '');
            if (!self::isHttpsUrl($url)) {
                $errors[] = $label . ' must be a valid HTTPS URL in production.';
            }
        }

        $resetUrl = (string) ($config['auth']['password_reset_base_url'] ?? '');
        if (!self::isHttpsUrl($resetUrl)) {
            $errors[] = 'PASSWORD_RESET_BASE_URL must be a valid HTTPS URL in production.';
        }

        $origins = $config['cors']['allowed_origins'] ?? [];
        if ($origins === []) {
            $errors[] = 'CORS_ALLOWED_ORIGINS must contain the exact frontend HTTPS origin.';
        }
        if (in_array('*', $origins, true)) {
            $errors[] = 'Wildcard CORS origins are forbidden in production.';
        }
        foreach ($origins as $origin) {
            if (!self::isOrigin($origin, true)) {
                $errors[] = 'Every production CORS origin must be an HTTPS origin without a path.';
                break;
            }
        }

        if (($config['security']['allowed_hosts'] ?? []) === []) {
            $errors[] = 'APP_ALLOWED_HOSTS must contain the API hostname in production.';
        }

        if (strtolower((string) ($config['database']['user'] ?? '')) === 'root') {
            $errors[] = 'Production database access must not use the MySQL root account.';
        }

        if (strtolower((string) ($config['logging']['level'] ?? '')) === 'debug') {
            $errors[] = 'LOG_LEVEL must not be debug in production.';
        }

        if (!empty($config['mail']['enabled'])) {
            $enc = strtolower((string) ($config['mail']['encryption'] ?? ''));
            if (!in_array($enc, ['ssl', 'smtps', 'tls', 'starttls'], true)) {
                $errors[] = 'Encrypted SMTP (TLS/SMTPS) is required when mail is enabled in production.';
            }
        }

        if ($errors !== []) {
            throw new RuntimeException("Unsafe production configuration:\n- " . implode("\n- ", $errors));
        }
    }

    private static function isHttpsUrl(string $value): bool
    {
        $parts = parse_url($value);
        return is_array($parts)
            && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && !empty($parts['host']);
    }

    private static function isOrigin(string $value, bool $httpsOnly): bool
    {
        $parts = parse_url($value);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }
        if ($httpsOnly && strtolower((string) $parts['scheme']) !== 'https') {
            return false;
        }
        return empty($parts['path']) || $parts['path'] === '/'
            ? !isset($parts['query']) && !isset($parts['fragment'])
            : false;
    }
}
