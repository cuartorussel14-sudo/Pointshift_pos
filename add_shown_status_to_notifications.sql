-- Add shown status to notifications table
ALTER TABLE notifications 
ADD COLUMN shown BOOLEAN NOT NULL DEFAULT FALSE;

-- Mark all existing notifications as shown
UPDATE notifications SET shown = TRUE;