<?php
// Run this script from command line or cron to create notifications for products expiring within $days days
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Notification.php';

$days = 30;
$db = Database::getInstance()->getConnection();

// Check for already expired products
$expiredStmt = $db->prepare("
    SELECT id, name, expiry, stock_quantity 
    FROM products 
    WHERE expiry IS NOT NULL 
    AND expiry <> '' 
    AND expiry < CURDATE()
    AND stock_quantity > 0
");
$expiredStmt->execute();
$expiredRows = $expiredStmt->fetchAll(PDO::FETCH_ASSOC);

// Check for products expiring soon
$upcomingStmt = $db->prepare("
    SELECT id, name, expiry, stock_quantity 
    FROM products 
    WHERE expiry IS NOT NULL 
    AND expiry <> '' 
    AND expiry <= DATE_ADD(CURDATE(), INTERVAL ? DAY) 
    AND expiry >= CURDATE()
    AND stock_quantity > 0
");
$upcomingStmt->execute([$days]);
$upcomingRows = $upcomingStmt->fetchAll(PDO::FETCH_ASSOC);

// Process expired products
foreach ($expiredRows as $r) {
    $message = "EXPIRED: Product '{$r['name']}' has expired on " . date('Y-m-d', strtotime($r['expiry'])) . ".";
    // Log action
    @file_put_contents(__DIR__ . '/../logs/expiry_notifications.log', date('[Y-m-d H:i:s] ') . "Expired notification prepared for product_id={$r['id']}, name={$r['name']}\n", FILE_APPEND);
    // Avoid duplicate notifications: check recent notifications for same product and type
    $check = $db->prepare("SELECT COUNT(*) FROM notifications WHERE product_id = ? AND type = 'expiry' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $check->execute([$r['id']]);
    $count = $check->fetchColumn();
    if ($count == 0) {
        $notif = Notification::create($db, $message, 'expiry', $r['id']);
        @file_put_contents(__DIR__ . '/../logs/expiry_notifications.log', date('[Y-m-d H:i:s] ') . "Created expired notification id=" . ($notif['id'] ?? 'unknown') . " for product_id={$r['id']}\n", FILE_APPEND);
    }
}

// Process upcoming expiries
foreach ($upcomingRows as $r) {
    $expiryDate = new DateTime($r['expiry']);
    $today = new DateTime();
    $interval = $today->diff($expiryDate);
    $daysLeft = (int)$interval->format('%a');
    $message = "Product '{$r['name']}' will expire in {$daysLeft} day" . ($daysLeft !== 1 ? 's' : '') . " (" . date('Y-m-d', strtotime($r['expiry'])) . ").";

    // Avoid duplicate notifications
    $check = $db->prepare("SELECT COUNT(*) FROM notifications WHERE product_id = ? AND type = 'expiry' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $check->execute([$r['id']]);
    $count = $check->fetchColumn();
    if ($count == 0) {
        Notification::create($db, $message, 'expiry', $r['id']);
    }
}
@file_put_contents(__DIR__ . '/../logs/expiry_notifications.log', date('[Y-m-d H:i:s] ') . "Scan completed: expired=" . count($expiredRows) . ", upcoming=" . count($upcomingRows) . "\n", FILE_APPEND);
echo "Expiry notifications scan completed. Found: " . (count($expiredRows) + count($upcomingRows)) . " items (" . count($expiredRows) . " expired, " . count($upcomingRows) . " upcoming).\n";
?>
