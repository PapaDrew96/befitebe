# BE-FIT acceptance / device test checklist

Run this after the V3 migration and before client handoff.

## Authentication and onboarding

- Admin can log in and receives admin navigation.
- Member can log in and cannot access `/admin/*` endpoints.
- A new member with `must_change_password=true` is forced to Profile until changing the temporary password.
- Password change logs the user out and old tokens no longer work.
- Forgot-password returns a usable development reset token when `APP_DEBUG=true`.
- With `APP_DEBUG=false`, forgot-password never exposes a raw reset token.
- Invalid/expired reset token is rejected.

## Schedule and booking rules

- Monday/Wednesday/Friday have 08:30, 09:30, 10:30 plus 16:00–21:00.
- Tuesday/Thursday have 16:00–21:00.
- Saturday/Sunday have no recurring sessions unless admin adds one.
- Member can book an allowed available session.
- Same member cannot book the same session twice.
- Multiple bookings on the same day are allowed.
- There is no maximum number of active future bookings.
- Booking-days-ahead and booking-cutoff rules are enforced.
- Cancellation cutoff is enforced for members.
- Admin can still manage a member booking when member cutoff has passed.


## Payments

- Member sees `My Payments` status, paid-until date and history.
- Admin can record a payment from the Members screen.
- First/expired membership suggests one calendar month from the payment date.
- Active membership suggests one calendar month after the existing paid-until date.
- Admin can manually override `valid_until`.
- Payment history records the confirming administrator.
- `paid`, `expired`, and `unpaid` states render correctly.
- Expired or missing payment does **not** prevent booking.

## Capacity/concurrency

- Fill a session to capacity and confirm it reports `FULL`.
- Full session offers waiting list when enabled.
- Two simultaneous requests for the last place result in only one successful booking.
- Cancelling a full session booking automatically promotes the oldest waiting member when auto-promotion is enabled.
- Promoted member receives an in-app notification.

## Attendance and reports

- Admin can mark a booking `checked_in`.
- Admin can mark a booking `no_show`.
- Cancelled booking cannot be marked for attendance.
- Admin report totals change correctly after check-ins/no-shows/cancellations.
- Popular hour and average occupancy render without errors on an empty and a populated range.

## Closures and notifications

- Closing/cancelling a dated session prevents new bookings.
- Members already booked into that session receive an in-app schedule-change notification.
- Adding a full-day closure prevents bookings for that date.
- Members with bookings on that date receive a closure notification.
- Removing a closure reopens the date according to the underlying session statuses, but bookings cancelled by the closure are not automatically restored.

## Privacy

- With `show_attendee_names=false`, members see capacity but not other member names.
- With it enabled, attendee names appear as expected.
- Admin can still see member information needed for managing bookings.

## Frontend / mobile

Test at minimum:

- iPhone Safari;
- Android Chrome;
- desktop Chrome/Edge;
- narrow 320–360px width;
- common 390px mobile width;
- tablet width;
- landscape orientation.

Verify EN/EL switching, login, schedule navigation, reserve/cancel, waitlist, payments, notifications, profile, admin tables/modals and logout on each mobile platform used by the client. Confirm no horizontal overflow at 320–360px.

## PWA / connectivity

- Production HTTPS allows the service worker to register.
- Install prompt/app icon works on supported browsers.
- A newly deployed version replaces the old shell after service-worker update/reopen.
- API data is not served from stale cache; booking capacity remains live.
- Friendly errors are displayed if the API is temporarily unreachable.
