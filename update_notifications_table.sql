-- Add user_id column to notifications table
ALTER TABLE notifications ADD COLUMN user_id INT NULL;
ALTER TABLE notifications ADD FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;

-- Update existing notifications to handle user-specific notifications
-- Index for better query performance
CREATE INDEX idx_notifications_user_id ON notifications(user_id);
CREATE INDEX idx_notifications_status ON notifications(status);
CREATE INDEX idx_notifications_created_at ON notifications(created_at);