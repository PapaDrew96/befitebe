INSERT INTO `settings` (`setting_key`, `setting_value`)
VALUES ('payment_reminder_days_before_expiry', '5')
ON DUPLICATE KEY UPDATE `setting_value` = `setting_value`;
