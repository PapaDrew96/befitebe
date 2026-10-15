<?php

declare(strict_types=1);

use Befit\Service\MailService;
use Befit\Support\MemberWelcomeEmail;
use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);

if (is_file($root . '/.env')) {
    Dotenv::createImmutable($root)->safeLoad();
}

$config = require $root . '/config/app.php';

$mail = new MailService(
    $config['mail']['enabled'],
    $config['mail']['host'],
    $config['mail']['port'],
    $config['mail']['encryption'],
    $config['mail']['username'],
    $config['mail']['password'],
    $config['mail']['from'],
    $config['mail']['from_name']
);

if (!$mail->isEnabled()) {
    fwrite(
        STDERR,
        "MAIL_ENABLED is false. Email was not sent.\n"
    );

    exit(1);
}

$to = 'andreaskaras20@gmail.com';

/*
 * Preview credentials only.
 * No user is created and nothing is written to the database.
 */
$previewEmail = 'member@example.com';
$previewPassword = 'BeFit!Preview2026';

echo "Sending BE-FIT welcome email preview...\n";
echo "Recipient: {$to}\n";

$sent = $mail->sendHtml(
    $to,
    MemberWelcomeEmail::subject(),
    MemberWelcomeEmail::html(
        $previewEmail,
        $previewPassword
    ),
    MemberWelcomeEmail::text(
        $previewEmail,
        $previewPassword
    )
);

if (!$sent) {
    fwrite(
        STDERR,
        "Email delivery failed. Check SMTP configuration/logs.\n"
    );

    exit(1);
}

echo "Email sent successfully.\n";