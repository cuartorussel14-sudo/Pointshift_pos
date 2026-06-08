-- Combined SQL file
-- Generated on: 2025-10-29
-- Purpose: Single combined SQL file containing all migration and helper SQL in this repository.
-- IMPORTANT: This file concatenates multiple SQL snippets and migrations. Review before running.

-- Table of contents (sources included):
--  1) database.sql (original dump)
--  2) add_system_notifications_table.sql
--  3) add_store_settings_table.sql
--  4) add_shown_status_to_notifications.sql
--  5) add_shifts_table.sql
--  6) add_sample_barcodes.sql
--  7) add_payment_qrcodes_table.sql
--  8) add_notifications_table.sql
--  9) add_messages_table.sql
-- 10) add_inventory_tracking.sql
-- 11) add_gcash_qr_table.sql
-- 12) add_expiry_column_to_products.sql
-- 13) add_encrypted_fields.sql
-- 14) add_backup_and_shift_updates.sql
-- 15) update_orders_table.sql
-- 16) update_notifications_table.sql
-- 17) update_inventory_tracking.sql
-- 18) sample_products.sql
-- 19) sql/20251029_create_product_expiries.sql
-- 20) sql/add_pos_messages_table.sql
-- 21) sql/20251029_add_last_activity_to_users.sql
-- 22) fix_inventory_reports_table.sql
-- 23) fix_inventory_reports_columns.sql
-- 24) migrations/2025-10-28_add_pending_to_users.sql
-- 25) add_expiry_date_to_notifications.sql

-- NOTE: A backup of the original `database.sql` was written to `database.sql.bak` in this repository.

-- -----------------------------------------------------------------------------
-- Section: Original database.sql (begin)
-- -----------------------------------------------------------------------------
-- See database.sql.bak for the original full dump. Below is the core schema portion from the original dump.
CREATE DATABASE IF NOT EXISTS `pointshift_pos` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `pointshift_pos`;

CREATE TABLE IF NOT EXISTS `categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `inventory_reports` (
  `id` int NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `product_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `change_type` enum('Added','Removed') NOT NULL,
  `quantity` int NOT NULL DEFAULT '0' COMMENT 'Legacy column - same as quantity_changed',
  `quantity_changed` int NOT NULL DEFAULT '0',
  `previous_quantity` int DEFAULT NULL,
  `new_quantity` int DEFAULT NULL,
  `remarks` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `fk_inventory_reports_user` (`user_id`),
  KEY `idx_inventory_reports_date` (`date` DESC),
  KEY `idx_inventory_reports_created` (`created_at` DESC),
  CONSTRAINT `fk_inventory_reports_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_reports_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sender_id` int NOT NULL,
  `recipient_id` int DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `parent_message_id` int DEFAULT NULL COMMENT 'For threaded conversations',
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `sender_id` (`sender_id`),
  KEY `recipient_id` (`recipient_id`),
  KEY `parent_message_id` (`parent_message_id`),
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`),
  CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`),
  CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`parent_message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `orders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_number` varchar(20) NOT NULL,
  `user_id` int NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) DEFAULT '0.00',
  `discount_percent` decimal(5,2) DEFAULT '0.00',
  `discount_amount` decimal(10,2) DEFAULT '0.00',
  `tax_amount` decimal(10,2) DEFAULT '0.00',
  `payment_method` varchar(50) DEFAULT 'cash',
  `amount_received` decimal(10,2) DEFAULT '0.00',
  `status` enum('pending','completed','cancelled') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_number` (`order_number`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `order_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity` int NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `payment_qrcodes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `payment_method` varchar(50) NOT NULL DEFAULT 'gcash',
  `qr_code_path` varchar(255) NOT NULL,
  `description` text,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `payment_method` (`payment_method`),
  KEY `is_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `products` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `sku` varchar(64) DEFAULT NULL,
  `category_id` int DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock_quantity` int DEFAULT '0',
  `low_stock_threshold` int DEFAULT '10',
  `barcode` varchar(100) DEFAULT NULL,
  `expiry` date DEFAULT NULL,
  `description` text,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_updated_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  KEY `fk_products_last_updated_by` (`last_updated_by`),
  CONSTRAINT `fk_products_last_updated_by` FOREIGN KEY (`last_updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=78 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `shifts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `shift_name` varchar(100) NOT NULL,
  `shift_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `description` text,
  `location` varchar(255) DEFAULT NULL,
  `max_employees` int DEFAULT '10',
  `status` enum('scheduled','in-progress','completed','cancelled') DEFAULT 'scheduled',
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `shift_date` (`shift_date`),
  KEY `status` (`status`),
  CONSTRAINT `shifts_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `shift_assignments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `shift_id` int NOT NULL,
  `user_id` int NOT NULL,
  `role` enum('supervisor','regular') DEFAULT 'regular',
  `status` enum('assigned','confirmed','declined','completed','no-show') DEFAULT 'assigned',
  `notes` text,
  `assigned_by` int NOT NULL,
  `assigned_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_shift_user` (`shift_id`,`user_id`),
  KEY `shift_id` (`shift_id`),
  KEY `user_id` (`user_id`),
  KEY `assigned_by` (`assigned_by`),
  CONSTRAINT `shift_assignments_ibfk_1` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `shift_assignments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `shift_assignments_ibfk_3` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `store_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `setting_type` varchar(50) DEFAULT 'text' COMMENT 'text, number, boolean, image',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','staff','cashier') NOT NULL DEFAULT 'staff',
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- -----------------------------------------------------------------------------
-- Section: add_system_notifications_table.sql
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS system_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message TEXT NOT NULL,
    type VARCHAR(50) DEFAULT 'info', -- info, warning, error, success
    status VARCHAR(20) DEFAULT 'active', -- active, dismissed, expired
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME DEFAULT NULL
);

