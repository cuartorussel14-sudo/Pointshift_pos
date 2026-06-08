-- =====================================================
-- FIX INVENTORY REPORTS TABLE - Add Missing Columns
-- =====================================================
-- This fixes the error: Column not found: 'quantity_changed'
-- Run this in your MySQL/phpMyAdmin or Terminal
-- =====================================================

USE pointshift_pos;

-- First, add default value to the existing 'quantity' column
ALTER TABLE inventory_reports 
MODIFY COLUMN quantity INT NOT NULL DEFAULT 0
COMMENT 'Legacy column - same as quantity_changed';

-- Add quantity_changed column (absolute value of stock change)
ALTER TABLE inventory_reports 
ADD COLUMN IF NOT EXISTS quantity_changed INT NOT NULL DEFAULT 0 AFTER quantity
COMMENT 'Absolute amount of stock added or removed';

-- Add previous_quantity column (stock level before the change)
ALTER TABLE inventory_reports 
ADD COLUMN IF NOT EXISTS previous_quantity INT DEFAULT NULL AFTER quantity_changed
COMMENT 'Stock quantity before the change';

-- Add new_quantity column (stock level after the change)
ALTER TABLE inventory_reports 
ADD COLUMN IF NOT EXISTS new_quantity INT DEFAULT NULL AFTER previous_quantity
COMMENT 'Stock quantity after the change';

-- Verify the changes
SELECT 'Table structure updated successfully!' as Status;
DESCRIBE inventory_reports;

-- =====================================================
-- EXPECTED RESULT:
-- =====================================================
-- inventory_reports table should now have these columns:
-- - id
-- - date
-- - product_id
-- - user_id
-- - change_type (Added/Removed)
-- - quantity (has default value 0 now)
-- - quantity_changed (NEW - how much was added/removed)
-- - previous_quantity (NEW - stock before change)
-- - new_quantity (NEW - stock after change)
-- - remarks
-- - created_at
-- =====================================================
