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
    $data = $_POST;
}

$id = isset($data['id']) ? intval($data['id']) : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Expiry id is required']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    // Delete from product_expiries table
    $stmt = $db->prepare("DELETE FROM product_expiries WHERE id = ?");
    $stmt->execute([$id]);
    $affected = $stmt->rowCount();

    if ($affected > 0) {
        echo json_encode(['success' => true, 'message' => 'Expiry removed']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Expiry not found or already removed']);
    }
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error', 'error' => $e->getMessage()]);
    exit;
}
?>
