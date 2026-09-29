# BE-FIT backend security v1 — production deployment

**Important:** do not replace the production `.env` with `.env.example`.

## 1. Back up code first

```bash
cd /var/www/vhosts/befittraining.gr
cp -a api.befittraining.gr api.befittraining.gr.backup-security-v1
```

A database backup before the deployment is also recommended. This release does not require a database migration.

## 2. Update `.env` BEFORE uploading the new PHP code

Generate a unique application key on the server:

```bash
/opt/plesk/php/8.3/bin/php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
```

Add the generated value privately to the existing `.env` and confirm these production values:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.befittraining.gr
APP_ALLOWED_HOSTS=api.befittraining.gr
APP_KEY=<generated value>
CORS_ALLOWED_ORIGINS=https://app.befittraining.gr
LOG_LEVEL=info
PASSWORD_RESET_BASE_URL=https://app.befittraining.gr

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
MAX_REQUEST_BODY_BYTES=65536
REQUIRE_JSON_WRITES=true
```

Keep your existing DB and SMTP credentials unchanged.

Do not set `TRUSTED_PROXIES` unless the server setup has been verified. Leaving it blank is safe.

Then protect `.env`:

```bash
chmod 600 .env
```

## 3. Upload the patch files

Upload the patch over `/var/www/vhosts/befittraining.gr/api.befittraining.gr/`, preserving paths.

Do not upload `.env.example` as `.env`.

## 4. PHP syntax check

```bash
cd /var/www/vhosts/befittraining.gr/api.befittraining.gr

find src bootstrap config routes public bin -type f -name '*.php' -print0 \
  | xargs -0 -n1 /opt/plesk/php/8.3/bin/php -l
```

Every file must report `No syntax errors detected`.

## 5. Application security check

```bash
/opt/plesk/php/8.3/bin/php bin/security-check.php
```

Production configuration must show zero failures.

## 6. API smoke tests

```bash
curl -si https://api.befittraining.gr/api/v1/health
```

Expected: HTTP 200 with generic status only.

```bash
curl -si \
  -H 'Origin: https://example.invalid' \
  https://api.befittraining.gr/api/v1/health
```

Expected: HTTP 403.

```bash
curl -si -X POST \
  -H 'Content-Type: text/plain' \
  --data 'test' \
  https://api.befittraining.gr/api/v1/auth/login
```

Expected: HTTP 415.

Then test a real login through the frontend and confirm member and admin pages still work.

## 7. Dependency audit

Use Composer with PHP 8.3, not the server's old `/usr/bin/php` if that still points to PHP 7.x.

First locate Composer:

```bash
which composer
```

Then run it with PHP 8.3, for example:

```bash
/opt/plesk/php/8.3/bin/php "$(which composer)" audit --locked
```

## 8. Daily security cleanup

Create a daily Plesk scheduled task:

```bash
/opt/plesk/php/8.3/bin/php /var/www/vhosts/befittraining.gr/api.befittraining.gr/bin/security-cleanup.php
```

## Rollback

If the API does not boot, restore the backed-up code directory. Do not remove or expose the production `.env` while troubleshooting.
