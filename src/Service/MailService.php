<?php

declare(strict_types=1);

namespace Befit\Service;

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

final class MailService
{
    public function __construct(
        private readonly bool $enabled,
        private readonly string $host,
        private readonly int $port,
        private readonly string $encryption,
        private readonly string $username,
        private readonly string $password,
        private readonly string $from,
        private readonly string $fromName
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function sendText(string $to, string $subject, string $body): bool
    {
        if (!$this->enabled || trim($to) === '') {
            return false;
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $this->host;
            $mail->Port = $this->port;
            $mail->SMTPAuth = true;
            $mail->Username = $this->username;
            $mail->Password = $this->password;
            $mail->CharSet = 'UTF-8';
            $mail->Timeout = 20;

            $encryption = strtolower(trim($this->encryption));

            if ($encryption === 'ssl' || $encryption === 'smtps') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($encryption === 'tls' || $encryption === 'starttls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
            }

            $mail->setFrom($this->from, $this->fromName);
            $mail->addAddress($to);

            $mail->isHTML(false);
            $mail->Subject = $subject;
            $mail->Body = $body;

            return $mail->send();
        } catch (Exception $exception) {
            error_log(
                'BE-FIT mail delivery failed for ' . $to . ': ' . $exception->getMessage()
            );

            return false;
        }
    }
}
