-- Add GCash QR Code table for payment QR codes
-- Run this migration to add support for GCash QR code uploads

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

-- Insert default placeholder
INSERT INTO `payment_qrcodes` (`payment_method`, `qr_code_path`, `description`, `is_active`) 
VALUES ('gcash', '', 'GCash Payment QR Code', 1)
ON DUPLICATE KEY UPDATE payment_method=payment_method;