-- -----------------------------------------------------------------------------
-- Section: add_store_settings_table.sql
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `store_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `setting_type` varchar(50) DEFAULT 'text' COMMENT 'text, number, boolean, image',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `store_settings` (`setting_key`, `setting_value`, `setting_type`) VALUES
('store_name', 'PointShift POS', 'text'),
('store_branch', 'Main Branch', 'text'),
('store_address', '123 Main Street, City, Country', 'text'),
('store_phone', '+1234567890', 'text'),
('store_email', 'info@pointshift.com', 'text'),
('business_hours_open', '08:00', 'text'),
('business_hours_close', '20:00', 'text'),
('business_days', 'Monday to Sunday', 'text'),
('store_logo', '', 'image'),
('receipt_header', 'Thank you for your purchase!', 'text'),
('receipt_footer', 'Please come again!', 'text'),
('tax_rate', '12', 'number'),
('currency_symbol', '₱', 'text'),
('receipt_show_logo', '1', 'boolean'),
('receipt_show_cashier', '1', 'boolean'),
('admin_notification_email', '', 'text')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

ALTER TABLE store_settings
  ADD COLUMN admin_notification_email VARCHAR(255) NULL;

ALTER TABLE users
  ADD COLUMN email_verified TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN email_verification_code VARCHAR(10) NULL,
  ADD COLUMN email_verification_expires_at DATETIME NULL;

-- -----------------------------------------------------------------------------
-- Section: add_shown_status_to_notifications.sql
-- -----------------------------------------------------------------------------
ALTER TABLE notifications 
ADD COLUMN shown BOOLEAN NOT NULL DEFAULT FALSE;

UPDATE notifications SET shown = TRUE;

-- -----------------------------------------------------------------------------
-- Section: add_shifts_table.sql
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `shifts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `shift_name` varchar(100) NOT NULL,
  `shift_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `description` text,
  `location` varchar(255) DEFAULT NULL,
  `max_employees` int DEFAULT 10,
  `status` enum('scheduled','in-progress','completed','cancelled') DEFAULT 'scheduled',
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `shift_date` (`shift_date`),
  KEY `status` (`status`),
  CONSTRAINT `shifts_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `shift_assignments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `shift_id` int NOT NULL,
  `user_id` int NOT NULL,
  `role` enum('supervisor','regular') DEFAULT 'regular',
  `status` enum('assigned','confirmed','declined','completed','no-show') DEFAULT 'assigned',
  `notes` text,
  `assigned_by` int NOT NULL,
  `assigned_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_shift_user` (`shift_id`, `user_id`),
  KEY `shift_id` (`shift_id`),
  KEY `user_id` (`user_id`),
  KEY `assigned_by` (`assigned_by`),
  CONSTRAINT `shift_assignments_ibfk_1` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `shift_assignments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `shift_assignments_ibfk_3` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `shifts` (`shift_name`, `shift_date`, `start_time`, `end_time`, `description`, `location`, `max_employees`, `status`, `created_by`) VALUES
