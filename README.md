# BE-FIT Training Center API

Production-oriented REST API for the BE-FIT mobile/PWA booking application.

## Stack

- PHP 8.1+
- Slim Framework 4
- Slim PSR-7
- PDO MySQL
- MySQL 8+ / MariaDB 10.5+
- phpdotenv
- Monolog
- Opaque Bearer-token authentication (only SHA-256 token hashes are stored)

The code is deliberately framework-light and split into Controllers, Services, Repositories, Middleware, and Support classes.

## Project structure

```text
befit-backend/
├── bin/                    CLI tools
├── bootstrap/              application bootstrap
├── config/                 environment-based configuration
├── database/               schema + initial BE-FIT timetable
├── docs/                   API reference
├── public/                 web server document root
├── routes/                 versioned API routes
├── server/                 Laragon/Apache example
├── src/
│   ├── Controller/
│   ├── Database/
│   ├── Exception/
│   ├── Http/
│   ├── Middleware/
│   ├── Repository/
│   ├── Service/
│   └── Support/
└── storage/                logs/cache
```

## Laragon installation

1. Extract/copy the project to `C:\laragon\www\befit-api`.
2. Open a Laragon terminal in that folder and run:

```bash
composer install
copy .env.example .env
```

3. Create/import the database in phpMyAdmin. The easiest option is to import `database/install.sql` once. Alternatively import `database/schema.sql` first and `database/seed.sql` second
4. Edit `.env` if your MySQL credentials or frontend origin differ.
5. Configure the Apache virtual host so its **DocumentRoot is the project's `public` directory**. An example is in `server/laragon-apache-vhost.conf.example`. Restart Apache after changing the vhost.
6. Create the first administrator:

```bash
php bin/create-admin.php --first="Gym" --last="Owner" --email="owner@example.com" --password="ChangeMe123!"
```

7. Test:

```bash
curl http://befit-api.test/api/v1/health
```

Expected result contains `"status":"ok"` and `"database":"ok"`.

## Initial timetable

`database/seed.sql` reproduces the supplied BE-FIT program with default capacity 8:

- Monday, Wednesday, Friday: 08:30, 09:30, 10:30
- Monday-Friday: 16:00, 17:00, 18:00, 19:00, 20:00, 21:00

Recurring templates generate actual dated `gym_sessions` lazily when the schedule is requested. Once an actual dated session exists, it is intentionally treated as its own record: future changes to a template do not silently rewrite an already-materialized session or its bookings. The administrator can edit that dated session explicitly.

## Authentication

Login uses email **or** phone plus password. The API returns a 64-character opaque Bearer token. Send it as:

```http
Authorization: Bearer YOUR_TOKEN
```

The raw token is never stored in MySQL; only its SHA-256 hash is stored. Default token lifetime is 30 days and can be changed with `TOKEN_TTL_DAYS`.

Because authentication is header-based rather than cookie-based, the API does not rely on browser cookie sessions or CSRF tokens.

## Booking integrity

Creating a booking opens a database transaction and locks the target `gym_sessions` row with `SELECT ... FOR UPDATE`. Capacity is checked while that lock is held. Two users trying to take the final available position cannot both succeed.

The database also has a unique `(session_id, user_id)` constraint, preventing duplicate attendance rows for the same member/session.

## Schedule exceptions

The API supports both:

- a full-date closure in `schedule_closures`, and
- an individual dated session marked `open`, `closed`, or `cancelled`.

This means holidays, one-off closures, cancelled hours, extra sessions, and temporary capacity changes do not require code changes.

## CORS

Set comma-separated frontend origins in `.env`, for example:

```dotenv
CORS_ALLOWED_ORIGINS=http://befit.test,http://localhost:3000
```

Do not use `*` in production unless the API is intentionally public to every web origin.

## Production notes

- Set `APP_ENV=production` and `APP_DEBUG=false`.
- Serve only `public/` from the web server.
- Use HTTPS in production.
- Keep `.env`, `database/`, `src/`, `storage/`, and `vendor/` outside the public document root.
- Give the PHP/Apache user write permission to `storage/logs`.
- Back up MySQL regularly.
- Change the example administrator password immediately.
- Set `CORS_ALLOWED_ORIGINS` to the real PWA/domain only.

## API documentation

See [`docs/API.md`](docs/API.md). A ready-to-import Postman collection is included at `docs/BE-FIT.postman_collection.json`.

For an existing V1 installation use [`docs/V2-UPGRADE.md`](docs/V2-UPGRADE.md). Before public launch also use [`docs/PRODUCTION-CHECKLIST.md`](docs/PRODUCTION-CHECKLIST.md) and [`docs/TEST-CHECKLIST.md`](docs/TEST-CHECKLIST.md).

---

## V2 production features

The V2 package adds configurable booking/cancellation rules, a waiting list with optional automatic promotion, check-in/no-show attendance tracking, attendance/history reports, forced temporary-password replacement, password reset tokens, in-app notifications, next-day reminder generation, safer attendee-name privacy defaults, and a Windows/Laragon database backup helper.

If you already installed the original database, **do not import `database/install.sql` again**. Follow `docs/V2-UPGRADE.md` and run `database/migrations/002_v2_features.sql` once.


## V3 payments and booking-rule update

For an existing V2 database, run `database/migrations/003_payments_and_booking_rules.sql` once, then run `php bin/v3-check.php`.

V3 adds:

- Admin-validated member payments with `payment_date`, `valid_until`, confirming admin and full history.
- Member `GET /api/v1/payments`.
- Admin `GET/POST /api/v1/admin/users/{id}/payments`.
- Payment status never blocks bookings. It is informational/administrative only.
- Removed `max_active_bookings` and `max_bookings_per_day` from settings and booking validation. Session capacity, duplicate-session protection, booking/cancellation cutoffs, closures and the waitlist remain enforced.
- The frontend provides EN/EL UI and a simple temporary-password generator; these are frontend features and do not change authentication rules.
