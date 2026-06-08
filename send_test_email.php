<?php
require_once __DIR__ . '/config.php';
requireLogin();
requireAdmin();

// Load PHPMailer classes (vendor autoload is a small local autoloader)
require_once __DIR__ . '/vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$page_title = 'Send Test Email';
ob_start();
?>
<div class="container py-4">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Send Test Email</h5>
                </div>
                <div class="card-body">
                    <p class="small text-muted">Use this form to send a test email using the configured SMTP settings.</p>

                    <?php
                    $statusHtml = '';
                    if (defined('EMAIL_DISABLED') && EMAIL_DISABLED) {
                        $statusHtml = '<div class="alert alert-warning">Email sending is currently disabled (EMAIL_DISABLED = true).</div>';
                    }

                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($statusHtml)) {
                        $to = trim($_POST['to'] ?? '');
                        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                            $statusHtml = '<div class="alert alert-danger">Please provide a valid recipient email address.</div>';
                        } else {
                            // Send using PHPMailer
                            $success = false;
                            try {
                                $mail = new PHPMailer(true);
                                $mail->isSMTP();
                                $mail->Host = SMTP_HOST;
                                $mail->SMTPAuth = true;
                                $mail->Username = SMTP_USER;
                                $mail->Password = SMTP_PASS;
                                if (defined('SMTP_SECURE') && SMTP_SECURE) $mail->SMTPSecure = SMTP_SECURE;
                                if (defined('SMTP_PORT') && SMTP_PORT) $mail->Port = intval(SMTP_PORT);
                                $mail->setFrom(SMTP_USER, SITE_NAME . ' (Test)');
                                $mail->addAddress($to);
                                $mail->Subject = 'Test email from ' . SITE_NAME;
                                $mail->isHTML(true);
                                $mail->Body = '<p>This is a test email sent from <strong>' . htmlspecialchars(SITE_NAME) . '</strong> at ' . date('Y-m-d H:i:s') . '.</p>';
                                $mail->AltBody = 'This is a test email from ' . SITE_NAME . ' - ' . date('Y-m-d H:i:s');
                                $mail->send();
                                $success = true;
                            } catch (Exception $e) {
                                $err = htmlspecialchars($e->getMessage());
                                // include PHPMailer ErrorInfo if available
                                if (isset($mail) && !empty($mail->ErrorInfo)) {
                                    $err .= ' | ' . htmlspecialchars($mail->ErrorInfo);
                                }
                                $statusHtml = '<div class="alert alert-danger">Send failed: ' . $err . '</div>';
                            }
                            if ($success) {
                                $statusHtml = '<div class="alert alert-success">Test email sent successfully to ' . htmlspecialchars($to) . '.</div>';
                            }
                        }
                    }

                    echo $statusHtml;
                    ?>

                    <?php
                    // Quick sanity hint for common misconfiguration
                    $hint = '';
                    if (stripos(SMTP_HOST, 'outlook') !== false && stripos(SMTP_USER ?? '', '@gmail.com') !== false) {
                        $hint = '<div class="alert alert-warning mt-3">SMTP host looks like Outlook (smtp-mail.outlook.com) but SMTP_USER is a Gmail address. Update <code>SMTP_USER</code> to your Outlook account or use the correct SMTP host for Gmail (smtp.gmail.com).</div>';
                    }
                    echo $hint;
                    ?>

                    <form method="post" class="mt-3">
                        <div class="mb-3">
                            <label for="to" class="form-label">Recipient email</label>
                            <input type="email" id="to" name="to" class="form-control" placeholder="you@example.com" required value="<?php echo htmlspecialchars($_POST['to'] ?? ''); ?>">
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-primary" type="submit">Send test email</button>
                            <a href="send_test_email_debug.php" class="btn btn-outline-secondary">Send with SMTP debug</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/views/layout.php';
?>
