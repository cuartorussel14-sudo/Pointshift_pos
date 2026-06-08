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

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.barcode = ? OR p.sku = ? LIMIT 1");
    $stmt->execute([$barcode, $barcode]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit();
    }

    // normalize fields
    $product['price'] = isset($product['price']) ? (float)$product['price'] : 0.0;
    $product['stock_quantity'] = isset($product['stock_quantity']) ? (int)$product['stock_quantity'] : 0;
    $product['expiry'] = isset($product['expiry']) && $product['expiry'] !== '' ? $product['expiry'] : null;

    echo json_encode(['success' => true, 'product' => $product]);
    exit();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
    exit();
}

?>
