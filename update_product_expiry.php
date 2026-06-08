<?php
require_once '../config.php';
User::requireLogin();
header('Content-Type: application/json');

require_once __DIR__ . '/../classes/Notification.php';

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
if (!$data || !isset($data['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Product id is required']);
    exit();
}

$id = (int)$data['id'];
$expiry = isset($data['expiry']) && $data['expiry'] !== '' ? trim($data['expiry']) : null;

try {
    $db = Database::getInstance()->getConnection();
    // Validate product exists
    $stmt = $db->prepare("SELECT id FROM products WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $prod = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$prod) {
        http_response_code(404);
        echo json_encode(['error' => 'Product not found']);
        exit();
    }

    $userId = $_SESSION['user_id'] ?? null;
    $update = $db->prepare("UPDATE products SET expiry = ?, last_updated_by = ? WHERE id = ?");
    $update->execute([$expiry, $userId, $id]);

    $stmt2 = $db->prepare("SELECT id, name, sku, barcode, price, stock_quantity, expiry, description FROM products WHERE id = ?");
    $stmt2->execute([$id]);
    $updated = $stmt2->fetch(PDO::FETCH_ASSOC);

    // If expiry is set to today or in the past, create an immediate expiry notification
    try {
        if (!empty($updated['expiry'])) {
            $expiryDate = date('Y-m-d', strtotime($updated['expiry']));
            $today = date('Y-m-d');
            if ($expiryDate <= $today) {
                $message = "Product '{$updated['name']}' has expired on {$expiryDate}.";
                // Use static Notification helper to create a record
                Notification::create($db, $message, 'expiry', $id);
                @file_put_contents(__DIR__ . '/../logs/expiry_notifications.log', date('[Y-m-d H:i:s] ') . "Created immediate expiry notif for product_id={$id}, expiry={$expiryDate}\n", FILE_APPEND);
            }
        }
    } catch (Exception $e) {
        // non-fatal: log and continue
        @file_put_contents(__DIR__ . '/../logs/expiry_notifications.log', date('[Y-m-d H:i:s] ') . "Error creating immediate expiry notif: " . $e->getMessage() . "\n", FILE_APPEND);
    }

    echo json_encode(['success' => true, 'product' => $updated]);
    exit();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
    exit();
}

?>
