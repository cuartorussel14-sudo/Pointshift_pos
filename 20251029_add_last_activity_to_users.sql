-- Migration: add last_activity column to users table
ALTER TABLE users
ADD COLUMN last_activity TIMESTAMP NULL DEFAULT NULL AFTER updated_at;

-- Optionally backfill with updated_at for existing users
-- UPDATE users SET last_activity = updated_at WHERE last_activity IS NULL;
