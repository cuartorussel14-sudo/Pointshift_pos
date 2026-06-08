-- SQL migration to create `product_expiries` and backfill existing product expiry values

CREATE TABLE IF NOT EXISTS product_expiries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  expiry_date DATE NOT NULL,
  quantity INT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (product_id),
  CONSTRAINT fk_pe_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert existing single expiry values into product_expiries
INSERT INTO product_expiries (product_id, expiry_date, quantity, created_at)
SELECT id, expiry, NULL, NOW()
FROM products
WHERE expiry IS NOT NULL AND expiry != '';

-- Optional: clear products.expiry to avoid duplication
-- UPDATE products SET expiry = NULL WHERE expiry IS NOT NULL AND expiry != '';
