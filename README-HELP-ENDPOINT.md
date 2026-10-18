# BE-FIT Help page backend patch

Adds the authenticated member/admin endpoint used by the frontend Help page:

`GET /api/v1/booking-rules`

It returns only member-safe booking rules:

- `booking_days_ahead`
- `booking_cutoff_minutes`
- `cancellation_cutoff_minutes`
- `waitlist_enabled`
- `auto_promote_waitlist`
- `show_attendee_names`

No database migration is required.

## Files

Copy these files over the matching paths in the backend:

- `bootstrap/app.php`
- `routes/api.php`
- `src/Controller/BookingRulesController.php`
- `src/Service/SettingsService.php`

Keep the server's existing `.env` unchanged.

## After copying

From the backend root:

```bash
composer dump-autoload
php -l src/Controller/BookingRulesController.php
php -l src/Service/SettingsService.php
php -l routes/api.php
php -l bootstrap/app.php
```

Then test with an authenticated member token:

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  https://api.befittraining.gr/api/v1/booking-rules
```

Expected `data` shape:

```json
{
  "booking_days_ahead": 30,
  "booking_cutoff_minutes": 60,
  "cancellation_cutoff_minutes": 120,
  "waitlist_enabled": true,
  "auto_promote_waitlist": true,
  "show_attendee_names": false
}
```
