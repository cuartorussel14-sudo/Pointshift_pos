<?php
require_once 'config.php';
header('Content-Type: application/json');

// Support both form-encoded POST and JSON body
$rawInput = file_get_contents('php://input');
$contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false || !$rawInput) {
    $json = $rawInput ? json_decode($rawInput, true) : null;
    $data = is_array($json) ? $json : $_POST;
} else {
    // fallback to $_POST for form submissions
    $data = $_POST;
}

$id = isset($data['product_id']) ? intval($data['product_id']) : 0;
$expiry = isset($data['expiry']) && $data['expiry'] !== '' ? trim($data['expiry']) : null;
$quantity = isset($data['quantity']) && $data['quantity'] !== '' ? intval($data['quantity']) : null;

if ($id <= 0 || empty($expiry)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Product id and expiry date are required']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    // Ensure product_expiries table exists (supports multiple expiry records per product)
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS product_expiries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            expiry_date DATE NOT NULL,
            quantity INT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX (product_id),
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {
        // if creation fails, we'll fall back later
    }

    // Try inserting into product_expiries
    try {
        $ins = $db->prepare("INSERT INTO product_expiries (product_id, expiry_date, quantity, created_at) VALUES (?, ?, ?, NOW())");
        $ins->execute([$id, $expiry, $quantity]);
        $newId = $db->lastInsertId();
        echo json_encode(['success' => true, 'inserted_id' => $newId, 'message' => 'Expiry added']);
        exit;
    } catch (Exception $e) {
        // insert failed; will try fallback below
    }

    // Fallback: update products.expiry (single expiry)
    $upd = $db->prepare("UPDATE products SET expiry = ? WHERE id = ?");
    $upd->execute([$expiry, $id]);
    echo json_encode(['success' => true, 'message' => 'Product expiry updated (fallback)']);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error', 'error' => $e->getMessage()]);
    exit;
}

?>
