-- Update/create pos_messages table to support cashier routing from mobile app

-- Create table if it does not exist
CREATE TABLE IF NOT EXISTS pos_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  msg_id VARCHAR(64) NOT NULL,
  user_id INT NULL,
  cashier_id INT NULL,
  channel VARCHAR(64) NULL,
  kiosk_key VARCHAR(64) NULL,
  payload LONGTEXT NULL,
  processed TINYINT(1) NOT NULL DEFAULT 0,
  processed_by INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at TIMESTAMP NULL DEFAULT NULL,
  KEY idx_processed (processed),
  KEY idx_cashier (cashier_id),
  KEY idx_channel (channel),
  KEY idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add routing columns if they are missing (MySQL lacks IF NOT EXISTS for columns in older versions)
-- Each block checks column existence before attempting to add.

SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_messages' AND COLUMN_NAME = 'user_id');
SET @stmt := IF(@col_exists = 0, 'ALTER TABLE pos_messages ADD COLUMN user_id INT NULL AFTER msg_id', 'DO 0');
PREPARE alter_if_missing FROM @stmt; EXECUTE alter_if_missing; DEALLOCATE PREPARE alter_if_missing;

SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_messages' AND COLUMN_NAME = 'cashier_id');
SET @stmt := IF(@col_exists = 0, 'ALTER TABLE pos_messages ADD COLUMN cashier_id INT NULL AFTER user_id', 'DO 0');
PREPARE alter_if_missing FROM @stmt; EXECUTE alter_if_missing; DEALLOCATE PREPARE alter_if_missing;

SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_messages' AND COLUMN_NAME = 'channel');
SET @stmt := IF(@col_exists = 0, 'ALTER TABLE pos_messages ADD COLUMN channel VARCHAR(64) NULL AFTER cashier_id', 'DO 0');
PREPARE alter_if_missing FROM @stmt; EXECUTE alter_if_missing; DEALLOCATE PREPARE alter_if_missing;

-- Ensure created_at has a default current timestamp
SET @col_type_ok := (SELECT CASE 
  WHEN COLUMN_DEFAULT = 'CURRENT_TIMESTAMP' THEN 1 ELSE 0 END
  FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_messages' AND COLUMN_NAME = 'created_at' LIMIT 1);
SET @stmt := IF(@col_type_ok = 1, 'DO 0', 'ALTER TABLE pos_messages MODIFY created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
PREPARE alter_if_missing FROM @stmt; EXECUTE alter_if_missing; DEALLOCATE PREPARE alter_if_missing;

-- Create indexes if they are missing
SET @idx_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_messages' AND INDEX_NAME = 'idx_cashier');
SET @stmt := IF(@idx_exists = 0, 'CREATE INDEX idx_cashier ON pos_messages (cashier_id)', 'DO 0');
PREPARE alter_if_missing FROM @stmt; EXECUTE alter_if_missing; DEALLOCATE PREPARE alter_if_missing;

SET @idx_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_messages' AND INDEX_NAME = 'idx_channel');
SET @stmt := IF(@idx_exists = 0, 'CREATE INDEX idx_channel ON pos_messages (channel)', 'DO 0');
PREPARE alter_if_missing FROM @stmt; EXECUTE alter_if_missing; DEALLOCATE PREPARE alter_if_missing;

SET @idx_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pos_messages' AND INDEX_NAME = 'idx_processed');
SET @stmt := IF(@idx_exists = 0, 'CREATE INDEX idx_processed ON pos_messages (processed)', 'DO 0');
PREPARE alter_if_missing FROM @stmt; EXECUTE alter_if_missing; DEALLOCATE PREPARE alter_if_missing;
