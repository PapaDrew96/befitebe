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

$origins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) $env('CORS_ALLOWED_ORIGINS', ''))
)));

return [
    'app' => [
        'env' => (string) $env('APP_ENV', 'production'),
        'debug' => $bool($env('APP_DEBUG', false)),
        'timezone' => (string) $env('APP_TIMEZONE', 'Europe/Athens'),
        'base_path' => rtrim((string) $env('APP_BASE_PATH', ''), '/'),
        'url' => rtrim((string) $env('APP_URL', ''), '/'),
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
        'token_ttl_days' => max(1, (int) $env('TOKEN_TTL_DAYS', 30)),
        'login_rate_limit_attempts' => max(1, (int) $env('LOGIN_RATE_LIMIT_ATTEMPTS', 10)),
        'login_rate_limit_window_seconds' => max(60, (int) $env('LOGIN_RATE_LIMIT_WINDOW_SECONDS', 900)),
        'password_reset_ttl_minutes' => max(10, (int) $env('PASSWORD_RESET_TTL_MINUTES', 60)),
        'password_reset_rate_limit_attempts' => max(1, (int) $env('PASSWORD_RESET_RATE_LIMIT_ATTEMPTS', 5)),
        'password_reset_rate_limit_window_seconds' => max(60, (int) $env('PASSWORD_RESET_RATE_LIMIT_WINDOW_SECONDS', 900)),
        'password_reset_base_url' => rtrim((string) $env('PASSWORD_RESET_BASE_URL', 'http://befit.test:8082'), '/'),
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
        'allowed_origins' => $origins,
    ],
    'logging' => [
        'level' => (string) $env('LOG_LEVEL', 'info'),
        'file' => $root . '/' . ltrim((string) $env('LOG_FILE', 'storage/logs/app.log'), '/\\'),
    ],
];
