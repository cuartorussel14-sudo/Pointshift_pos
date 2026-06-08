<?php
/**
 * Email Configuration Setup
 * Helps configure Gmail SMTP settings for PHP Mailer
 */

require_once 'config.php';
User::requireLogin();

// Only admin can access this
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$title = 'Email Configuration Setup';
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'test_smtp') {
        $smtp_user = trim($_POST['smtp_user']);
        $smtp_pass = trim($_POST['smtp_pass']);
        $test_email = trim($_POST['test_email']);
        
        if ($smtp_user && $smtp_pass && $test_email) {
            // Set environment variables temporarily
            putenv("GMAIL_SMTP_USER=$smtp_user");
            putenv("GMAIL_SMTP_PASS=$smtp_pass");
            putenv("GMAIL_SMTP_PORT=587");
            putenv("GMAIL_SMTP_SECURE=tls");
            
            require_once 'classes/Mailer.php';
            
            $subject = "PointShift POS - SMTP Test";
            $body = "<h3>SMTP Configuration Test</h3>";
            $body .= "<p>This email confirms that your SMTP configuration is working correctly.</p>";
            $body .= "<p><strong>SMTP User:</strong> " . htmlspecialchars($smtp_user) . "</p>";
            $body .= "<p><strong>Test Time:</strong> " . date('Y-m-d H:i:s') . "</p>";
            $body .= "<p><strong>System:</strong> " . $_SERVER['HTTP_HOST'] . "</p>";
            
            $success = Mailer::sendEmail($test_email, $subject, $body, SITE_NAME);
            
            if ($success) {
                $message = "✅ SMTP test successful! Email sent to " . htmlspecialchars($test_email);
                $message_type = "success";
            } else {
                $message = "❌ SMTP test failed. Check your credentials and try again.";
                $message_type = "danger";
            }
        } else {
            $message = "⚠️ Please fill in all required fields.";
            $message_type = "warning";
        }
    }
}

ob_start();
?>

<div class="container py-4">
    <h2><i class="fas fa-cog"></i> Email Configuration Setup</h2>
    <p>Configure Gmail SMTP settings for PHP Mailer to enable email notifications.</p>
    
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
                    <h5><i class="fas fa-envelope"></i> Gmail SMTP Configuration</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="test_smtp">
                        
                        <div class="mb-3">
                            <label class="form-label">Gmail Address *</label>
                            <input type="email" class="form-control" name="smtp_user" required 
                                   placeholder="your-email@gmail.com">
                            <small class="form-text text-muted">Your Gmail address</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Gmail App Password *</label>
                            <input type="password" class="form-control" name="smtp_pass" required 
                                   placeholder="16-character app password">
                            <small class="form-text text-muted">
                                <a href="https://support.google.com/accounts/answer/185833" target="_blank">
                                    How to create Gmail App Password
                                </a>
                            </small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Test Email Address *</label>
                            <input type="email" class="form-control" name="test_email" required 
                                   placeholder="test@example.com">
                            <small class="form-text text-muted">Where to send the test email</small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Test SMTP Configuration
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-info-circle"></i> Setup Instructions</h5>
                </div>
                <div class="card-body">
                    <h6>Step 1: Enable 2FA</h6>
                    <p>Go to Google Account → Security → 2-Step Verification</p>
                    
                    <h6>Step 2: Create App Password</h6>
                    <p>Security → App passwords → Generate for "Mail"</p>
                    
                    <h6>Step 3: Test Configuration</h6>
                    <p>Use the form to test your settings</p>
                    
                    <h6>Step 4: Set Environment Variables</h6>
                    <p>Add to your system or .env file:</p>
                    <code>
                        GMAIL_SMTP_USER=your-email@gmail.com<br>
                        GMAIL_SMTP_PASS=your-app-password
                    </code>
                </div>
            </div>
            
            <div class="card mt-3">
                <div class="card-header">
                    <h5><i class="fas fa-link"></i> Quick Links</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="quick_email_test.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-test-tube"></i> Quick Email Test
                        </a>
                        <a href="staff/email.php" class="btn btn-outline-success btn-sm">
                            <i class="fas fa-user-tie"></i> Staff Email
                        </a>
                        <a href="cashier/email.php" class="btn btn-outline-info btn-sm">
                            <i class="fas fa-cash-register"></i> Cashier Email
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="alert alert-info mt-4">
        <h6><i class="fas fa-lightbulb"></i> Pro Tips:</h6>
        <ul class="mb-0">
            <li><strong>Use App Password:</strong> Never use your regular Gmail password</li>
            <li><strong>Enable 2FA:</strong> Required for App Passwords</li>
            <li><strong>Test First:</strong> Always test before going live</li>
            <li><strong>Check Logs:</strong> View email logs in <code>logs/email.log</code></li>
        </ul>
    </div>
</div>

<?php
$content = ob_get_clean();
include 'layout.php';
?>
