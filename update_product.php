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
if (!$data || !isset($data['product_id']) || !isset($data['expiry'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Product ID and expiry date are required']);
    exit();
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Update the product
    $stmt = $db->prepare("UPDATE products SET expiry = ?, last_updated_by = ? WHERE id = ?");
    if (!$stmt->execute([$data['expiry'], $_SESSION['user_id'], $data['product_id']])) {
        throw new Exception('Failed to update product');
    }

    // Get the updated product details
    $stmt = $db->prepare("SELECT p.*, c.name as category_name 
                         FROM products p 
                         LEFT JOIN categories c ON p.category_id = c.id 
                         WHERE p.id = ?");
    $stmt->execute([$data['product_id']]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        throw new Exception('Product not found');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Product updated successfully',
        'product' => $product
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
?>