-- Migration: Add 'pending' to users.status enum and ensure email_verified column
-- Run this on your MySQL server for the `pointshift_pos` database (via phpMyAdmin or mysql CLI).

ALTER TABLE `users`
    MODIFY COLUMN `status` ENUM('active','inactive','pending') NOT NULL DEFAULT 'pending';

-- Normalize any rows with invalid or empty status to 'pending'
UPDATE `users`
SET `status` = 'pending'
WHERE `status` IS NULL OR `status` = '' OR `status` NOT IN ('active','inactive','pending');

-- Ensure email_verified column exists (default 0)
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `email_verified` TINYINT(1) NOT NULL DEFAULT 0;

-- Optional: if you want new users created without explicit status to default to 'pending', the MODIFY above sets that default.
