#!/usr/bin/env php
<?php

declare(strict_types=1);

use Befit\Database\Database;
use Befit\Repository\UserRepository;
use Dotenv\Dotenv;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
Dotenv::createImmutable($root)->safeLoad();
$config = require $root . '/config/app.php';
date_default_timezone_set($config['app']['timezone']);

$options = getopt('', ['first:', 'last:', 'email:', 'phone::', 'password:']);
if (empty($options['first']) || empty($options['last']) || empty($options['email']) || empty($options['password'])) {
    fwrite(STDERR, "Usage: php bin/create-admin.php --first=Gym --last=Owner --email=owner@example.com --password=StrongPassword [--phone=6900000000]\n");
    exit(1);
}
if (strlen((string) $options['password']) < 8) {
    fwrite(STDERR, "Password must be at least 8 characters.\n");
    exit(1);
}
if (filter_var($options['email'], FILTER_VALIDATE_EMAIL) === false) {
    fwrite(STDERR, "Email is not valid.\n");
    exit(1);
}

$database = new Database($config['database']);
$users = new UserRepository($database->pdo());

try {
    $id = $users->create([
        'role' => 'admin',
        'first_name' => trim((string) $options['first']),
        'last_name' => trim((string) $options['last']),
        'email' => trim((string) $options['email']),
        'phone' => isset($options['phone']) && trim((string) $options['phone']) !== '' ? trim((string) $options['phone']) : null,
        'password_hash' => password_hash((string) $options['password'], PASSWORD_DEFAULT),
        'status' => 'active',
        'must_change_password' => 0,
    ]);
    fwrite(STDOUT, "Administrator created successfully. User ID: {$id}\n");
} catch (Throwable $e) {
    fwrite(STDERR, "Could not create administrator: {$e->getMessage()}\n");
    exit(1);
}
