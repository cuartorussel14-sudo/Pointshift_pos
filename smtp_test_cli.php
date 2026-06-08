<?php
// CLI SMTP tester — run from project root: php tools/smtp_test_cli.php recipient@example.com
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$to = $argv[1] ?? SMTP_USER ?? null;
if (!$to) {
    echo "Usage: php tools/smtp_test_cli.php recipient@example.com\n";
    exit(2);
}

echo "Using recipient: $to\n";

$mail = new PHPMailer(true);
$debugLog = '';
try {
    $mail->isSMTP();
    $mail->SMTPDebug = 3; // show connection/auth debug
    $mail->Debugoutput = function($str, $level) use (&$debugLog) { $debugLog .= trim($str) . "\n"; };
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USER;
    $mail->Password = SMTP_PASS;
    if (defined('SMTP_SECURE') && SMTP_SECURE) $mail->SMTPSecure = SMTP_SECURE;
    if (defined('SMTP_PORT') && SMTP_PORT) $mail->Port = intval(SMTP_PORT);
    $mail->setFrom(SMTP_USER, SITE_NAME . ' CLI test');
    $mail->addAddress($to);
    $mail->Subject = 'CLI SMTP test from ' . SITE_NAME;
    $mail->Body = 'This is a CLI SMTP test at ' . date('Y-m-d H:i:s');
    echo "Attempting to send...\n";
    $sent = $mail->send();
    echo $sent ? "Message sent\n" : "Message not sent\n";
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}

echo "--- SMTP debug output ---\n";
echo $debugLog ?: "(no debug output)\n";

if (!empty($mail->ErrorInfo)) echo "PHPMailer ErrorInfo: " . $mail->ErrorInfo . "\n";
