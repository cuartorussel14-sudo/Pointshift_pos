<?php
// This script should be run periodically (e.g., daily via cron) to clean up old notifications
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Notification.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Delete notifications older than 30 days
    $deleted = Notification::deleteOld($db, 30);
    
    echo "Cleanup completed. Old notifications have been removed.\n";
    
} catch (Exception $e) {
    echo "Error during cleanup: " . $e->getMessage() . "\n";
    exit(1);
}