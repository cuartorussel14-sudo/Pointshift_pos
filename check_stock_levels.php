<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/NotificationManager.php';

// Prevent duplicate runs within 5 minutes
$lockFile = __DIR__ . '/stock_check.lock';
if (file_exists($lockFile)) {
    $lastCheck = (int)file_get_contents($lockFile);
    if (time() - $lastCheck < 300) { // 5 minutes
        exit('Too soon since last check');
    }
}
file_put_contents($lockFile, time());

try {
    $db = Database::getInstance()->getConnection();
    $notificationManager = NotificationManager::getInstance($db);

    // Find products that are low or out of stock
    $prodStmt = $db->prepare("
        SELECT id, name, stock_quantity, low_stock_threshold 
        FROM products 
        WHERE stock_quantity <= low_stock_threshold 
        OR stock_quantity <= 0
    ");
    $prodStmt->execute();
    $prods = $prodStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($prods as $p) {
        // Check if there's already an unread notification for this product and type
        $checkStmt = $db->prepare("
            SELECT COUNT(*) 
            FROM notifications 
            WHERE product_id = ? 
            AND status = 'unread' 
            AND type IN ('low_stock','out_of_stock')
            AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $checkStmt->execute([$p['id']]);
        $exists = (int)$checkStmt->fetchColumn();

        if ($exists === 0) {
            // Create appropriate stock notification
            $notificationManager->createStockNotification(
                $p['id'],
                (int)$p['stock_quantity'],
                (int)$p['low_stock_threshold']
            );
        }
    }

    echo "Stock check completed successfully\n";
} catch (Exception $e) {
    error_log("Stock check error: " . $e->getMessage());
    echo "Error during stock check: " . $e->getMessage() . "\n";
}