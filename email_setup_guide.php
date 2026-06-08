<?php
/**
 * Email Setup Guide
 */

require_once 'config.php';

echo "<h2>📧 Email Setup Guide</h2>";

echo "<h3>🔧 How to Set Up Working Email:</h3>";

echo "<div style='background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<h4>Option 1: Outlook/Hotmail (Recommended)</h4>";
echo "<ol>";
echo "<li><strong>Create Outlook account:</strong> Go to <a href='https://outlook.com' target='_blank'>outlook.com</a></li>";
echo "<li><strong>Update config.php:</strong><br>";
echo "   <code>define('SMTP_USER', 'your-email@outlook.com');</code><br>";
echo "   <code>define('SMTP_PASS', 'your-password');</code></li>";
echo "<li><strong>Test:</strong> Use the test form below</li>";
echo "</ol>";
echo "</div>";

echo "<div style='background: #f0f8ff; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<h4>Option 2: Gmail (Alternative)</h4>";
echo "<ol>";
echo "<li><strong>Enable 2-Factor Authentication</strong> on Gmail</li>";
echo "<li><strong>Generate App Password:</strong> Google Account → Security → App passwords</li>";
echo "<li><strong>Update config.php:</strong><br>";
echo "   <code>define('SMTP_HOST', 'smtp.gmail.com');</code><br>";
echo "   <code>define('SMTP_USER', 'your-email@gmail.com');</code><br>";
echo "   <code>define('SMTP_PASS', 'your-app-password');</code></li>";
echo "</ol>";
echo "</div>";

echo "<div style='background: #f0fff0; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<h4>Option 3: SendGrid (Professional)</h4>";
echo "<ol>";
echo "<li><strong>Sign up:</strong> <a href='https://sendgrid.com/free/' target='_blank'>sendgrid.com/free/</a></li>";
echo "<li><strong>Get API Key:</strong> Dashboard → Settings → API Keys</li>";
echo "<li><strong>Update config.php:</strong><br>";
echo "   <code>define('SMTP_HOST', 'smtp.sendgrid.net');</code><br>";
echo "   <code>define('SMTP_USER', 'apikey');</code><br>";
echo "   <code>define('SMTP_PASS', 'your-sendgrid-api-key');</code></li>";
echo "</ol>";
echo "</div>";

echo "<h3>📋 Current Configuration:</h3>";
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Setting</th><th>Value</th><th>Status</th></tr>";

$email_disabled = defined('EMAIL_DISABLED') && EMAIL_DISABLED;
$status = $email_disabled ? '🔴 Disabled' : '🟢 Enabled';

echo "<tr><td>EMAIL_DISABLED</td><td>" . ($email_disabled ? 'true' : 'false') . "</td><td>{$status}</td></tr>";
echo "<tr><td>SMTP_HOST</td><td>" . (defined('SMTP_HOST') ? SMTP_HOST : 'Not set') . "</td><td>-</td></tr>";
echo "<tr><td>SMTP_USER</td><td>" . (defined('SMTP_USER') ? SMTP_USER : 'Not set') . "</td><td>-</td></tr>";
echo "<tr><td>SMTP_PASS</td><td>" . (defined('SMTP_PASS') ? 'Set (' . strlen(SMTP_PASS) . ' chars)' : 'Not set') . "</td><td>-</td></tr>";
echo "</table>";

echo "<h3>🧪 Test Your Email Setup:</h3>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_email'])) {
    $test_email = trim($_POST['test_email']);
    
    if ($test_email && filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
        require_once 'classes/Mailer.php';
        
        echo "<strong>Sending test email to:</strong> " . htmlspecialchars($test_email) . "<br>";
        
        $subject = "PointShift POS - Email Test";
        $body = "<h2>✅ Email Test Successful!</h2>";
        $body .= "<p>Your PointShift POS email system is working correctly.</p>";
        $body .= "<p><strong>Test Time:</strong> " . date('Y-m-d H:i:s') . "</p>";
        $body .= "<p><strong>From:</strong> " . SITE_NAME . "</p>";
        
        $result = Mailer::sendEmail($test_email, $subject, $body, SITE_NAME, null);
        
        if ($result) {
            echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
            echo "✅ <strong>Email sent successfully!</strong><br>";
            echo "Check your inbox at: " . htmlspecialchars($test_email);
            echo "</div>";
        } else {
            echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
            echo "❌ <strong>Email failed!</strong><br>";
            echo "Check logs/email.log for details.";
            echo "</div>";
        }
    } else {
        echo "<div style='background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "⚠️ Please provide a valid email address.";
        echo "</div>";
    }
}

echo "<form method='POST' style='background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<h4>Send Test Email:</h4>";
echo "<input type='email' name='test_email' placeholder='your-email@example.com' value='test@example.com' required style='padding: 8px; width: 300px; margin-right: 10px;'>";
echo "<button type='submit' style='padding: 8px 15px; background: #007bff; color: white; border: none; border-radius: 3px;'>Send Test</button>";
echo "</form>";

echo "<h3>📝 Next Steps:</h3>";
echo "<ol>";
echo "<li><strong>Choose an email provider</strong> from the options above</li>";
echo "<li><strong>Update config.php</strong> with your email credentials</li>";
echo "<li><strong>Test using this page</strong> to verify it works</li>";
echo "<li><strong>Try the Staff/Cashier email system</strong> to send real messages</li>";
echo "</ol>";

echo "<hr>";
echo "<p><strong>Current Time:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>
