# BE-FIT production checklist

Use this checklist before the gym starts relying on the application with real members.

## Server and application

- Serve the frontend and API over HTTPS.
- Backend Apache/Nginx document root must point only to `befit-backend/public`.
- Set `APP_ENV=production` and `APP_DEBUG=false`.
- Set `APP_URL` and `PASSWORD_RESET_BASE_URL` to the real HTTPS URLs.
- Set `CORS_ALLOWED_ORIGINS` to the exact production frontend origin only.
- Use a dedicated MySQL user with access only to the BE-FIT database; do not use `root` in production.
- Keep `.env` outside the public document root and never commit it.
- Ensure only `storage/logs` and the backup destination require write access.
- Replace/remove all local test users and temporary passwords.
- Revoke test Bearer tokens before launch.

## Member privacy / GDPR-oriented product choices

- Default `show_attendee_names` is **false**. Keep it disabled unless the gym explicitly decides members should see one another's names.
- Collect only member data the gym actually needs.
- Define who is allowed to export/use attendance history and how long it is retained.
- Include the gym's privacy information/terms in the final public deployment if required by the client's legal/privacy process.

## Password recovery and email

- Password reset tokens are hashed in MySQL and expire automatically.
- In production, reset tokens are never returned by the API when `APP_DEBUG=false`.
- If email reset links are required, configure the server so PHP `mail()` can deliver mail and set `MAIL_ENABLED=true`.
- Test delivery to at least Gmail and Outlook before relying on it.
- If the production host does not provide reliable PHP mail delivery, replace native `mail()` with the client's SMTP/provider integration before launch.

## Booking rules to confirm with the gym owner

Review **Admin → Settings** and explicitly agree on:

- booking days ahead;
- booking cutoff minutes;
- cancellation cutoff minutes;
- maximum active future bookings;
- maximum bookings per member per day;
- waiting list enabled/disabled;
- automatic waiting-list promotion enabled/disabled;
- whether member names are visible.

## Scheduled jobs

Create a daily task for reminders if desired:

```bash
php bin/create-reminders.php
```

Create an automated nightly MySQL backup. The Windows/Laragon helper is:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\backup-database.ps1
```

The helper keeps 30 days by default. Adjust its destination/retention for the production server and periodically perform a restore test.

## Monitoring

- Check `storage/logs/app.log` after deployment and during the first week of use.
- Confirm disk space for logs/backups.
- Ensure the server clock/timezone is correct (`Europe/Athens` for the current setup).
- Confirm the `/api/v1/health` endpoint returns both application and database `ok`.
