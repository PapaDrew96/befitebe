# BE-FIT production checklist

## Required security configuration

- Serve frontend and API only over HTTPS.
- Backend document root must point only to `befit-backend/public`.
- `APP_ENV=production`.
- `APP_DEBUG=false`.
- `APP_URL=https://api.befittraining.gr`.
- `APP_ALLOWED_HOSTS=api.befittraining.gr`.
- `CORS_ALLOWED_ORIGINS=https://app.befittraining.gr` and never `*`.
- `PASSWORD_RESET_BASE_URL=https://app.befittraining.gr`.
- Generate a unique `APP_KEY` with at least 32 characters and keep it secret.
- Use a dedicated MySQL user limited to the BE-FIT database; never use MySQL `root`.
- Use `LOG_LEVEL=info` or stricter in production.
- Use encrypted SMTP (`tls`, `starttls`, `ssl` or `smtps`) when mail is enabled.
- Keep `.env` outside the public document root and set permissions to `600` when possible.
- Do not commit `.env`, logs or database backups.

Run:

```bash
/opt/plesk/php/8.3/bin/php bin/security-check.php
composer audit --locked
```

## Authentication defaults recommended for production

```dotenv
TOKEN_TTL_DAYS=7
TOKEN_IDLE_TTL_MINUTES=1440
TOKEN_MAX_ACTIVE_PER_USER=5
LOGIN_RATE_LIMIT_ATTEMPTS=10
LOGIN_IP_RATE_LIMIT_ATTEMPTS=30
LOGIN_RATE_LIMIT_WINDOW_SECONDS=900
PASSWORD_MIN_LENGTH=12
PASSWORD_RESET_TTL_MINUTES=30
PASSWORD_RESET_RATE_LIMIT_ATTEMPTS=5
PASSWORD_RESET_IP_RATE_LIMIT_ATTEMPTS=20
PASSWORD_RESET_RATE_LIMIT_WINDOW_SECONDS=900
```

- Remove local test users and temporary passwords.
- Revoke old/test Bearer tokens before launch.
- Password changes/resets revoke all existing sessions for that user.

## Server / PHP

- Use PHP 8.3 through the Plesk PHP binary for CLI jobs.
- Disable `display_errors` and `expose_php` in production PHP settings.
- Keep OPcache enabled.
- Keep Plesk/nginx/Apache/PHP/MariaDB patched.
- Do not expose MySQL to the public Internet unless there is a documented need and firewall allowlisting.
- Restrict SSH and Plesk administration by strong passwords/keys and MFA where available.

## Dependencies

- Deploy from `composer.lock`.
- Use production install flags:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader --classmap-authoritative
```

- Run `composer audit --locked` on every deployment and regularly thereafter.

## Password recovery / email

- Reset tokens are random, stored only as SHA-256 hashes and expire.
- Reset tokens are never returned by the API when `APP_DEBUG=false`.
- Password reset invalidates all active API sessions for the account.
- Test SMTP delivery before relying on password recovery.

## Privacy

- Keep `show_attendee_names=false` unless explicitly approved by the gym.
- Collect only required member data.
- Define retention for activity logs, attendance records and backups.
- Limit access to exports/reports to administrators who require it.

## Scheduled security maintenance

Run daily:

```bash
/opt/plesk/php/8.3/bin/php /var/www/vhosts/befittraining.gr/api.befittraining.gr/bin/security-cleanup.php
```

Continue nightly encrypted/off-host database backups and periodically perform restore tests.

## Monitoring

- Monitor `storage/logs/app.log` without exposing it through the web server.
- Monitor disk space and backup completion.
- Investigate repeated 401/403/429 responses.
- Confirm `/api/v1/health` returns HTTP 200 without exposing internal database details.
