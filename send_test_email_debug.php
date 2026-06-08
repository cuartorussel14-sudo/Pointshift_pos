<?php
require_once __DIR__ . '/config.php';
requireLogin();
requireAdmin();

require_once __DIR__ . '/vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$page_title = 'Send Test Email (SMTP Debug)';
ob_start();
?>
<div class="container py-4">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Send Test Email (SMTP Debug)</h5></div>
                <div class="card-body">
                    <p class="small text-muted">This will attempt to send a test email and show SMTP debug output for troubleshooting.</p>

                    <?php
                    $debugHtml = '';
                    $statusHtml = '';

                    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        $to = trim($_POST['to'] ?? '');
                        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                            $statusHtml = '<div class="alert alert-danger">Please provide a valid recipient email address.</div>';
                        } else {
                            $debugLog = '';
                            try {
                                $mail = new PHPMailer(true);
                                $mail->isSMTP();
                                $mail->SMTPDebug = 3; // verbose
                                $mail->Debugoutput = function($str, $level) use (&$debugLog) {
                                    $debugLog .= htmlspecialchars($str) . "<br>";
                                };
                                $mail->Host = SMTP_HOST;
                                $mail->SMTPAuth = true;
                                $mail->Username = SMTP_USER;
                                $mail->Password = SMTP_PASS;
                                if (defined('SMTP_SECURE') && SMTP_SECURE) $mail->SMTPSecure = SMTP_SECURE;
                                if (defined('SMTP_PORT') && SMTP_PORT) $mail->Port = intval(SMTP_PORT);
                                $mail->setFrom(SMTP_USER, SITE_NAME . ' (Test Debug)');
                                $mail->addAddress($to);
                                $mail->Subject = 'SMTP Debug Test - ' . SITE_NAME;
                                $mail->isHTML(true);
                                $mail->Body = '<p>SMTP debug test from <strong>' . htmlspecialchars(SITE_NAME) . '</strong> at ' . date('Y-m-d H:i:s') . '.</p>';
                                $mail->AltBody = 'SMTP debug test from ' . SITE_NAME;
                                $sent = $mail->send();
                                $statusHtml = $sent ? '<div class="alert alert-success">Message sent (check debug log below).</div>' : '<div class="alert alert-warning">Message not sent (see debug log).</div>';
                            } catch (Exception $e) {
                                $statusHtml = '<div class="alert alert-danger">Send failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
                            }
                            $debugHtml = '<div class="border rounded p-2 small bg-light mt-3"><strong>SMTP debug output</strong><div class="mt-2">' . ($debugLog ?: 'No debug output captured') . '</div></div>';
                        }
                    }

                    echo $statusHtml;
                    ?>

                    <form method="post" class="mt-3">
                        <div class="mb-3">
                            <label for="to" class="form-label">Recipient email</label>
                            <input type="email" id="to" name="to" class="form-control" placeholder="you@example.com" required value="<?php echo htmlspecialchars($_POST['to'] ?? ''); ?>">
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-primary" type="submit">Send with debug</button>
                            <a href="send_test_email.php" class="btn btn-outline-secondary">Back</a>
                        </div>
                    </form>

                    <?php echo $debugHtml; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/views/layout.php';
?>
