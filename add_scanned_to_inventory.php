<?php
require_once '../config.php';
User::requireLogin();
header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || empty($data['barcode'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Barcode is required']);
    exit();
}

$barcode = trim($data['barcode']);
$quantity = isset($data['quantity']) ? (int)$data['quantity'] : 1;
if ($quantity <= 0) $quantity = 1;

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT id, name, sku, barcode, price, stock_quantity FROM products WHERE barcode = ? LIMIT 1");
    $stmt->execute([$barcode]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    $userId = $_SESSION['user_id'] ?? null;

    if (!$product) {
        // Insert new product with values from request or defaults
        $name = isset($data['name']) && $data['name'] !== '' ? $data['name'] : 'New Product';
        $sku = isset($data['sku']) && $data['sku'] !== '' ? $data['sku'] : null;
        $categoryName = isset($data['category']) && $data['category'] !== '' ? $data['category'] : null;
        $categoryId = null;
        if ($categoryName) {
            // Try to find category by name
            $catStmt = $db->prepare("SELECT id FROM categories WHERE name = ? LIMIT 1");
            $catStmt->execute([$categoryName]);
            $catRow = $catStmt->fetch(PDO::FETCH_ASSOC);
            if ($catRow) {
                $categoryId = $catRow['id'];
            } else {
                // Create new category
                $catInsert = $db->prepare("INSERT INTO categories (name) VALUES (?)");
                $catInsert->execute([$categoryName]);
                $categoryId = $db->lastInsertId();
            }
        }
        $expiry = isset($data['expiry']) && $data['expiry'] !== '' ? $data['expiry'] : null;
        $price = isset($data['price']) && is_numeric($data['price']) ? floatval($data['price']) : 0.00;
        $stock = $quantity;
        $status = 'active';
        $description = isset($data['description']) ? $data['description'] : null;
        $insert = $db->prepare("INSERT INTO products (name, sku, category_id, price, stock_quantity, barcode, expiry, status, last_updated_by, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $insert->execute([$name, $sku, $categoryId, $price, $stock, $barcode, $expiry, $status, $userId, $description]);
        $newId = $db->lastInsertId();
        $stmt2 = $db->prepare("SELECT id, name, sku, barcode, price, stock_quantity, category_id, expiry, description, status FROM products WHERE id = ?");
        $stmt2->execute([$newId]);
        $updated = $stmt2->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'product' => $updated, 'new' => true]);
        exit();
    } else {
        $newStock = ((int)$product['stock_quantity']) + $quantity;
        $update = $db->prepare("UPDATE products SET stock_quantity = ?, last_updated_by = ? WHERE id = ?");
        $update->execute([$newStock, $userId, $product['id']]);
        
        // Check for low stock notifications after stock update
        try {
            require_once __DIR__ . '/../classes/NotificationManager.php';
            $notificationManager = NotificationManager::getInstance($db);
            $notificationManager->createStockNotification(
                $product['id'],
                $newStock,
                (int)$product['low_stock_threshold']
            );
        } catch (Exception $e) {
            // Ignore notification errors to avoid blocking inventory updates
        }
        
        // Return updated product info (minimal)
        $stmt2 = $db->prepare("SELECT id, name, sku, barcode, price, stock_quantity FROM products WHERE id = ?");
        $stmt2->execute([$product['id']]);
        $updated = $stmt2->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'product' => $updated, 'new' => false]);
        exit();
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
    exit();
}
?>