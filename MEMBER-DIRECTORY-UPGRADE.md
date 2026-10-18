# BE-FIT member directory upgrade

This update supports the frontend member-directory changes:

- optional member first/last names;
- per-member `membership_deal`;
- sorting by first name, last name, or email;
- ascending/descending ordering;
- fixed PDO search placeholders for `/admin/users`.

## Database

Run once on the existing production database:

```sql
USE `befit_app`;

ALTER TABLE `users`
  MODIFY COLUMN `first_name` VARCHAR(100) NULL,
  MODIFY COLUMN `last_name` VARCHAR(100) NULL,
  ADD COLUMN `membership_deal` VARCHAR(190) NULL AFTER `phone`;
```

The same SQL is stored in `database/migrations/005_member_directory.sql`.

## Backend checks

```bash
/opt/plesk/php/8.3/bin/php -l src/Repository/UserRepository.php
/opt/plesk/php/8.3/bin/php -l src/Service/UserService.php
/opt/plesk/php/8.3/bin/php -l src/Support/UserPresenter.php
/opt/plesk/php/8.3/bin/php -l src/Controller/AdminUserController.php
```

## Smoke tests

1. Open Admin > Members and search by part of an email.
2. Sort by first name, last name, and email in both directions.
3. Edit an email-only member with blank first/last name.
4. Set a member deal, save it, reopen the member, and confirm the value persists.
5. Confirm normal member `/auth/me` and `/profile` responses do not expose the administrator-only `membership_deal` field.
