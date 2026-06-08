<?php
/**
 * Email Status Test
 */

require_once 'config.php';
require_once 'classes/Mailer.php';

echo "<h2>📧 Email System Status</h2>";

echo "<h3>Current Configuration:</h3>";
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Setting</th><th>Value</th><th>Status</th></tr>";

$email_disabled = defined('EMAIL_DISABLED') && EMAIL_DISABLED;
$status = $email_disabled ? '🔴 Disabled' : '🟢 Enabled';

echo "<tr><td>EMAIL_DISABLED</td><td>" . ($email_disabled ? 'true' : 'false') . "</td><td>{$status}</td></tr>";
echo "<tr><td>SMTP_HOST</td><td>" . (defined('SMTP_HOST') ? SMTP_HOST : 'Not set') . "</td><td>-</td></tr>";
echo "<tr><td>SMTP_USER</td><td>" . (defined('SMTP_USER') ? SMTP_USER : 'Not set') . "</td><td>-</td></tr>";
echo "</table>";

echo "<h3>Email Test:</h3>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_email'])) {
    $test_email = trim($_POST['test_email']);
    
    if ($test_email && filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
        echo "<strong>Sending test email to:</strong> " . htmlspecialchars($test_email) . "<br>";
        
        $subject = "PointShift POS - Status Test";
        $body = "<h2>Email System Test</h2>";
        $body .= "<p>This is a test of the PointShift POS email system.</p>";
        $body .= "<p><strong>Test Time:</strong> " . date('Y-m-d H:i:s') . "</p>";
        $body .= "<p><strong>Email Status:</strong> " . ($email_disabled ? 'Disabled' : 'Enabled') . "</p>";
        
        $result = Mailer::sendEmail($test_email, $subject, $body, SITE_NAME, null);
        
        if ($result) {
            if ($email_disabled) {
                echo "<div style='background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
                echo "✅ <strong>Email system is disabled - marked as sent!</strong><br>";
                echo "The app works without sending actual emails.";
                echo "</div>";
            } else {
                echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
                echo "✅ <strong>Email sent successfully!</strong><br>";
                echo "Check your inbox at: " . htmlspecialchars($test_email);
                echo "</div>";
            }
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

echo "<h3>📝 How to Enable Email Later:</h3>";
echo "<ol>";
echo "<li><strong>Set up email provider:</strong> Gmail, Outlook, SendGrid, etc.</li>";
echo "<li><strong>Update config.php:</strong> Set EMAIL_DISABLED to false</li>";
echo "<li><strong>Configure SMTP:</strong> Add your email provider settings</li>";
echo "<li><strong>Test:</strong> Use this page to verify it works</li>";
echo "</ol>";

echo "<hr>";
echo "<p><strong>Current Time:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>
