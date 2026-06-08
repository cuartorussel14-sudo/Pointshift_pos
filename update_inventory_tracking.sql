-- SQL Migration for Inventory Tracking Enhancement
-- This adds columns to track WHO updated product stocks

-- Add last_updated_by column to products table
ALTER TABLE `products` 
ADD COLUMN `last_updated_by` int DEFAULT NULL AFTER `updated_at`,
ADD CONSTRAINT `fk_products_last_updated_by` FOREIGN KEY (`last_updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- Add user_id column to inventory_reports table to track who made the change
ALTER TABLE `inventory_reports` 
ADD COLUMN `user_id` int DEFAULT NULL AFTER `product_id`,
ADD COLUMN `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP AFTER `remarks`,
ADD CONSTRAINT `fk_inventory_reports_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- Add index for faster queries
CREATE INDEX `idx_inventory_reports_date` ON `inventory_reports` (`date` DESC);
CREATE INDEX `idx_inventory_reports_created` ON `inventory_reports` (`created_at` DESC);
