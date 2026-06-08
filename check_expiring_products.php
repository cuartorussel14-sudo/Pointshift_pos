<?php
require_once 'config.php';
require_once 'classes/NotificationManager.php';

// This script should be run daily via cron job
// For testing, you can run it manually

function checkExpiringProducts() {
    $db = Database::getInstance()->getConnection();
    $notificationManager = NotificationManager::getInstance($db);

    echo "Starting expiry check...\n";

    // First, check if the expiry column exists
    try {
        $checkColumn = $db->query("SHOW COLUMNS FROM products LIKE 'expiry'");
        if ($checkColumn->rowCount() === 0) {
            echo "Error: expiry column does not exist in products table\n";
            return;
        }
    } catch (Exception $e) {
        echo "Error checking expiry column: " . $e->getMessage() . "\n";
        return;
    }

    // Get products expiring in the next 30 days or already expired
    $stmt = $db->prepare("
        SELECT 
            id, 
            name, 
            expiry,
            CURDATE() as today,
            DATE_ADD(CURDATE(), INTERVAL 30 DAY) as thirty_days_ahead,
            CASE 
                WHEN expiry < CURDATE() THEN 'expired'
                WHEN expiry <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'expiring_soon'
                ELSE 'ok'
            END as expiry_status
        FROM products 
        WHERE 
            expiry IS NOT NULL 
            AND expiry != ''
            AND (
                expiry <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                OR expiry < CURDATE()
            )
    ");
    
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($products) . " products with expiry dates to check\n";

    foreach ($products as $product) {
        echo "\nChecking product: {$product['name']}\n";
        echo "Expiry date: {$product['expiry']}\n";
        echo "Status: {$product['expiry_status']}\n";

        $existingCheck = $db->prepare("
            SELECT id FROM notifications 
            WHERE product_id = ? 
            AND type = 'expiry' 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $existingCheck->execute([$product['id']]);
        $hasExisting = $existingCheck->fetch();
        
        echo "Has existing notification in last 24h: " . ($hasExisting ? "Yes" : "No") . "\n";
        
        // Only create notification if one hasn't been created in the last 24 hours
        if (!$hasExisting) {
            if ($product['expiry_status'] === 'expired') {
                echo "Creating expired notification...\n";
                try {
                    $result = $notificationManager->create(
                        "Product Expired: {$product['name']} has expired on {$product['expiry']}",
                        'expiry',
                        $product['id']
                    );
                    echo "Notification created: " . ($result ? "Success" : "Failed") . "\n";
                } catch (Exception $e) {
                    echo "Error creating notification: " . $e->getMessage() . "\n";
                }
            } else if ($product['expiry_status'] === 'expiring_soon') {
                $daysUntilExpiry = (strtotime($product['expiry']) - time()) / (60 * 60 * 24);
                echo "Creating expiring soon notification (Days until expiry: " . round($daysUntilExpiry) . ")...\n";
                try {
                    $result = $notificationManager->create(
                        "Expiring Soon: {$product['name']} will expire in " . round($daysUntilExpiry) . " days ({$product['expiry']})",
                        'expiry',
                        $product['id']
                    );
                    echo "Notification created: " . ($result ? "Success" : "Failed") . "\n";
                } catch (Exception $e) {
                    echo "Error creating notification: " . $e->getMessage() . "\n";
                }
            }
        }
    }
}

// Run the check
try {
    checkExpiringProducts();
    echo "Expiry check completed successfully.\n";
} catch (Exception $e) {
    error_log("Error checking product expiry: " . $e->getMessage());
    echo "Error checking expiry. Check error logs for details.\n";
}
?>