('Morning Shift', CURDATE(), '08:00:00', '16:00:00', 'Regular morning shift', 'Main Store', 5, 'scheduled', 1),
('Evening Shift', CURDATE(), '16:00:00', '00:00:00', 'Regular evening shift', 'Main Store', 4, 'scheduled', 1),
('Weekend Day Shift', DATE_ADD(CURDATE(), INTERVAL 2 DAY), '09:00:00', '17:00:00', 'Weekend coverage', 'Main Store', 6, 'scheduled', 1),
('Night Shift', CURDATE(), '00:00:00', '08:00:00', 'Overnight shift', 'Main Store', 3, 'scheduled', 1);

-- -----------------------------------------------------------------------------
-- Section: add_sample_barcodes.sql
-- -----------------------------------------------------------------------------
UPDATE products SET barcode = '8901234567890' WHERE id = 1 AND barcode IS NULL;
UPDATE products SET barcode = '8901234567891' WHERE id = 2 AND barcode IS NULL;
UPDATE products SET barcode = '8901234567892' WHERE id = 3 AND barcode IS NULL;
UPDATE products SET barcode = '8901234567893' WHERE id = 4 AND barcode IS NULL;
UPDATE products SET barcode = '8901234567894' WHERE id = 5 AND barcode IS NULL;
UPDATE products SET barcode = '8901234567895' WHERE id = 6 AND barcode IS NULL;
UPDATE products SET barcode = '8901234567896' WHERE id = 7 AND barcode IS NULL;
UPDATE products SET barcode = '8901234567897' WHERE id = 8 AND barcode IS NULL;
UPDATE products SET barcode = '8901234567898' WHERE id = 9 AND barcode IS NULL;
UPDATE products SET barcode = '8901234567899' WHERE id = 10 AND barcode IS NULL;

-- -----------------------------------------------------------------------------
-- Section: add_payment_qrcodes_table.sql
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payment_qrcodes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `payment_method` varchar(50) NOT NULL DEFAULT 'gcash',
  `qr_code_path` varchar(255) NOT NULL,
  `description` text,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `payment_method` (`payment_method`),
  KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `payment_qrcodes` (`payment_method`, `qr_code_path`, `description`, `is_active`) 
VALUES ('gcash', '', 'GCash Payment QR Code', 1)
ON DUPLICATE KEY UPDATE payment_method=payment_method;

-- -----------------------------------------------------------------------------
-- Section: add_notifications_table.sql
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  message VARCHAR(255) NOT NULL,
  type VARCHAR(50) NOT NULL, -- e.g., 'low_stock', 'expiry', 'transaction'
  status VARCHAR(20) NOT NULL DEFAULT 'unread', -- unread, read
  product_id INT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Section: add_messages_table.sql
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sender_id` int NOT NULL,
  `recipient_id` int DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `parent_message_id` int DEFAULT NULL COMMENT 'For threaded conversations',
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `sender_id` (`sender_id`),
  KEY `recipient_id` (`recipient_id`),
  KEY `parent_message_id` (`parent_message_id`),
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`),
  CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`),
  CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`parent_message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- -----------------------------------------------------------------------------
-- Section: add_inventory_tracking.sql
-- -----------------------------------------------------------------------------
-- (file present in repo but empty) - this section intentionally left as a placeholder.

-- -----------------------------------------------------------------------------
-- Section: add_gcash_qr_table.sql
-- -----------------------------------------------------------------------------
-- (file present in repo but empty) - placeholder for future QR code table migrations.

-- -----------------------------------------------------------------------------
-- Section: add_expiry_column_to_products.sql
-- -----------------------------------------------------------------------------
-- (file present in repo but empty) - placeholder for expiry column migration.

-- -----------------------------------------------------------------------------
-- Section: add_encrypted_fields.sql
-- -----------------------------------------------------------------------------
-- Note: this file in the repository contains a PHP call to a migration script.
-- If you intend to run encryption migrations, run the referenced PHP migration script instead of executing this block.
<?php
php migrate_product_encryption.php
?>

