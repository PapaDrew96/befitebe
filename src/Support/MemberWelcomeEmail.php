<?php

declare(strict_types=1);

namespace Befit\Support;

final class MemberWelcomeEmail
{
    public static function subject(): string
    {
        return 'Καλώς ήρθες στο BE-FIT | Welcome to BE-FIT';
    }

    public static function html(
        string $email,
        string $password
    ): string {
        $safeEmail = htmlspecialchars(
            $email,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );

        $safePassword = htmlspecialchars(
            $password,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );

        return <<<HTML
<!doctype html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BE-FIT</title>
</head>

<body style="
    margin:0;
    padding:0;
    background-color:#f6f7f9;
    color:#1f2328;
    font-family:Arial,Helvetica,sans-serif;
    -webkit-text-size-adjust:100%;
">

<!-- Preheader -->
<div style="
    display:none;
    max-height:0;
    overflow:hidden;
    opacity:0;
    color:transparent;
">
    Ο λογαριασμός σου στο BE-FIT είναι έτοιμος.
    Your BE-FIT account is ready.
</div>

<table
    role="presentation"
    width="100%"
    cellspacing="0"
    cellpadding="0"
    border="0"
    style="
        width:100%;
        background-color:#f6f7f9;
        margin:0;
        padding:0;
    "
>
    <tr>
        <td
            align="center"
            style="padding:40px 16px;"
        >

            <table
                role="presentation"
                width="100%"
                cellspacing="0"
                cellpadding="0"
                border="0"
                style="
                    width:100%;
                    max-width:620px;
                    background-color:#ffffff;
                    border:1px solid #e5e7eb;
                    border-radius:24px;
                    overflow:hidden;
                "
            >

                <!-- Header -->
                <tr>
                    <td
                        align="center"
                        style="
                            padding:38px 28px 34px;
                            background-color:#db5726;
                        "
                    >
                        <div
                            style="
                                margin:0;
                                color:#ffffff;
                                font-size:34px;
                                line-height:40px;
                                font-weight:800;
                                letter-spacing:1.5px;
                            "
                        >
                            BE-FIT
                        </div>

                        <div
                            style="
                                margin-top:8px;
                                color:#fff0e9;
                                font-size:14px;
                                line-height:21px;
                                font-weight:600;
                            "
                        >
                            TRAINING CENTER
                        </div>
                    </td>
                </tr>

                <!-- Greek intro -->
                <tr>
                    <td style="padding:38px 38px 0;">

                        <div
                            style="
                                display:inline-block;
                                margin-bottom:14px;
                                padding:6px 10px;
                                background-color:#fff0e9;
                                color:#ad3f18;
                                border-radius:999px;
                                font-size:11px;
                                line-height:16px;
                                font-weight:700;
                                letter-spacing:.7px;
                            "
                        >
                            ΚΑΛΩΣ ΗΡΘΕΣ
                        </div>

                        <h1
                            style="
                                margin:0 0 14px;
                                color:#1f2328;
                                font-size:27px;
                                line-height:35px;
                                font-weight:700;
                            "
                        >
                            Ο λογαριασμός σου είναι έτοιμος.
                        </h1>

                        <p
                            style="
                                margin:0;
                                color:#737b85;
                                font-size:16px;
                                line-height:25px;
                            "
                        >
                            Καλώς ήρθες στο BE-FIT.
                            Μπορείς πλέον να συνδεθείς στην εφαρμογή
                            και να διαχειρίζεσαι εύκολα τις κρατήσεις
                            των προπονήσεών σου.
                        </p>

                    </td>
                </tr>

                <!-- Credentials -->
                <tr>
                    <td style="padding:30px 38px 0;">

                        <table
                            role="presentation"
                            width="100%"
                            cellspacing="0"
                            cellpadding="0"
                            border="0"
                            style="
                                width:100%;
                                background-color:#fff8f4;
                                border:1px solid #fff0e9;
                                border-radius:16px;
                            "
                        >
                            <tr>
                                <td style="padding:26px;">

                                    <div
                                        style="
                                            margin:0 0 7px;
                                            color:#737b85;
                                            font-size:11px;
                                            line-height:16px;
                                            font-weight:700;
                                            letter-spacing:1px;
                                        "
                                    >
                                        EMAIL
                                    </div>

                                    <div
                                        style="
                                            color:#1f2328;
                                            font-size:16px;
                                            line-height:24px;
                                            font-weight:700;
                                            word-break:break-word;
                                        "
                                    >
                                        {$safeEmail}
                                    </div>

                                    <div
                                        style="
                                            height:1px;
                                            margin:22px 0;
                                            background-color:#e5e7eb;
                                        "
                                    ></div>

                                    <div
                                        style="
                                            margin:0 0 7px;
                                            color:#737b85;
                                            font-size:11px;
                                            line-height:16px;
                                            font-weight:700;
                                            letter-spacing:1px;
                                        "
                                    >
                                        ΚΩΔΙΚΟΣ ΠΡΟΣΒΑΣΗΣ / PASSWORD
                                    </div>

                                    <div
                                        style="
                                            color:#1f2328;
                                            font-family:Consolas,'Courier New',monospace;
                                            font-size:19px;
                                            line-height:27px;
                                            font-weight:700;
                                            letter-spacing:.4px;
                                            word-break:break-all;
                                        "
                                    >
                                        {$safePassword}
                                    </div>

                                </td>
                            </tr>
                        </table>

                    </td>
                </tr>

                <!-- CTA -->
                <tr>
                    <td
                        align="center"
                        style="padding:30px 38px 14px;"
                    >

                        <table
                            role="presentation"
                            cellspacing="0"
                            cellpadding="0"
                            border="0"
                        >
                            <tr>
                                <td
                                    align="center"
                                    bgcolor="#db5726"
                                    style="
                                        border-radius:12px;
                                        background-color:#db5726;
                                    "
                                >
                                    <a
                                        href="https://app.befittraining.gr"
                                        target="_blank"
                                        style="
                                            display:inline-block;
                                            padding:16px 30px;
                                            color:#ffffff;
                                            font-size:15px;
                                            line-height:20px;
                                            font-weight:700;
                                            text-decoration:none;
                                            border-radius:12px;
                                        "
                                    >
                                        ΑΝΟΙΓΜΑ BE-FIT &nbsp; / &nbsp; OPEN BE-FIT
                                    </a>
                                </td>
                            </tr>
                        </table>

                    </td>
                </tr>

                <!-- Greek note -->
                <tr>
                    <td style="padding:10px 38px 30px;">

                        <p
                            style="
                                margin:0;
                                color:#737b85;
                                font-size:13px;
                                line-height:21px;
                                text-align:center;
                            "
                        >
                            Χρησιμοποίησε το email και τον κωδικό
                            πρόσβασης που εμφανίζονται παραπάνω
                            για να συνδεθείς.
                        </p>

                    </td>
                </tr>

                <!-- Separator -->
                <tr>
                    <td style="padding:0 38px;">
                        <div
                            style="
                                height:1px;
                                background-color:#e5e7eb;
                            "
                        ></div>
                    </td>
                </tr>

                <!-- English -->
                <tr>
                    <td style="padding:30px 38px 38px;">

                        <div
                            style="
                                display:inline-block;
                                margin-bottom:14px;
                                padding:6px 10px;
                                background-color:#fff0e9;
                                color:#ad3f18;
                                border-radius:999px;
                                font-size:11px;
                                line-height:16px;
                                font-weight:700;
                                letter-spacing:.7px;
                            "
                        >
                            WELCOME
                        </div>

                        <h2
                            style="
                                margin:0 0 12px;
                                color:#1f2328;
                                font-size:22px;
                                line-height:30px;
                                font-weight:700;
                            "
                        >
                            Your BE-FIT account is ready.
                        </h2>

                        <p
                            style="
                                margin:0;
                                color:#737b85;
                                font-size:15px;
                                line-height:24px;
                            "
                        >
                            Welcome to BE-FIT.
                            You can now sign in to the application
                            and manage your training bookings easily.
                            Use the email address and password shown
                            above to access your account.
                        </p>

                    </td>
                </tr>

                <!-- Security note -->
                <tr>
                    <td
                        style="
                            padding:22px 38px;
                            background-color:#fff8f4;
                            border-top:1px solid #fff0e9;
                        "
                    >
                        <table
                            role="presentation"
                            width="100%"
                            cellspacing="0"
                            cellpadding="0"
                            border="0"
                        >
                            <tr>
                                <td
                                    valign="top"
                                    style="
                                        width:32px;
                                        color:#db5726;
                                        font-size:20px;
                                        line-height:24px;
                                    "
                                >
                                    ●
                                </td>

                                <td>
                                    <div
                                        style="
                                            margin-bottom:4px;
                                            color:#1f2328;
                                            font-size:13px;
                                            line-height:19px;
                                            font-weight:700;
                                        "
                                    >
                                        Κράτησε τα στοιχεία σου ασφαλή.
                                    </div>

                                    <div
                                        style="
                                            color:#737b85;
                                            font-size:12px;
                                            line-height:19px;
                                        "
                                    >
                                        Μην κοινοποιείς τον κωδικό πρόσβασής σου.
                                        <br>
                                        Keep your login credentials private and secure.
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <!-- Footer -->
                <tr>
                    <td
                        align="center"
                        style="
                            padding:26px 30px;
                            background-color:#1f2328;
                        "
                    >
                        <div
                            style="
                                color:#ffffff;
                                font-size:15px;
                                line-height:21px;
                                font-weight:700;
                            "
                        >
                            BE-FIT Training Center
                        </div>

                        <div
                            style="
                                margin-top:6px;
                                color:#9aa1aa;
                                font-size:11px;
                                line-height:18px;
                            "
                        >
                            Αυτό το email αφορά τον προσωπικό σου
                            λογαριασμό στο BE-FIT.
                            <br>
                            This email contains information about
                            your personal BE-FIT account.
                        </div>

                        <div style="margin-top:14px;">
                            <a
                                href="https://app.befittraining.gr"
                                target="_blank"
                                style="
                                    color:#db5726;
                                    font-size:12px;
                                    line-height:18px;
                                    font-weight:700;
                                    text-decoration:none;
                                "
                            >
                                app.befittraining.gr
                            </a>
                        </div>
                    </td>
                </tr>

            </table>

        </td>
    </tr>
</table>

</body>
</html>
HTML;
    }

    public static function text(
        string $email,
        string $password
    ): string {
        return <<<TEXT
BE-FIT TRAINING CENTER

Καλώς ήρθες στο BE-FIT!

Ο λογαριασμός σου είναι έτοιμος.
Μπορείς πλέον να συνδεθείς στην εφαρμογή και να διαχειρίζεσαι τις κρατήσεις των προπονήσεών σου.

Email:
{$email}

Κωδικός πρόσβασης:
{$password}

Άνοιγμα BE-FIT:
https://app.befittraining.gr

Χρησιμοποίησε το email και τον κωδικό πρόσβασης παραπάνω για να συνδεθείς.

Κράτησε τα στοιχεία σύνδεσής σου ασφαλή και μην κοινοποιείς τον κωδικό πρόσβασής σου.


----------------------------------------


WELCOME TO BE-FIT

Your BE-FIT account is ready.

You can now sign in to the application and manage your training bookings.

Email:
{$email}

Password:
{$password}

Open BE-FIT:
https://app.befittraining.gr

Use the email address and password above to sign in.

Keep your login credentials private and secure.


BE-FIT Training Center
TEXT;
    }
}