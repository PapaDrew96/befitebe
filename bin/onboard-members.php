<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$file = $root . '/storage/import/fitmails.txt';

if (!is_file($file)) {
    fwrite(STDERR, "Email file not found:\n{$file}\n");
    exit(1);
}

$content = file_get_contents($file);

if ($content === false) {
    fwrite(STDERR, "Unable to read email file.\n");
    exit(1);
}

/*
 * Accept comma-separated emails, including whitespace/newlines.
 */
$rawEmails = preg_split('/\s*,\s*/', trim($content));

if (!is_array($rawEmails)) {
    fwrite(STDERR, "Unable to parse email file.\n");
    exit(1);
}

$valid = [];
$invalid = [];

foreach ($rawEmails as $email) {
    $email = strtolower(trim($email));

    if ($email === '') {
        continue;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $invalid[] = $email;
        continue;
    }

    $valid[] = $email;
}

/*
 * Remove duplicates.
 */
$valid = array_values(array_unique($valid));

/*
 * Alphabetical, case-insensitive order.
 */
natcasesort($valid);
$valid = array_values($valid);

/*
 * CLI options:
 *
 * --limit=10
 * --offset=0
 */
$options = getopt('', [
    'limit::',
    'offset::',
]);

$limit = isset($options['limit'])
    ? max(1, (int) $options['limit'])
    : 10;

$offset = isset($options['offset'])
    ? max(0, (int) $options['offset'])
    : 0;

$batch = array_slice(
    $valid,
    $offset,
    $limit
);

echo "BE-FIT member onboarding dry run\n";
echo "===============================\n\n";

echo 'Valid unique emails: ' . count($valid) . PHP_EOL;
echo 'Invalid emails:      ' . count($invalid) . PHP_EOL;
echo 'Offset:              ' . $offset . PHP_EOL;
echo 'Batch size:          ' . count($batch) . PHP_EOL;
echo PHP_EOL;

if ($invalid !== []) {
    echo "Invalid entries:\n";

    foreach ($invalid as $email) {
        echo "  - {$email}\n";
    }

    echo PHP_EOL;
}

if ($batch === []) {
    echo "No emails in this batch.\n";
    exit(0);
}

echo "Emails in current batch:\n\n";

foreach ($batch as $index => $email) {
    $position = $offset + $index + 1;

    printf(
        "%4d. %s\n",
        $position,
        $email
    );
}

echo PHP_EOL;

$nextOffset = $offset + count($batch);

if ($nextOffset < count($valid)) {
    echo "Next batch:\n";
    echo "php bin/onboard-members.php --limit={$limit} --offset={$nextOffset}\n";
} else {
    echo "End of list.\n";
}