-- BE-FIT v3 migration: member payment validation and removal of booking quantity limits.
-- Run ONCE after 002_v2_features.sql on an existing database.
USE `befit_app`;

CREATE TABLE IF NOT EXISTS `member_payments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `payment_date` DATE NOT NULL,
  `valid_until` DATE NOT NULL,
  `confirmed_by_user_id` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_member_payments_user_valid` (`user_id`, `valid_until`),
  KEY `idx_member_payments_payment_date` (`payment_date`),
  KEY `idx_member_payments_confirmed_by` (`confirmed_by_user_id`),
  CONSTRAINT `fk_member_payments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_member_payments_confirmed_by` FOREIGN KEY (`confirmed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_member_payments_dates` CHECK (`valid_until` >= `payment_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELETE FROM `settings`
WHERE `setting_key` IN ('max_active_bookings', 'max_bookings_per_day');
