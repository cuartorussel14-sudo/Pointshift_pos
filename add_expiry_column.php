<?php
// Run this in the browser (or CLI via C:\xampp\php\php.exe) to add expiry_date to notifications if missing
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

try {
    $db = Database::getInstance()->getConnection();
    // Check if column exists
    $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'notifications' AND COLUMN_NAME = 'expiry_date'");
    $stmt->execute([DB_NAME]);
    $exists = (int)$stmt->fetchColumn();

    if ($exists === 0) {
        $db->exec("ALTER TABLE notifications ADD COLUMN expiry_date DATE DEFAULT NULL");
        $added = true;
    } else {
        $added = false;
    }

    // Backfill expiry_date for existing expiry notifications
    $db->exec("UPDATE notifications n JOIN products p ON n.product_id = p.id SET n.expiry_date = p.expiry WHERE n.type = 'expiry' AND (n.expiry_date IS NULL OR n.expiry_date = '')");

    echo json_encode(['success' => true, 'added' => $added, 'message' => $added ? 'Column added and backfilled' : 'Column already present, backfilled existing rows'], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