-- -----------------------------------------------------------------------------
-- Section: add_backup_and_shift_updates.sql
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS backup_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(1024) DEFAULT NULL,
    checksum VARCHAR(128) DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL,
    encrypted TINYINT(1) DEFAULT 0,
    size BIGINT DEFAULT NULL,
    duration_seconds INT DEFAULT NULL,
    status ENUM('success','failed') DEFAULT 'success',
    notes TEXT,
    INDEX (created_at),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS restore_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    backup_id INT DEFAULT NULL,
    filename VARCHAR(255) NOT NULL,
    restored_at DATETIME NOT NULL,
    restored_by INT DEFAULT NULL,
    duration_seconds INT DEFAULT NULL,
    status ENUM('success','failed') DEFAULT 'success',
    notes TEXT,
    FOREIGN KEY (backup_id) REFERENCES backup_logs(id),
    FOREIGN KEY (restored_by) REFERENCES users(id),
    INDEX (restored_at)
);

CREATE TABLE IF NOT EXISTS backup_retention (
    id INT AUTO_INCREMENT PRIMARY KEY,
    days_to_keep INT NOT NULL DEFAULT 30,
    last_run DATETIME DEFAULT NULL
);

-- -----------------------------------------------------------------------------
-- Section: update_orders_table.sql
-- -----------------------------------------------------------------------------
ALTER TABLE `orders` 
ADD COLUMN `subtotal` DECIMAL(10,2) DEFAULT 0.00 AFTER `total_amount`,
ADD COLUMN `discount_percent` DECIMAL(5,2) DEFAULT 0.00 AFTER `subtotal`,
ADD COLUMN `discount_amount` DECIMAL(10,2) DEFAULT 0.00 AFTER `discount_percent`,
ADD COLUMN `tax_amount` DECIMAL(10,2) DEFAULT 0.00 AFTER `discount_amount`,
ADD COLUMN `payment_method` VARCHAR(50) DEFAULT 'cash' AFTER `tax_amount`,
ADD COLUMN `amount_received` DECIMAL(10,2) DEFAULT 0.00 AFTER `payment_method`;

UPDATE `orders` SET 
    `subtotal` = `total_amount`,
    `tax_amount` = `total_amount` * 0.12,
    `payment_method` = 'cash',
    `amount_received` = `total_amount`
WHERE `subtotal` = 0.00;

DESCRIBE `orders`;

-- -----------------------------------------------------------------------------
-- Section: update_notifications_table.sql
-- -----------------------------------------------------------------------------
ALTER TABLE notifications ADD COLUMN user_id INT NULL;
ALTER TABLE notifications ADD FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;

CREATE INDEX idx_notifications_user_id ON notifications(user_id);
CREATE INDEX idx_notifications_status ON notifications(status);
CREATE INDEX idx_notifications_created_at ON notifications(created_at);

