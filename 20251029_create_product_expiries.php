<?php
// Run this from the browser or CLI to create product_expiries table and backfill existing product.expiry values.
require_once __DIR__ . '/../config.php';

try {
    $db = Database::getInstance()->getConnection();

    $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS product_expiries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  expiry_date DATE NOT NULL,
  quantity INT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (product_id),
  CONSTRAINT fk_pe_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Backfill existing product expiry values into product_expiries
INSERT INTO product_expiries (product_id, expiry_date, quantity, created_at)
SELECT id, expiry, NULL, NOW()
FROM products
WHERE expiry IS NOT NULL AND expiry != '';

-- Optional: clear products.expiry to avoid duplication (commented out - uncomment to apply)
-- UPDATE products SET expiry = NULL WHERE expiry IS NOT NULL AND expiry != '';
SQL;

    // split and execute statements
    $stmts = array_filter(array_map('trim', explode(';', $sql)));
    foreach ($stmts as $stmt) {
        if ($stmt === '') continue;
        $db->exec($stmt);
    }

    echo "Migration completed. product_expiries table created and backfilled.\n";
    echo "Note: products.expiry has NOT been cleared. If you want to remove the old single-expiry column values, uncomment the UPDATE in the migration file.\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}

?>