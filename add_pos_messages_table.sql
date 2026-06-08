-- Migration: create pos_messages table for cross-browser/mobile scanner messages
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
