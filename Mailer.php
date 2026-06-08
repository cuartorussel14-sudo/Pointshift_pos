<?php
// Mailer wrapper: prefers PHPMailer + SMTP (Gmail) when configured, otherwise falls back to PHP mail()
class Mailer {
    // Send a simple HTML email. Returns true on success, false on failure.
    // If email is disabled, returns true (success) without sending
    public static function sendEmail($toEmail, $subject, $bodyHtml, $fromName = null, $fromEmail = null) {
        // Check if email is disabled
        if (defined('EMAIL_DISABLED') && EMAIL_DISABLED) {
            self::logEmailAttempt($toEmail, $subject, $bodyHtml, true, 'Email disabled - marked as sent');
            return true;
        }
        // Determine defaults
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        // Use a valid email format for localhost
        $fromEmailDefault = $fromEmail ?? ('noreply@' . str_replace('localhost', 'pointshift.local', $host));
        $fromNameDefault = $fromName ?? ($fromEmail ? '' : 'Mail');

        // Attempt to autoload PHPMailer (Composer) if present
        $autoload = __DIR__ . '/../vendor/autoload.php';
        if (file_exists($autoload)) {
            @require_once $autoload;
        }

        // If PHPMailer is available, prefer it so we can use SMTP
        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer') || class_exists('PHPMailer')) {
            try {
                // Try new namespace first, fall back to old class name
                if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                } else {
                    $mail = new PHPMailer(true);
                }

                // Read SMTP settings: prefer database-configured settings (store_settings) if available,
                // otherwise fall back to constants from config.php
                $smtpHost = 'localhost';
                $smtpUser = '';
                $smtpPass = '';
                $smtpPort = 25;
                $smtpSecure = '';

                // If a DB connection exists, try to read SMTP settings from store_settings
                if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
                    try {
                        $keys = ['smtp_host','smtp_user','smtp_pass','smtp_port','smtp_secure'];
                        $in = implode(',', array_fill(0, count($keys), '?'));
                        $types = str_repeat('s', count($keys));
                        $stmt = $GLOBALS['conn']->prepare("SELECT setting_key, setting_value FROM store_settings WHERE setting_key IN ('" . implode("','", $keys) . "')");
                        if ($stmt) {
                            $stmt->execute();
                            $res = $stmt->get_result();
                            while ($row = $res->fetch_assoc()) {
                                switch ($row['setting_key']) {
                                    case 'smtp_host': $smtpHost = $row['setting_value']; break;
                                    case 'smtp_user': $smtpUser = $row['setting_value']; break;
                                    case 'smtp_pass': $smtpPass = $row['setting_value']; break;
                                    case 'smtp_port': $smtpPort = (int)$row['setting_value']; break;
                                    case 'smtp_secure': $smtpSecure = $row['setting_value']; break;
                                }
                            }
                        }
                    } catch (Throwable $e) {
                        // ignore and fall back to config constants
                    }
                }

                // Fallback to config constants if values not set from DB
                if (empty($smtpHost) || $smtpHost === 'localhost') {
                    if (defined('SMTP_HOST')) $smtpHost = SMTP_HOST;
                }
                if (empty($smtpUser) && defined('SMTP_USER')) $smtpUser = SMTP_USER;
                if (empty($smtpPass) && defined('SMTP_PASS')) $smtpPass = SMTP_PASS;
                if (empty($smtpPort) && defined('SMTP_PORT')) $smtpPort = SMTP_PORT;
                if (empty($smtpSecure) && defined('SMTP_SECURE')) $smtpSecure = SMTP_SECURE;

                if ($smtpHost && $smtpHost !== 'localhost') {
                    // Use SMTP server
                    $mail->isSMTP();
                    $mail->Host = $smtpHost;
                    $mail->Port = (int)$smtpPort;
                    
                    if ($smtpUser && $smtpPass) {
                        $mail->SMTPAuth = true;
                        $mail->Username = $smtpUser;
                        $mail->Password = $smtpPass;
                    }
                    
                    if ($smtpSecure) {
                        $mail->SMTPSecure = $smtpSecure;
                    }
                    
                    $mailFrom = $smtpUser ?: $fromEmailDefault;
                } else {
                    // Use local mail server or PHP mail()
                    $mail->isMail();
                    $mailFrom = $fromEmailDefault;
                }

                // Use the computed mailFrom as the envelope From. Validate and normalize addresses so
                // PHPMailer doesn't reject them (e.g., no-reply@localhost is invalid for many SMTP servers).
                $displayName = $fromNameDefault ?: (defined('SITE_NAME') ? SITE_NAME : $host);

                // Normalize candidate From address
                $mailFromCandidate = $mailFrom;
                if (!filter_var($mailFromCandidate, FILTER_VALIDATE_EMAIL)) {
                    // Prefer smtp user if it's a valid email
                    if (!empty($smtpUser) && filter_var($smtpUser, FILTER_VALIDATE_EMAIL)) {
                        $mailFromCandidate = $smtpUser;
                    } else {
                        // Derive a safe domain from SITE_URL or fallback to example.com
                        $fallbackDomain = 'example.com';
                        if (defined('SITE_URL')) {
                            $hostFromUrl = parse_url(SITE_URL, PHP_URL_HOST);
                            if ($hostFromUrl) {
                                // remove port if present
                                $hostFromUrl = preg_replace('/:\d+$/', '', $hostFromUrl);
                                $fallbackDomain = $hostFromUrl;
                            }
                        }
                        $mailFromCandidate = 'noreply@' . $fallbackDomain;
                    }
                }

                // Final validation: if still invalid, use a safe fallback
                if (!filter_var($mailFromCandidate, FILTER_VALIDATE_EMAIL)) {
                    $mailFromCandidate = 'noreply@example.com';
                }

                $mail->setFrom($mailFromCandidate, $displayName);
                // Only add Reply-To if the desired fromEmailDefault is a valid email and differs
                if ($mailFromCandidate !== $fromEmailDefault && filter_var($fromEmailDefault, FILTER_VALIDATE_EMAIL)) {
                    $mail->addReplyTo($fromEmailDefault, $displayName);
                }
                $mail->addAddress($toEmail);
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body = $bodyHtml;
                $mail->AltBody = strip_tags($bodyHtml);

                return (bool)$mail->send();
            } catch (Exception $e) {
                // Log PHPMailer exception and fall through to fallback
                self::logEmailAttempt($toEmail, $subject, $bodyHtml, false, "PHPMailer exception: " . $e->getMessage());
            }
        }

        // Fallback: use PHP's mail() function
        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type: text/html; charset=utf-8';
        $headers[] = 'From: ' . ($fromNameDefault ? "$fromNameDefault <$fromEmailDefault>" : $fromEmailDefault);
        $headers[] = 'Reply-To: ' . $fromEmailDefault;

        $headerString = implode("\r\n", $headers);
        $success = @mail($toEmail, $subject, $bodyHtml, $headerString);

        if (!$success) {
            self::logEmailAttempt($toEmail, $subject, $bodyHtml, false, 'mail() failed');
        } else {
            self::logEmailAttempt($toEmail, $subject, $bodyHtml, true);
        }

        return (bool)$success;
    }

    private static function logEmailAttempt($toEmail, $subject, $bodyHtml, $success, $note = '') {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $logFile = $logDir . '/email.log';
        $entry = sprintf("[%s] to=%s subject=%s success=%s note=%s\nBody:%s\n\n",
            date('Y-m-d H:i:s'), $toEmail, $subject, $success ? '1' : '0', $note, $bodyHtml
        );
        @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }
}
