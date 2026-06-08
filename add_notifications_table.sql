-- Migration: create notifications table
CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  message VARCHAR(255) NOT NULL,
  type VARCHAR(50) NOT NULL, -- e.g., 'low_stock', 'expiry', 'transaction'
  status VARCHAR(20) NOT NULL DEFAULT 'unread', -- unread, read
  product_id INT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;