# BE-FIT backend security hardening v1

This update hardens the existing Bearer-token API without changing the public endpoint URLs or requiring a database migration.

## Main changes

- Production starts in fail-closed mode when critical security configuration is unsafe.
- Passwords use Argon2id when available, with bcrypt cost 12 as a fallback.
- Existing password hashes are transparently rehashed after the next successful login when stronger hashing is available.
- New/reset passwords require at least 12 characters with uppercase, lowercase and a number.
- Login timing for unknown accounts performs a dummy password verification.
- Authentication is rate-limited independently by client IP and login identity.
- Forgot-password is rate-limited independently by client IP and login identity.
- Reset-password is rate-limited by client IP.
- Rate-limit keys are HMACed with `APP_KEY` so login identities are not stored as reversible/dictionary-friendly hashes.
- Bearer sessions now support an idle timeout and a cap on concurrent active tokens per user.
- Expired tokens are removed during login and old active sessions are trimmed.
- Disallowed browser origins receive HTTP 403 instead of silently receiving a response without CORS headers.
- Write requests with a body must use JSON.
- Oversized API bodies are rejected before controller logic.
- TRACE/TRACK/CONNECT and HTTP method-override headers are rejected.
- Host-header allowlisting is supported and enforced.
- Production error responses no longer expose Slim exception details.
- The public health endpoint no longer exposes database component details.
- Security headers include HSTS, CSP, Permissions-Policy and no-store directives.
- `.htaccess` adds defense-in-depth response headers and hidden-file protection.
- Security maintenance and validation CLI commands are included.

## Required production `.env` values

Do not copy the example values literally. Keep the existing database/mail secrets and add or update the following values:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.befittraining.gr
APP_ALLOWED_HOSTS=api.befittraining.gr
CORS_ALLOWED_ORIGINS=https://app.befittraining.gr
LOG_LEVEL=info

APP_KEY=<unique random secret, at least 32 characters>

TOKEN_TTL_DAYS=7
TOKEN_IDLE_TTL_MINUTES=1440
TOKEN_MAX_ACTIVE_PER_USER=5

LOGIN_RATE_LIMIT_ATTEMPTS=10
LOGIN_IP_RATE_LIMIT_ATTEMPTS=30
LOGIN_RATE_LIMIT_WINDOW_SECONDS=900

PASSWORD_MIN_LENGTH=12
PASSWORD_ARGON_MEMORY_KB=65536
PASSWORD_ARGON_TIME_COST=4
PASSWORD_ARGON_THREADS=2
PASSWORD_BCRYPT_COST=12

PASSWORD_RESET_TTL_MINUTES=30
PASSWORD_RESET_RATE_LIMIT_ATTEMPTS=5
PASSWORD_RESET_IP_RATE_LIMIT_ATTEMPTS=20
PASSWORD_RESET_RATE_LIMIT_WINDOW_SECONDS=900
PASSWORD_RESET_BASE_URL=https://app.befittraining.gr

MAX_REQUEST_BODY_BYTES=65536
REQUIRE_JSON_WRITES=true
```

Generate `APP_KEY` directly on the server:

```bash
/opt/plesk/php/8.3/bin/php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
```

Paste the generated value into `.env`. Never send it in chat, screenshots, tickets or logs.

## Trusted proxies

Leave `TRUSTED_PROXIES` empty unless PHP sees the local reverse proxy address as `REMOTE_ADDR` and you explicitly need `X-Forwarded-For` for rate limiting.

If verified on this Plesk host, a typical value may be:

```dotenv
TRUSTED_PROXIES=127.0.0.1,::1
```

Do not trust arbitrary/public proxy ranges. The resolver only accepts forwarded addresses when the direct peer is explicitly trusted.

## Deployment order

1. Back up the current backend code and database.
2. Add `APP_KEY` and the production security values to `.env` **before** deploying the new PHP files.
3. Deploy the update files.
4. Keep the web document root pointed only at `befit-backend/public`.
5. Run the PHP syntax check and security check.
6. Run `composer audit --locked` on a machine/server with Composer and network access.
7. Test health, CORS, login, logout, password change/reset, member routes and admin routes.
8. Schedule `bin/security-cleanup.php` daily.

## Verification commands

```bash
cd /var/www/vhosts/befittraining.gr/api.befittraining.gr

find src bootstrap config routes public bin -type f -name '*.php' -print0 \
  | xargs -0 -n1 /opt/plesk/php/8.3/bin/php -l

/opt/plesk/php/8.3/bin/php bin/security-check.php
```

Check security headers:

```bash
curl -sSI https://api.befittraining.gr/api/v1/health \
  | grep -Ei 'HTTP/|strict-transport-security|content-security-policy|permissions-policy|cache-control|x-content-type-options|x-frame-options|referrer-policy'
```

Check disallowed CORS:

```bash
curl -si \
  -H 'Origin: https://example.invalid' \
  https://api.befittraining.gr/api/v1/health
```

Expected: HTTP 403.

Check oversized/wrong media type protection with a harmless endpoint request:

```bash
curl -si -X POST \
  -H 'Content-Type: text/plain' \
  --data 'test' \
  https://api.befittraining.gr/api/v1/auth/login
```

Expected: HTTP 415.

## Daily cleanup

Example Plesk scheduled task:

```bash
/opt/plesk/php/8.3/bin/php /var/www/vhosts/befittraining.gr/api.befittraining.gr/bin/security-cleanup.php
```

This removes expired API tokens, expired/old password reset tokens and stale rate-limit buckets.

## Important limitation

This update hardens the current Bearer-token architecture. A further security step would be moving browser authentication from JavaScript-accessible token storage to Secure, HttpOnly, SameSite cookies and adding admin MFA. Those changes require coordinated frontend + backend work and are intentionally not mixed into this compatibility-focused hardening release.
