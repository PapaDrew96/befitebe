<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$env = static function (string $key, mixed $default = null): mixed {
    return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
};

$bool = static function (mixed $value, bool $default = false): bool {
    if ($value === null || $value === '') {
        return $default;
    }

    return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
};

$list = static function (mixed $value): array {
    return array_values(array_filter(array_map(
        'trim',
        explode(',', (string) $value)
    ), static fn (string $item): bool => $item !== ''));
};

$appUrl = rtrim((string) $env('APP_URL', ''), '/');
$defaultHost = parse_url($appUrl, PHP_URL_HOST);
$allowedHosts = $list($env('APP_ALLOWED_HOSTS', is_string($defaultHost) ? $defaultHost : ''));
$allowedHosts = array_values(array_unique(array_map(
    static fn (string $host): string => strtolower(rtrim($host, '.')),
    $allowedHosts
)));

return [
    'app' => [
        'env' => (string) $env('APP_ENV', 'production'),
        'debug' => $bool($env('APP_DEBUG', false)),
        'timezone' => (string) $env('APP_TIMEZONE', 'Europe/Athens'),
        'base_path' => rtrim((string) $env('APP_BASE_PATH', ''), '/'),
        'url' => $appUrl,
    ],
    'database' => [
        'host' => (string) $env('DB_HOST', '127.0.0.1'),
        'port' => (int) $env('DB_PORT', 3306),
        'name' => (string) $env('DB_NAME', 'befit_app'),
        'user' => (string) $env('DB_USER', 'root'),
        'pass' => (string) $env('DB_PASS', ''),
        'charset' => (string) $env('DB_CHARSET', 'utf8mb4'),
    ],
    'auth' => [
        'token_ttl_days' => max(1, (int) $env('TOKEN_TTL_DAYS', 7)),
        'token_idle_ttl_minutes' => max(15, (int) $env('TOKEN_IDLE_TTL_MINUTES', 1440)),
        'token_max_active_per_user' => min(20, max(1, (int) $env('TOKEN_MAX_ACTIVE_PER_USER', 5))),

        // Keep the legacy setting as the default account/subject limit.
        'login_rate_limit_attempts' => max(1, (int) $env('LOGIN_RATE_LIMIT_ATTEMPTS', 10)),
        'login_ip_rate_limit_attempts' => max(1, (int) $env('LOGIN_IP_RATE_LIMIT_ATTEMPTS', 30)),
        'login_rate_limit_window_seconds' => max(60, (int) $env('LOGIN_RATE_LIMIT_WINDOW_SECONDS', 900)),

        'password_reset_ttl_minutes' => max(10, (int) $env('PASSWORD_RESET_TTL_MINUTES', 30)),
        'password_reset_rate_limit_attempts' => max(1, (int) $env('PASSWORD_RESET_RATE_LIMIT_ATTEMPTS', 5)),
        'password_reset_ip_rate_limit_attempts' => max(1, (int) $env('PASSWORD_RESET_IP_RATE_LIMIT_ATTEMPTS', 20)),
        'password_reset_rate_limit_window_seconds' => max(60, (int) $env('PASSWORD_RESET_RATE_LIMIT_WINDOW_SECONDS', 900)),
        'password_reset_base_url' => rtrim((string) $env('PASSWORD_RESET_BASE_URL', 'http://befit.test:8082'), '/'),

        'password_min_length' => min(64, max(12, (int) $env('PASSWORD_MIN_LENGTH', 12))),
        'argon_memory_kb' => max(32768, (int) $env('PASSWORD_ARGON_MEMORY_KB', 65536)),
        'argon_time_cost' => max(2, (int) $env('PASSWORD_ARGON_TIME_COST', 4)),
        'argon_threads' => max(1, (int) $env('PASSWORD_ARGON_THREADS', 2)),
        'bcrypt_cost' => min(15, max(10, (int) $env('PASSWORD_BCRYPT_COST', 12))),
    ],
    'mail' => [
        'enabled' => $bool($env('MAIL_ENABLED', false)),
        'host' => (string) $env('MAIL_HOST', ''),
        'port' => (int) $env('MAIL_PORT', 465),
        'encryption' => (string) $env('MAIL_ENCRYPTION', 'ssl'),
        'username' => (string) $env('MAIL_USERNAME', ''),
        'password' => (string) $env('MAIL_PASSWORD', ''),
        'from' => (string) $env('MAIL_FROM', 'no-reply@befit.local'),
        'from_name' => (string) $env('MAIL_FROM_NAME', 'BE-FIT Training Center'),
    ],
    'schedule' => [
        'max_days_ahead' => max(7, (int) $env('SCHEDULE_MAX_DAYS_AHEAD', 120)),
    ],
    'cors' => [
        'allowed_origins' => $list($env('CORS_ALLOWED_ORIGINS', '')),
    ],
    'security' => [
        'app_key' => (string) $env('APP_KEY', ''),
        'allowed_hosts' => $allowedHosts,
        'trusted_proxies' => $list($env('TRUSTED_PROXIES', '')),
        'max_request_body_bytes' => max(4096, (int) $env('MAX_REQUEST_BODY_BYTES', 65536)),
        'require_json_writes' => $bool($env('REQUIRE_JSON_WRITES', true), true),
    ],
    'logging' => [
        'level' => (string) $env('LOG_LEVEL', 'info'),
        'file' => $root . '/' . ltrim((string) $env('LOG_FILE', 'storage/logs/app.log'), '/\\'),
    ],
];
