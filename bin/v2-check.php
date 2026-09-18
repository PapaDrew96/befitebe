#!/usr/bin/env php
<?php

declare(strict_types=1);

use Befit\Database\Database;
use Dotenv\Dotenv;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
Dotenv::createImmutable($root)->safeLoad();
$config = require $root . '/config/app.php';

$db = new Database($config['database']);
$pdo = $db->pdo();
$schema = $config['database']['name'];

$errors = [];
$requiredTables = ['waitlist_entries','password_reset_tokens','notifications'];
foreach ($requiredTables as $table) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=:schema AND table_name=:table');
    $stmt->execute(['schema'=>$schema,'table'=>$table]);
    if ((int)$stmt->fetchColumn() !== 1) $errors[] = "Missing table: {$table}";
}

$requiredColumns = [
    ['users','must_change_password'],
    ['bookings','checked_in_at'],
    ['bookings','attendance_marked_by_user_id'],
];
foreach ($requiredColumns as [$table,$column]) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=:schema AND table_name=:table AND column_name=:column');
    $stmt->execute(['schema'=>$schema,'table'=>$table,'column'=>$column]);
    if ((int)$stmt->fetchColumn() !== 1) $errors[] = "Missing column: {$table}.{$column}";
}

$requiredSettings = [
    'booking_days_ahead','booking_cutoff_minutes','cancellation_cutoff_minutes',
    'waitlist_enabled','auto_promote_waitlist','show_attendee_names',
];
$placeholders = implode(',', array_fill(0, count($requiredSettings), '?'));
$stmt = $pdo->prepare("SELECT setting_key FROM settings WHERE setting_key IN ({$placeholders})");
$stmt->execute($requiredSettings);
$found = $stmt->fetchAll(PDO::FETCH_COLUMN);
foreach ($requiredSettings as $key) {
    if (!in_array($key, $found, true)) $errors[] = "Missing setting: {$key}";
}

if ($errors !== []) {
    fwrite(STDERR, "BE-FIT V2 check FAILED:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

fwrite(STDOUT, "BE-FIT V2 database check OK.\n");
fwrite(STDOUT, "Database: {$schema}\n");
fwrite(STDOUT, "Required V2 tables, columns, and settings are present. Booking quantity limits are no longer required in V3.\n");
