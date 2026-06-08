<?php
require_once 'config.php';
header('Content-Type: application/json');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product id']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    // Try to fetch from product_expiries table (if exists)
    try {
        $stmt = $db->prepare("SELECT id, expiry_date AS expiry, quantity FROM product_expiries WHERE product_id = ? ORDER BY expiry_date ASC");
        $stmt->execute([$id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($rows)) {
            // Normalize dates
            $exp = array_map(function($r){ return ['id' => $r['id'], 'expiry' => $r['expiry'], 'quantity' => isset($r['quantity']) ? intval($r['quantity']) : null]; }, $rows);
            echo json_encode(['success' => true, 'expiries' => $exp]);
            exit;
        }
    } catch (Exception $e) {
        // Table may not exist; fall back to single expiry
    }

    // Fallback: return product.expiry if present
    $pstmt = $db->prepare("SELECT expiry FROM products WHERE id = ? LIMIT 1");
    $pstmt->execute([$id]);
    $prod = $pstmt->fetch(PDO::FETCH_ASSOC);
    if ($prod && !empty($prod['expiry'])) {
        echo json_encode(['success' => true, 'expiries' => [['expiry' => $prod['expiry'], 'quantity' => null]]]);
        exit;
    }

    // No expiry data
    echo json_encode(['success' => true, 'expiries' => [] , 'message' => 'No expiry dates found']);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error', 'error' => $e->getMessage()]);
    exit;
}

?>