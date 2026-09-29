#!/usr/bin/env php
<?php

declare(strict_types=1);

use Befit\Database\Database;
use Befit\Repository\UserRepository;
use Befit\Security\PasswordHasher;
use Befit\Security\PasswordPolicy;
use Befit\Security\ProductionSecurity;
use Dotenv\Dotenv;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
Dotenv::createImmutable($root)->safeLoad();
$config = require $root . '/config/app.php';
ProductionSecurity::assertSafe($config);
date_default_timezone_set($config['app']['timezone']);

$options = getopt('', ['first:', 'last:', 'email:', 'phone::', 'password:']);
if (empty($options['first']) || empty($options['last']) || empty($options['email']) || empty($options['password'])) {
    fwrite(STDERR, "Usage: php bin/create-admin.php --first=Gym --last=Owner --email=owner@example.com --password='StrongRandomPassword' [--phone=6900000000]\n");
    exit(1);
}

if (filter_var($options['email'], FILTER_VALIDATE_EMAIL) === false) {
    fwrite(STDERR, "Email is not valid.\n");
    exit(1);
}

$policy = new PasswordPolicy($config['auth']['password_min_length']);
$errors = $policy->errors((string) $options['password']);
if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, $error . PHP_EOL);
    }
    exit(1);
}

$hasher = new PasswordHasher(
    $config['auth']['argon_memory_kb'],
    $config['auth']['argon_time_cost'],
    $config['auth']['argon_threads'],
    $config['auth']['bcrypt_cost']
);
$database = new Database($config['database']);
$users = new UserRepository($database->pdo());

try {
    $id = $users->create([
        'role' => 'admin',
        'first_name' => trim((string) $options['first']),
        'last_name' => trim((string) $options['last']),
        'email' => mb_strtolower(trim((string) $options['email'])),
        'phone' => isset($options['phone']) && trim((string) $options['phone']) !== ''
            ? trim((string) $options['phone'])
            : null,
        'password_hash' => $hasher->hash((string) $options['password']),
        'status' => 'active',
        'must_change_password' => 0,
    ]);
    fwrite(STDOUT, "Administrator created successfully. User ID: {$id}\n");
} catch (Throwable $e) {
    fwrite(STDERR, "Could not create administrator.\n");
    exit(1);
}
