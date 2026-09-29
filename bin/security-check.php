#!/usr/bin/env php
<?php

declare(strict_types=1);

use Befit\Security\ProductionSecurity;
use Dotenv\Dotenv;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
Dotenv::createImmutable($root)->safeLoad();
$config = require $root . '/config/app.php';

$failures = 0;
$warnings = 0;

$pass = static function (string $message): void {
    fwrite(STDOUT, "[PASS] {$message}\n");
};
$fail = static function (string $message) use (&$failures): void {
    $failures++;
    fwrite(STDOUT, "[FAIL] {$message}\n");
};
$warn = static function (string $message) use (&$warnings): void {
    $warnings++;
    fwrite(STDOUT, "[WARN] {$message}\n");
};

fwrite(STDOUT, "BE-FIT security check\n=====================\n");

if (PHP_VERSION_ID >= 80100) {
    $pass('PHP version is supported: ' . PHP_VERSION);
} else {
    $fail('PHP 8.1+ is required.');
}

if (defined('PASSWORD_ARGON2ID')) {
    $pass('Argon2id password hashing is available.');
} else {
    $warn('Argon2id is unavailable; bcrypt fallback will be used.');
}

try {
    ProductionSecurity::assertSafe($config);
    if ($config['app']['env'] === 'production') {
        $pass('Production configuration passes fail-closed security validation.');
    } else {
        $warn('APP_ENV is not production; production-only guards are not active.');
    }
} catch (Throwable $e) {
    foreach (explode("\n", $e->getMessage()) as $line) {
        if ($line !== '') {
            $fail($line);
        }
    }
}

$envFile = $root . '/.env';
if (!is_file($envFile)) {
    $warn('.env file was not found.');
} else {
    $perms = fileperms($envFile);
    if ($perms !== false) {
        $mode = $perms & 0777;
        if (($mode & 0007) !== 0) {
            $fail('.env is accessible by other users; recommended mode is 600.');
        } elseif (($mode & 0070) !== 0) {
            $warn('.env is group-accessible; mode 600 is preferred.');
        } else {
            $pass('.env permissions are restrictive.');
        }
    }
}

$public = realpath($root . '/public');
if ($public !== false) {
    $pass('Public document-root directory exists. Configure the web server document root to: ' . $public);
}

if (is_file($root . '/composer.lock')) {
    $pass('composer.lock exists; run composer audit --locked during deployment.');
} else {
    $fail('composer.lock is missing.');
}

fwrite(STDOUT, "\nResult: {$failures} failure(s), {$warnings} warning(s).\n");
exit($failures > 0 ? 1 : 0);
