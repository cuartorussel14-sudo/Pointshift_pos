<?php
require_once 'config.php';
User::requireLogin();

// Only admin can access this setup page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$title = 'Setup Notifications Database';
$setupComplete = false;
$errorMessage = '';
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_database'])) {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Create notifications table
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
        
        // Create system_notifications table
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
        
        // Insert sample notifications
        $sampleNotifications = [
            ['Welcome to PointShift POS System!', 'success', 'unread'],
            ['System is running smoothly', 'info', 'unread'],
            ['Remember to backup your data regularly', 'warning', 'unread']
        ];
        
        $stmt = $db->prepare("INSERT INTO notifications (message, type, status, created_at) VALUES (?, ?, ?, NOW())");
        foreach ($sampleNotifications as $notification) {
            $stmt->execute($notification);
        }
        
        $setupComplete = true;
        $successMessage = "Notifications database setup completed successfully!";
        
    } catch (Exception $e) {
        $errorMessage = "Error setting up database: " . $e->getMessage();
    }
}

ob_start();
?>

<div class="container py-4">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h4><i class="fas fa-database"></i> Setup Notifications Database</h4>
                </div>
                <div class="card-body">
                    <?php if ($errorMessage): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($errorMessage); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($successMessage): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMessage); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!$setupComplete): ?>
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle"></i> What this will do:</h6>
                            <ul>
                                <li>Create the <code>notifications</code> table</li>
                                <li>Create the <code>system_notifications</code> table</li>
                                <li>Add necessary indexes for performance</li>
                                <li>Insert sample notifications for testing</li>
                            </ul>
                        </div>
                        
                        <form method="POST">
                            <button type="submit" name="setup_database" class="btn btn-primary btn-lg">
                                <i class="fas fa-cog"></i> Setup Notifications Database
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-success">
                            <h6><i class="fas fa-check-circle"></i> Setup Complete!</h6>
                            <p>The notification system is now ready to use. You can:</p>
                            <ul>
                                <li>View notifications in the bell icon dropdown</li>
                                <li>Test the system with the sample notifications</li>
                                <li>Create new notifications programmatically</li>
                            </ul>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <a href="test_add_notification.php" class="btn btn-success">
                                <i class="fas fa-test-tube"></i> Test Notifications
                            </a>
                            <a href="dashboard.php" class="btn btn-primary">
                                <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include 'layout.php';
?>
