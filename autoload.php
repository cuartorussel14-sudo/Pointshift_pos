<?php
// Minimal autoloader for PHPMailer installed manually
require_once __DIR__ . '/phpmailer/phpmailer/src/Exception.php';
require_once __DIR__ . '/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/phpmailer/phpmailer/src/SMTP.php';

// Register PHPMailer namespace
if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
    class_alias('PHPMailer', 'PHPMailer\\PHPMailer\\PHPMailer');
}
if (!class_exists('PHPMailer\\PHPMailer\\SMTP')) {
    class_alias('SMTP', 'PHPMailer\\PHPMailer\\SMTP');
}
if (!class_exists('PHPMailer\\PHPMailer\\Exception')) {
    class_alias('PHPMailerException', 'PHPMailer\\PHPMailer\\Exception');
}
