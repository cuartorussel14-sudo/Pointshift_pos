<?php
/**
 * Database setup script for notifications system
 * This script will create all necessary tables and indexes for the notification system
 */

require_once 'config.php';

echo "=== Setting up Notifications Database ===\n\n";

try {
    $db = Database::getInstance()->getConnection();
    
    // 1. Create notifications table
    echo "1. Creating notifications table...\n";
    $sql = "
    CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        message VARCHAR(500) NOT NULL,
        type VARCHAR(50) NOT NULL DEFAULT 'info',
        status VARCHAR(20) NOT NULL DEFAULT 'unread',
        product_id INT DEFAULT NULL,
        user_id INT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_notifications_user_id (user_id),
        INDEX idx_notifications_status (status),
        INDEX idx_notifications_created_at (created_at),
        INDEX idx_notifications_type (type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $db->exec($sql);
    echo "   ✓ Notifications table created successfully\n";
    
    // 2. Create system_notifications table
    echo "\n2. Creating system_notifications table...\n";
    $sql = "
    CREATE TABLE IF NOT EXISTS system_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        message TEXT NOT NULL,
        type VARCHAR(50) DEFAULT 'info',
        status VARCHAR(20) DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME DEFAULT NULL,
        INDEX idx_system_notifications_status (status),
        INDEX idx_system_notifications_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $db->exec($sql);
    echo "   ✓ System notifications table created successfully\n";
    
    // 3. Add foreign key constraints if users table exists
    echo "\n3. Adding foreign key constraints...\n";
    try {
        // Check if users table exists
        $stmt = $db->query("SHOW TABLES LIKE 'users'");
        if ($stmt->fetch()) {
            // Add foreign key for user_id
            $db->exec("ALTER TABLE notifications ADD CONSTRAINT fk_notifications_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL");
            echo "   ✓ Foreign key constraint added for user_id\n";
        } else {
            echo "   ⚠ Users table not found, skipping foreign key constraint\n";
        }
        
        // Check if products table exists
        $stmt = $db->query("SHOW TABLES LIKE 'products'");
        if ($stmt->fetch()) {
            // Add foreign key for product_id
            $db->exec("ALTER TABLE notifications ADD CONSTRAINT fk_notifications_product_id FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL");
            echo "   ✓ Foreign key constraint added for product_id\n";
        } else {
            echo "   ⚠ Products table not found, skipping foreign key constraint\n";
        }
    } catch (Exception $e) {
        echo "   ⚠ Could not add foreign key constraints: " . $e->getMessage() . "\n";
    }
    
    // 4. Insert some sample notifications for testing
    echo "\n4. Inserting sample notifications...\n";
    $sampleNotifications = [
        [
            'message' => 'Welcome to PointShift POS System!',
            'type' => 'success',
            'status' => 'unread'
        ],
        [
            'message' => 'System is running smoothly',
            'type' => 'info',
            'status' => 'unread'
        ],
        [
            'message' => 'Remember to backup your data regularly',
            'type' => 'warning',
            'status' => 'unread'
        ]
    ];
    
    $stmt = $db->prepare("
        INSERT INTO notifications (message, type, status, created_at) 
        VALUES (?, ?, ?, NOW())
    ");
    
    $inserted = 0;
    foreach ($sampleNotifications as $notification) {
        try {
            $stmt->execute([
                $notification['message'],
                $notification['type'],
                $notification['status']
            ]);
            $inserted++;
        } catch (Exception $e) {
            echo "   ⚠ Could not insert sample notification: " . $e->getMessage() . "\n";
        }
    }
    
    echo "   ✓ Inserted {$inserted} sample notifications\n";
    
    // 5. Verify the setup
    echo "\n5. Verifying setup...\n";
    
    // Check notifications table
    $stmt = $db->query("SELECT COUNT(*) as count FROM notifications");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "   ✓ Notifications table has {$result['count']} records\n";
    
    // Check unread count
    $stmt = $db->query("SELECT COUNT(*) as count FROM notifications WHERE status = 'unread'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "   ✓ Unread notifications: {$result['count']}\n";
    
    echo "\n=== Database Setup Complete! ===\n";
    echo "The notification system is now ready to use.\n";
    echo "You can test it by visiting the web interface.\n\n";
    
} catch (Exception $e) {
    echo "\n❌ Error setting up database: " . $e->getMessage() . "\n";
    echo "Please check your database connection and try again.\n";
}
?>
