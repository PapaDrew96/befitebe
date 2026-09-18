# BE-FIT V3 — Exact upgrade steps

This package is based on the current frontend/backend ZIPs supplied for this update.

## What changed

- Full EN / EL interface switcher; the selected language is remembered.
- Responsive improvements across member/admin views without changing the BE-FIT color palette.
- Member menu: **My Payments / Οι πληρωμές μου**.
- Admin payment validation and history from **Admin → Members**.
- Payment validity defaults to one calendar month:
  - if the existing `paid_until` is still active, extend from that date;
  - otherwise extend from the payment date/current date.
  - Admin can edit `valid_until` manually.
- Expired/unpaid status is informational only and **does not block bookings**.
- `max_active_bookings` and `max_bookings_per_day` are completely removed from settings and backend booking validation.
- Simple temporary-password generator + copy button in the Admin member form.
- Service worker cache bumped to `befit-shell-v3`.

## 1. Back up the current database

Use phpMyAdmin Export or your normal MySQL backup process before applying the migration.

## 2. Backend files

Copy the contents of the new backend ZIP over:

`C:\laragon\www\befit-api\befit-backend\`

**Keep your existing `.env`.** The distributed backend ZIP intentionally does not include `.env` so credentials are not packaged.

The package still contains `.env.example`.

## 3. Run database migration 003 exactly once

In phpMyAdmin, select `befit_app` and import:

`database/migrations/003_payments_and_booking_rules.sql`

This migration:

1. creates `member_payments`;
2. removes the obsolete `max_active_bookings` setting;
3. removes the obsolete `max_bookings_per_day` setting.

Do **not** re-import `database/install.sql` on the existing database.

## 4. Refresh backend autoload + verify

From Git Bash:

```bash
cd /c/laragon/www/befit-api/befit-backend
composer dump-autoload
php bin/v3-check.php
```

Expected:

```text
BE-FIT V3 database check OK.
Database: befit_app
Payments, booking rules, and V2 features are ready.
```

Then:

```bash
curl http://befitapi.test:8082/api/v1/health
```

## 5. Frontend files

Replace the contents of:

`C:\laragon\www\befit-frontend\`

with the new frontend ZIP.

The API URL behavior in `assets/js/config.js` remains compatible with the existing Laragon setup.

Open:

`http://befit.test:8082`

Then perform a hard refresh (`Ctrl + F5`). Because the service worker cache changed to `befit-shell-v3`, reopen an installed PWA once after deployment as well.

## 6. Test language switching

There is an **EN / EL** switch on both the login screen and inside the application header.

Check at minimum:

- login / forgot password;
- member schedule / bookings / payments / notices / notifications / profile;
- admin dashboard / schedule / bookings / members / announcements / reports / settings;
- dialogs, validation errors, confirmation dialogs and system notifications.

Free-text content written by an administrator (for example an announcement body or a custom session note) is shown exactly as the administrator entered it; the application's own UI is bilingual.

## 7. Test payments

### Admin

Go to **Admin → Members**. Member rows now include payment status and a payment/card action.

Open the payment action and verify:

- current `Paid / Expired / Unpaid` state;
- payment date;
- suggested `valid_until`;
- manual editing of `valid_until`;
- payment history and confirming administrator.

### Member

Go to **My Payments / Οι πληρωμές μου** and verify current status, paid-until date and history.

Important: payment state never blocks making a booking.

## 8. Test booking-rule removal

Verify that one member can now:

- hold more than the previous active-booking limit;
- book more than one different session on the same date.

The following protections remain:

- no duplicate booking of the same session;
- session capacity;
- booking/cancellation cutoffs;
- closed/cancelled sessions and full-day closures;
- waiting list behavior.

## 9. Test password generator

Go to **Admin → Members → Add member**.

Use **Generate / Δημιουργία**, then **Copy / Αντιγραφή**. The generated password is 12 characters and includes upper/lower-case letters and digits while avoiding some ambiguous characters.

`Require password change on next login` continues to work as before.

## 10. Responsive acceptance test

Test at approximately:

- 320–360 px;
- 390 px;
- tablet width;
- desktop width.

Admin tables collapse into stacked mobile cards on small screens; forms, toolbars, modal actions and session buttons are adjusted for narrow layouts. No new color values were introduced by this update.
