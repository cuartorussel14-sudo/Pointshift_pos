<?php
/**
 * Quick Email Configuration Test
 * This will help identify and fix the email configuration issues
 */

require_once 'config.php';

echo "<h2>🔧 Email Configuration Diagnostic</h2>";

// Check current configuration
echo "<h3>📋 Current Configuration:</h3>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Setting</th><th>Value</th><th>Status</th></tr>";

$configs = [
    'GMAIL_SMTP_USER' => defined('GMAIL_SMTP_USER') ? GMAIL_SMTP_USER : 'Not Set',
    'GMAIL_SMTP_PASS' => defined('GMAIL_SMTP_PASS') ? 'Set (' . strlen(GMAIL_SMTP_PASS) . ' chars)' : 'Not Set',
    'GMAIL_SMTP_PORT' => defined('GMAIL_SMTP_PORT') ? GMAIL_SMTP_PORT : 'Not Set',
    'GMAIL_SMTP_SECURE' => defined('GMAIL_SMTP_SECURE') ? GMAIL_SMTP_SECURE : 'Not Set',
    'SITE_NAME' => SITE_NAME,
    'SITE_URL' => SITE_URL
];

foreach ($configs as $key => $value) {
    $status = ($value !== 'Not Set') ? '✅' : '❌';
    echo "<tr><td>{$key}</td><td>{$value}</td><td>{$status}</td></tr>";
}
echo "</table>";

// Check PHPMailer
echo "<h3>📦 PHPMailer Status:</h3>";
if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
    echo "✅ PHPMailer is available<br>";
} else {
    echo "❌ PHPMailer is not available<br>";
    echo "Please install PHPMailer via Composer: <code>composer require phpmailer/phpmailer</code><br>";
}

// Test SMTP connection
echo "<h3>🔌 SMTP Connection Test:</h3>";

if (class_exists('PHPMailer\\PHPMailer\\PHPMailer') && defined('GMAIL_SMTP_USER') && defined('GMAIL_SMTP_PASS')) {
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = GMAIL_SMTP_USER;
        $mail->Password = GMAIL_SMTP_PASS;
        $mail->SMTPSecure = GMAIL_SMTP_SECURE;
        $mail->Port = GMAIL_SMTP_PORT;
        
        // Enable debug output
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = function($str, $level) {
            echo "<div style='background: #f8f9fa; padding: 5px; margin: 2px; font-family: monospace; font-size: 12px;'>" . htmlspecialchars($str) . "</div>";
        };
        
        echo "<strong>Attempting SMTP connection...</strong><br>";
        $mail->smtpConnect();
        echo "<br><strong>✅ SMTP connection successful!</strong><br>";
        
        echo "<br><strong>Attempting SMTP authentication...</strong><br>";
        $mail->smtpAuthenticate();
        echo "<br><strong>✅ SMTP authentication successful!</strong><br>";
        
        $mail->smtpClose();
        
    } catch (Exception $e) {
        echo "<br><strong>❌ SMTP Error:</strong> " . htmlspecialchars($e->getMessage()) . "<br>";
        
        echo "<br><h4>🔧 Common Solutions:</h4>";
        echo "<ol>";
        echo "<li><strong>Enable 2-Factor Authentication</strong> on your Gmail account</li>";
        echo "<li><strong>Generate an App Password</strong>:<br>";
        echo "   - Go to Google Account → Security → 2-Step Verification → App passwords<br>";
        echo "   - Generate a new app password for 'Mail'<br>";
        echo "   - Use this password (not your regular Gmail password)</li>";
        echo "<li><strong>Update config.php</strong> with the App Password</li>";
        echo "<li><strong>Make sure 'Less secure app access' is enabled</strong> (if not using App Password)</li>";
        echo "</ol>";
    }
} else {
    echo "❌ Cannot test SMTP - missing configuration or PHPMailer<br>";
}

// Test sending a simple email
echo "<h3>📧 Test Email Send:</h3>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_email'])) {
    $test_email = trim($_POST['test_email']);
    
    if ($test_email && filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
        require_once 'classes/Mailer.php';
        
        $subject = "PointShift POS - Configuration Test";
        $body = "<h2>✅ Email Test Successful!</h2><p>Your PointShift POS email system is working correctly.</p><p>Time: " . date('Y-m-d H:i:s') . "</p>";
        
        $result = Mailer::sendEmail($test_email, $subject, $body, SITE_NAME, null);
        
        if ($result) {
            echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px;'>";
            echo "✅ <strong>Test email sent successfully!</strong><br>";
            echo "Check your inbox at: " . htmlspecialchars($test_email);
            echo "</div>";
        } else {
            echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px;'>";
            echo "❌ <strong>Test email failed!</strong><br>";
            echo "Check the logs/email.log file for details.";
            echo "</div>";
        }
    } else {
        echo "<div style='background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px;'>";
        echo "⚠️ Please provide a valid email address.";
        echo "</div>";
    }
}

echo "<form method='POST' style='background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<h4>Send Test Email:</h4>";
echo "<input type='email' name='test_email' placeholder='your-email@example.com' value='dummyacc45f@gmail.com' required style='padding: 8px; width: 300px; margin-right: 10px;'>";
echo "<button type='submit' style='padding: 8px 15px; background: #007bff; color: white; border: none; border-radius: 3px;'>Send Test</button>";
echo "</form>";

echo "<h3>📝 Next Steps:</h3>";
echo "<ol>";
echo "<li>Fix any configuration issues shown above</li>";
echo "<li>Test the email sending using the form above</li>";
echo "<li>If successful, try sending from Staff/Cashier email system</li>";
echo "<li>Check logs/email.log for detailed error messages</li>";
echo "</ol>";

echo "<hr>";
echo "<p><strong>Current Time:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>
