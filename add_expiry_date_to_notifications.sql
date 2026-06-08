-- Add expiry_date column to notifications table if it doesn't exist
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS expiry_date DATE DEFAULT NULL;

-- Update existing expiry notifications with their product's expiry date
UPDATE notifications n
JOIN products p ON n.product_id = p.id
SET n.expiry_date = p.expiry
WHERE n.type = 'expiry' AND n.expiry_date IS NULL;