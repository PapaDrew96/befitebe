#!/usr/bin/env php
<?php

declare(strict_types=1);

use Befit\Database\Database;
use Befit\Repository\NotificationRepository;
use Befit\Repository\PushDeviceRepository;
use Befit\Security\ProductionSecurity;
use Befit\Service\FcmService;
use Befit\Service\NotificationContentService;
use Befit\Service\NotificationDispatcher;
use Befit\Service\PushNotificationService;
use Dotenv\Dotenv;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
Dotenv::createImmutable($root)->safeLoad();
$config = require $root . '/config/app.php';
ProductionSecurity::assertSafe($config);
date_default_timezone_set($config['app']['timezone']);

$logDirectory = dirname($config['logging']['file']);
if (!is_dir($logDirectory)) {
    mkdir($logDirectory, 0775, true);
}

$levels = [
    'debug' => Level::Debug,
    'info' => Level::Info,
    'notice' => Level::Notice,
    'warning' => Level::Warning,
    'error' => Level::Error,
    'critical' => Level::Critical,
    'alert' => Level::Alert,
    'emergency' => Level::Emergency,
];
$logLevel = $levels[strtolower($config['logging']['level'])] ?? Level::Info;
$logger = new Logger('befit-reminders');
$logger->pushHandler(new StreamHandler($config['logging']['file'], $logLevel));

$database = new Database($config['database']);
$pdo = $database->pdo();
$notificationRepository = new NotificationRepository($pdo);
$pushDeviceRepository = new PushDeviceRepository($pdo);
$fcmService = new FcmService(
    $config['push']['enabled'],
    $config['push']['firebase_credentials_path'],
    $config['push']['firebase_project_id'],
    $logger
);
$pushNotificationService = new PushNotificationService(
    $pushDeviceRepository,
    $fcmService,
    new NotificationContentService(),
    $logger
);
$notifications = new NotificationDispatcher(
    $database,
    $notificationRepository,
    $pushNotificationService
);

$from = new DateTimeImmutable('tomorrow 00:00:00');
$to = new DateTimeImmutable('tomorrow 23:59:59');

$stmt = $pdo->prepare(
    "SELECT b.id AS booking_id,b.user_id,s.id AS session_id,s.session_date,s.start_time,s.end_time
     FROM bookings b
     INNER JOIN gym_sessions s ON s.id=b.session_id
     WHERE b.status='booked'
       AND TIMESTAMP(s.session_date,s.start_time) BETWEEN :from_dt AND :to_dt"
);
$stmt->execute([
    'from_dt' => $from->format('Y-m-d H:i:s'),
    'to_dt' => $to->format('Y-m-d H:i:s'),
]);

$rows = $stmt->fetchAll();
$created = 0;

foreach ($rows as $row) {
    $check = $pdo->prepare(
        "SELECT COUNT(*)
         FROM notifications
         WHERE user_id=:user_id
           AND type='session_reminder'
           AND JSON_UNQUOTE(JSON_EXTRACT(data,'$.booking_id'))=:booking_id"
    );
    $check->execute([
        'user_id' => $row['user_id'],
        'booking_id' => (string)$row['booking_id'],
    ]);

    if ((int)$check->fetchColumn() > 0) {
        continue;
    }

    $startTime = substr((string)$row['start_time'], 0, 5);
    $endTime = !empty($row['end_time']) ? substr((string)$row['end_time'], 0, 5) : null;

    $notifications->create(
        (int)$row['user_id'],
        'session_reminder',
        'Training reminder',
        'Your BE-FIT session is tomorrow at ' . $startTime . '.',
        [
            'booking_id' => (int)$row['booking_id'],
            'session_id' => (int)$row['session_id'],
            'date' => $row['session_date'],
            'start_time' => $startTime,
            'end_time' => $endTime,
        ]
    );

    $created++;
}

fwrite(STDOUT, "Created {$created} reminder notification(s).\n");
