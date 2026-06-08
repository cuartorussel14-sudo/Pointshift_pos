<?php
require_once 'config.php';
User::requireLogin();

// Only admin can access this setup page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$title = 'Setup Email Configuration';
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'test_email') {
        $test_email = trim($_POST['test_email']);
        $admin_email = trim($_POST['admin_email']);
        
        if ($test_email && $admin_email) {
            require_once 'classes/Mailer.php';
            
            $subject = "PointShift POS - Email Test";
            $body = "<h3>Email Configuration Test</h3>";
            $body .= "<p>This is a test email from PointShift POS system.</p>";
            $body .= "<p><strong>Test Details:</strong></p>";
            $body .= "<ul>";
            $body .= "<li>Sent to: " . htmlspecialchars($admin_email) . "</li>";
            $body .= "<li>Test email: " . htmlspecialchars($test_email) . "</li>";
            $body .= "<li>Time: " . date('Y-m-d H:i:s') . "</li>";
            $body .= "<li>System: " . $_SERVER['HTTP_HOST'] . "</li>";
            $body .= "</ul>";
            $body .= "<p>If you receive this email, your email configuration is working correctly!</p>";
            
            $success = Mailer::sendEmail($test_email, $subject, $body, SITE_NAME);
            
            if ($success) {
                $message = "Test email sent successfully to " . htmlspecialchars($test_email) . "!";
                $message_type = "success";
            } else {
                $message = "Failed to send test email. Check your email configuration.";
                $message_type = "danger";
            }
        } else {
            $message = "Please provide both test email and admin email addresses.";
            $message_type = "warning";
        }
    }
}

ob_start();
?>

<div class="container py-4">
    <h2><i class="fas fa-envelope"></i> Email Configuration Setup</h2>
    <p>Configure PHP Mailer settings for staff and cashier to send messages to admin.</p>
    
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-cog"></i> Email Configuration</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle"></i> Current Email System Status:</h6>
                        <ul class="mb-0">
                            <li><strong>Staff Email:</strong> ✅ Enabled with PHP Mailer</li>
                            <li><strong>Cashier Email:</strong> ✅ Enabled with PHP Mailer</li>
                            <li><strong>Email Notifications:</strong> ✅ Automatic notifications to admin</li>
                        </ul>
                    </div>
                    
                    <h6>How the Email System Works:</h6>
                    <ol>
                        <li><strong>Staff/Cashier</strong> sends message through the system</li>
                        <li><strong>Message</strong> is stored in the database</li>
                        <li><strong>Email notification</strong> is automatically sent to admin's email</li>
                        <li><strong>Admin</strong> receives both in-system message and email notification</li>
                    </ol>
                    
                    <h6>Email Configuration Options:</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card border-primary">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0">Option 1: Gmail SMTP (Recommended)</h6>
                                </div>
                                <div class="card-body">
                                    <p>For production use with Gmail:</p>
                                    <ol>
                                        <li>Set up Gmail App Password</li>
                                        <li>Configure environment variables:</li>
                                        <ul>
                                            <li><code>GMAIL_SMTP_USER</code> - Your Gmail address</li>
                                            <li><code>GMAIL_SMTP_PASS</code> - Your Gmail App Password</li>
                                        </ul>
                                    </ol>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card border-secondary">
                                <div class="card-header bg-secondary text-white">
                                    <h6 class="mb-0">Option 2: PHP mail() Function</h6>
                                </div>
                                <div class="card-body">
                                    <p>For local development:</p>
                                    <ul>
                                        <li>Uses server's built-in mail function</li>
                                        <li>No additional configuration needed</li>
                                        <li>May not work on all hosting providers</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-test-tube"></i> Test Email System</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="test_email">
                        
                        <div class="mb-3">
                            <label class="form-label">Admin Email Address *</label>
                            <input type="email" class="form-control" name="admin_email" required 
                                   placeholder="admin@example.com" 
                                   value="<?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?>">
                            <small class="form-text text-muted">The admin's email address</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Test Email Address *</label>
                            <input type="email" class="form-control" name="test_email" required 
                                   placeholder="test@example.com">
                            <small class="form-text text-muted">Where to send the test email</small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-paper-plane"></i> Send Test Email
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="card mt-3">
                <div class="card-header">
                    <h5><i class="fas fa-link"></i> Quick Links</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="staff/email.php" class="btn btn-outline-primary">
                            <i class="fas fa-user-tie"></i> Staff Email System
                        </a>
                        <a href="cashier/email.php" class="btn btn-outline-success">
                            <i class="fas fa-cash-register"></i> Cashier Email System
                        </a>
                        <a href="messages.php" class="btn btn-outline-danger">
                            <i class="fas fa-envelope"></i> Admin Messages
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="alert alert-success mt-4">
        <h6><i class="fas fa-check-circle"></i> Email System Features:</h6>
        <ul class="mb-0">
            <li><strong>Staff & Cashier</strong> can send messages to admin</li>
            <li><strong>Automatic email notifications</strong> sent to admin</li>
            <li><strong>Conversation threading</strong> for organized communication</li>
            <li><strong>Real-time messaging</strong> within the system</li>
            <li><strong>Email logging</strong> for debugging and monitoring</li>
        </ul>
    </div>
</div>

<?php
$content = ob_get_clean();
include 'layout.php';
?>
