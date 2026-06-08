<?php
require_once '../config.php';
User::requireLogin();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get JSON data from request body
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['barcode'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Barcode is required']);
    exit();
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Prepare and execute query
    $stmt = $db->prepare("SELECT * FROM products WHERE barcode = ?");
    $stmt->execute([$data['barcode']]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($product) {
        // Return product information
        echo json_encode($product);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Product not found']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>
