# BE-FIT V2 upgrade guide

This package upgrades the already-running BE-FIT v1 installation without deleting existing members, sessions, or bookings.

## 1. Back up first

From PowerShell you can use the included helper:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\backup-database.ps1
```

If your Laragon MySQL folder is different, pass `-MySqlDump` with the full path to `mysqldump.exe`.

## 2. Run the V2 database migration ONCE

In phpMyAdmin select/import:

`database/migrations/002_v2_features.sql`

Do **not** import `database/install.sql` into the existing database. `install.sql` is for a brand-new installation and rebuilds the schema.

The migration preserves existing data and adds:

- `users.must_change_password`
- attendance fields/statuses on `bookings`
- `waitlist_entries`
- `password_reset_tokens`
- `notifications`
- booking-rule settings
- privacy default (`show_attendee_names = 0`)

## 3. Replace backend application files

Safest approach: replace the complete backend application with the V2 backend package, but keep your existing local `.env` and `vendor/` folder.

If replacing only changed files, copy these from V2 over the existing files:

- `.env.example`
- `bootstrap/app.php`
- `config/app.php`
- `routes/api.php`
- `bin/create-admin.php`
- `bin/create-reminders.php` (new)
- `bin/v2-check.php` (new)
- `scripts/backup-database.ps1` (new)
- `src/Controller/AdminBookingController.php`
- `src/Controller/AdminReportController.php` (new)
- `src/Controller/BookingController.php`
- `src/Controller/NotificationController.php` (new)
- `src/Controller/PasswordResetController.php` (new)
- `src/Middleware/AuthenticationMiddleware.php`
- `src/Repository/BookingRepository.php`
- `src/Repository/DashboardRepository.php`
- `src/Repository/NotificationRepository.php` (new)
- `src/Repository/PasswordResetRepository.php` (new)
- `src/Repository/ReportRepository.php` (new)
- `src/Repository/UserRepository.php`
- `src/Repository/WaitlistRepository.php` (new)
- `src/Service/BookingService.php`
- `src/Service/NotificationService.php` (new)
- `src/Service/PasswordResetService.php` (new)
- `src/Service/ReportService.php` (new)
- `src/Service/ScheduleService.php`
- `src/Service/SettingsService.php`
- `src/Service/UserService.php`
- `src/Support/UserPresenter.php`

`database/schema.sql`, `database/seed.sql`, and `database/install.sql` are also updated for future clean installations, but you should use the migration for your current database.

## 4. Add these values to your existing `.env`

Keep your working DB/CORS values and add:

```env
PASSWORD_RESET_TTL_MINUTES=60
PASSWORD_RESET_RATE_LIMIT_ATTEMPTS=5
PASSWORD_RESET_RATE_LIMIT_WINDOW_SECONDS=900
PASSWORD_RESET_BASE_URL=http://befit.test:8082
MAIL_ENABLED=false
MAIL_FROM=no-reply@befit.test
MAIL_FROM_NAME=BE-FIT Training Center
```

For production, change the reset base URL to the HTTPS frontend URL. If the server's PHP `mail()` is configured, set `MAIL_ENABLED=true` and use a real sender address.

## 5. Restart Apache and verify

```bash
curl http://befitapi.test:8082/api/v1/health
```

Then verify the V2 migration itself:

```bash
php bin/v2-check.php
```

Expected: `BE-FIT V2 database check OK.`

Then log in again and verify `/auth/me`.

## 6. Replace the frontend

Replace these frontend files with V2:

- `index.html`
- `assets/js/api.js`
- `assets/js/app.js`
- `assets/css/style.css`
- `service-worker.js`
- `README.md`

The image files, manifest and `assets/js/config.js` can stay unchanged unless you prefer to replace the whole frontend directory.

Because the PWA shell cache changed from v1 to v2, refresh once with `Ctrl+F5`. If an installed PWA still shows the old shell, close/reopen it after the browser updates the service worker.

## 7. New administrator settings

Open **Admin → Settings** and review:

- booking days ahead: default 30
- booking cutoff: default 60 minutes
- cancellation cutoff: default 120 minutes
- max active future bookings: default 12
- max bookings per member per day: default 1
- waitlist enabled: yes
- automatic waitlist promotion: yes
- attendee names visible to other members: no

Adjust these with the gym owner before launch.

## 8. Attendance workflow

Open **Admin → Bookings**. Each non-cancelled booking now has an attendance selector:

- Booked
- Checked in
- No-show

The report page uses these statuses.

## 9. Notifications and reminders

Members receive in-app notifications for booking confirmation, booking cancellation, automatic waitlist promotion, session closure/cancellation, and full-day gym closures that affect their reservations.

To create next-day reminder notifications, run:

```bash
php bin/create-reminders.php
```

In production schedule this once per day with Plesk Scheduled Tasks / cron / Windows Task Scheduler.

## 10. Password onboarding/recovery

New users are created with **Require password change on next login** enabled by default. They can only access their Profile until they replace the temporary password.

The login screen also has **Forgot password?**.

In local debug mode the API returns the reset token to the frontend for testing. In production (`APP_DEBUG=false`) the token is never returned; enable server mail delivery if you want reset links emailed automatically.

## 11. Admin waiting-list view

Open **Admin → Bookings**. Below the booking table the frontend now shows the active waiting list for the selected date range, ordered by session/date and join time.

The API endpoint is:

```http
GET /api/v1/admin/waitlist?from=YYYY-MM-DD&to=YYYY-MM-DD&status=waiting
```

## 12. Production and device checks

Before launch, follow `docs/PRODUCTION-CHECKLIST.md` and `docs/TEST-CHECKLIST.md`.

## 13. Closure/cancellation behavior

- Marking a dated session **closed** prevents new reservations but preserves existing bookings.
- Marking a dated session **cancelled** cancels its active bookings, releases its waiting list, and notifies affected members.
- Creating a **full-day closure** cancels active bookings on that date, releases waiting-list entries, and notifies affected members.
- Removing a full-day closure does **not** restore bookings that were cancelled by the closure; members must reserve again if the gym reopens that date.


## V3 follow-up

After V2, apply `database/migrations/003_payments_and_booking_rules.sql` once and run `php bin/v3-check.php`. This adds admin-validated member payment history and removes the old active/per-day booking quantity settings. Expired payments do not block booking.
