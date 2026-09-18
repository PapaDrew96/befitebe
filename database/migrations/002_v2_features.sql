-- BE-FIT v2 migration: booking rules, waitlist, attendance, password reset, notifications.
-- Run ONCE against the existing befit_app database.
USE `befit_app`;

ALTER TABLE `users`
  ADD COLUMN `must_change_password` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`;

ALTER TABLE `bookings`
  ADD COLUMN `checked_in_at` DATETIME NULL AFTER `cancelled_at`,
  ADD COLUMN `attendance_marked_by_user_id` BIGINT UNSIGNED NULL AFTER `checked_in_at`,
  ADD KEY `idx_bookings_attendance_marked_by` (`attendance_marked_by_user_id`),
  ADD CONSTRAINT `fk_bookings_attendance_marked_by`
    FOREIGN KEY (`attendance_marked_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `bookings` DROP CHECK `chk_bookings_status`;
ALTER TABLE `bookings`
  ADD CONSTRAINT `chk_bookings_status`
  CHECK (`status` IN ('booked', 'checked_in', 'no_show', 'cancelled'));

CREATE TABLE `waitlist_entries` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'waiting',
  `promoted_booking_id` BIGINT UNSIGNED NULL,
  `joined_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `left_at` DATETIME NULL,
  `promoted_at` DATETIME NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_waitlist_session_user` (`session_id`, `user_id`),
  KEY `idx_waitlist_session_status_joined` (`session_id`, `status`, `joined_at`),
  KEY `idx_waitlist_user_status` (`user_id`, `status`),
  CONSTRAINT `fk_waitlist_session` FOREIGN KEY (`session_id`) REFERENCES `gym_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_waitlist_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_waitlist_booking` FOREIGN KEY (`promoted_booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_waitlist_status` CHECK (`status` IN ('waiting', 'promoted', 'left'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `password_reset_tokens` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_password_reset_hash` (`token_hash`),
  KEY `idx_password_reset_user` (`user_id`),
  KEY `idx_password_reset_expiry` (`expires_at`),
  CONSTRAINT `fk_password_reset_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notifications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `type` VARCHAR(50) NOT NULL,
  `title` VARCHAR(180) NOT NULL,
  `body` VARCHAR(1000) NOT NULL,
  `data` JSON NULL,
  `read_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user_read_created` (`user_id`, `read_at`, `created_at`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
  ('show_attendee_names', '0'),
  ('booking_days_ahead', '30'),
  ('booking_cutoff_minutes', '60'),
  ('cancellation_cutoff_minutes', '120'),
  ('waitlist_enabled', '1'),
  ('auto_promote_waitlist', '1')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);
