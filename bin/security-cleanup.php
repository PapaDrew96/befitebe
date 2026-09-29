#!/usr/bin/env php
<?php

declare(strict_types=1);

use Befit\Database\Database;
use Befit\Security\ProductionSecurity;
use Dotenv\Dotenv;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
Dotenv::createImmutable($root)->safeLoad();
$config = require $root . '/config/app.php';
ProductionSecurity::assertSafe($config);

$database = new Database($config['database']);
$pdo = $database->pdo();

$deletedTokens = $pdo->exec('DELETE FROM api_tokens WHERE expires_at <= NOW()');
$deletedResets = $pdo->exec(
    "DELETE FROM password_reset_tokens
     WHERE expires_at <= NOW()
        OR (used_at IS NOT NULL AND used_at < DATE_SUB(NOW(), INTERVAL 7 DAY))"
);
$deletedLimits = $pdo->exec(
    'DELETE FROM rate_limits WHERE updated_at < DATE_SUB(NOW(), INTERVAL 2 DAY)'
);

fwrite(STDOUT, sprintf(
    "Security cleanup completed: %d expired sessions, %d reset tokens, %d stale rate-limit buckets removed.\n",
    (int) $deletedTokens,
    (int) $deletedResets,
    (int) $deletedLimits
));
