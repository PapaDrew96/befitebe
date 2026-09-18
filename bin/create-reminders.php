#!/usr/bin/env php
<?php

declare(strict_types=1);

use Befit\Database\Database;
use Dotenv\Dotenv;

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
Dotenv::createImmutable($root)->safeLoad();
$config = require $root . '/config/app.php';
date_default_timezone_set($config['app']['timezone']);

$db = new Database($config['database']);
$pdo = $db->pdo();
$from = new DateTimeImmutable('tomorrow 00:00:00');
$to = new DateTimeImmutable('tomorrow 23:59:59');

$stmt = $pdo->prepare("SELECT b.id AS booking_id,b.user_id,s.id AS session_id,s.session_date,s.start_time
    FROM bookings b INNER JOIN gym_sessions s ON s.id=b.session_id
    WHERE b.status='booked' AND TIMESTAMP(s.session_date,s.start_time) BETWEEN :from_dt AND :to_dt");
$stmt->execute(['from_dt'=>$from->format('Y-m-d H:i:s'),'to_dt'=>$to->format('Y-m-d H:i:s')]);
$rows=$stmt->fetchAll();
$created=0;
foreach($rows as $row){
    $needle='"booking_id":'.(int)$row['booking_id'];
    $check=$pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=:user_id AND type='session_reminder' AND JSON_UNQUOTE(JSON_EXTRACT(data,'$.booking_id'))=:booking_id");
    $check->execute(['user_id'=>$row['user_id'],'booking_id'=>(string)$row['booking_id']]);
    if((int)$check->fetchColumn()>0)continue;
    $insert=$pdo->prepare("INSERT INTO notifications (user_id,type,title,body,data) VALUES (:user_id,'session_reminder','Training reminder',:body,:data)");
    $insert->execute([
        'user_id'=>$row['user_id'],
        'body'=>'Your BE-FIT session is tomorrow at '.substr((string)$row['start_time'],0,5).'.',
        'data'=>json_encode(['booking_id'=>(int)$row['booking_id'],'session_id'=>(int)$row['session_id']],JSON_UNESCAPED_SLASHES),
    ]);
    $created++;
}
fwrite(STDOUT,"Created {$created} reminder notification(s).\n");