-- -----------------------------------------------------------------------------
-- Section: update_inventory_tracking.sql
-- -----------------------------------------------------------------------------
ALTER TABLE `products` 
ADD COLUMN `last_updated_by` int DEFAULT NULL AFTER `updated_at`,
ADD CONSTRAINT `fk_products_last_updated_by` FOREIGN KEY (`last_updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `inventory_reports` 
ADD COLUMN `user_id` int DEFAULT NULL AFTER `product_id`,
ADD COLUMN `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP AFTER `remarks`,
ADD CONSTRAINT `fk_inventory_reports_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

CREATE INDEX `idx_inventory_reports_date` ON `inventory_reports` (`date` DESC);
CREATE INDEX `idx_inventory_reports_created` ON `inventory_reports` (`created_at` DESC);

-- -----------------------------------------------------------------------------
-- Section: sample_products.sql
-- -----------------------------------------------------------------------------
INSERT INTO products (name, price, stock_quantity, barcode) VALUES
('HP Printer', 4999.99, 5, 'HP001'),
('Canon Camera', 15999.99, 3, 'CAN001'),
('Apple iPhone', 45999.99, 12, 'APL001'),
('Samsung TV', 28999.99, 0, 'SAM001'),
('Dell Desktop', 35999.99, 8, 'DEL001'),
('Logitech Webcam', 2999.99, 20, 'LOG001'),
('Microsoft Office', 5999.99, 0, 'MIC001'),
('Gaming Chair', 12999.99, 6, 'GAM001'),
('Power Bank', 1299.99, 25, 'POW001'),
('Bluetooth Speaker', 3999.99, 2, 'BLU001'),
('Car Air Freshener', 8.99, 79, 'CAR001'),
('Energy Drink', 2.99, 199, 'ENG001'),
('Garden Hose', 45.99, 20, 'GAR001'),
('Laptop Charger', 29.99, 49, 'LAP002'),
('USB Flash Drive', 200.00, 0, 'USB002'),
('T-Shirt Large', 15.99, 75, 'TSH002'),
('Coffee Beans 1kg', 299.99, 30, 'COF001'),
('Wireless Earbuds', 1599.99, 15, 'EAR001'),
('Phone Case', 199.99, 100, 'PHO001'),
('Notebook A4', 89.99, 200, 'NOT001');

-- -----------------------------------------------------------------------------
-- Section: sql/20251029_create_product_expiries.sql
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS product_expiries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  expiry_date DATE NOT NULL,
  quantity INT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (product_id),
  CONSTRAINT fk_pe_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO product_expiries (product_id, expiry_date, quantity, created_at)
SELECT id, expiry, NULL, NOW()
FROM products
WHERE expiry IS NOT NULL AND expiry != '';

-- -----------------------------------------------------------------------------
-- Section: sql/add_pos_messages_table.sql
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pos_messages` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `msg_id` VARCHAR(128) NOT NULL,
  `kiosk_key` VARCHAR(128) DEFAULT NULL,
  `payload` JSON NOT NULL,
  `processed` TINYINT(1) NOT NULL DEFAULT 0,
  `processed_by` INT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_msg_id` (`msg_id`),
  KEY `idx_processed` (`processed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Section: sql/20251029_add_last_activity_to_users.sql
-- -----------------------------------------------------------------------------
ALTER TABLE users
ADD COLUMN last_activity TIMESTAMP NULL DEFAULT NULL AFTER updated_at;

-- Optional backfill:
-- UPDATE users SET last_activity = updated_at WHERE last_activity IS NULL;

-- -----------------------------------------------------------------------------
-- Section: fix_inventory_reports_table.sql
-- -----------------------------------------------------------------------------
-- Ensure columns required by reports exist (quantity_changed, previous_quantity, new_quantity)
ALTER TABLE inventory_reports 
MODIFY COLUMN quantity INT NOT NULL DEFAULT 0
COMMENT 'Legacy column - same as quantity_changed';

ALTER TABLE inventory_reports 
ADD COLUMN IF NOT EXISTS quantity_changed INT NOT NULL DEFAULT 0 AFTER quantity
COMMENT 'Absolute amount of stock added or removed';

ALTER TABLE inventory_reports 
ADD COLUMN IF NOT EXISTS previous_quantity INT DEFAULT NULL AFTER quantity_changed
COMMENT 'Stock quantity before the change';

ALTER TABLE inventory_reports 
ADD COLUMN IF NOT EXISTS new_quantity INT DEFAULT NULL AFTER previous_quantity
COMMENT 'Stock quantity after the change';

-- -----------------------------------------------------------------------------
-- Section: fix_inventory_reports_columns.sql
-- -----------------------------------------------------------------------------
-- (Detailed fix script already applied above) - left as informational note.

-- -----------------------------------------------------------------------------
-- Section: migrations/2025-10-28_add_pending_to_users.sql
-- -----------------------------------------------------------------------------
ALTER TABLE `users`
    MODIFY COLUMN `status` ENUM('active','inactive','pending') NOT NULL DEFAULT 'pending';

UPDATE `users`
SET `status` = 'pending'
WHERE `status` IS NULL OR `status` = '' OR `status` NOT IN ('active','inactive','pending');

ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `email_verified` TINYINT(1) NOT NULL DEFAULT 0;

-- -----------------------------------------------------------------------------
-- Section: add_expiry_date_to_notifications.sql
-- -----------------------------------------------------------------------------
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS expiry_date DATE DEFAULT NULL;

UPDATE notifications n
JOIN products p ON n.product_id = p.id
SET n.expiry_date = p.expiry
WHERE n.type = 'expiry' AND n.expiry_date IS NULL;

-- -----------------------------------------------------------------------------
-- End of combined file
-- -----------------------------------------------------------------------------

