<?php
// Set timezone for Philippines (Manila)
date_default_timezone_set('Asia/Manila');

// Ensure essential classes are available early (helps when pages include config.php directly)
require_once __DIR__ . '/classes/Database.php';

// Database configuration
// Use the local MySQL host — using the site domain caused the server to be
// treated as a remote host (IPv6) which led to an access-denied error. Use
// 127.0.0.1 to force IPv4 loopback and avoid name-resolution/IPv6 issues.
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'u337963325_itoti');
define('DB_USER', 'u337963325_itoti');
define('DB_PASS', 'Pointshift_pos1');

// Site configuration
// Live site URL. Prefer overriding with an environment variable in production.
if (!defined('SITE_URL')) {
    $envSiteUrl = getenv('SITE_URL');
    if (!empty($envSiteUrl)) {
        define('SITE_URL', rtrim($envSiteUrl, '/'));
    } else {
        // Fallback to configured production host. Change this value on deployment if needed.
        define('SITE_URL', 'https://pointshift.online');
    }
}

define('SITE_NAME', 'PointShift POS');

// Helper to build absolute URLs that respect SITE_URL
if (!function_exists('site_url')) {
    function site_url($path = '') {
        $base = rtrim(SITE_URL, '/');
        if ($path === '' || $path === '/') return $base;
        return $base . '/' . ltrim($path, '/');
    }
}

// API key for public product lookup (used by mobile scanners/kiosks). Keep this secret.
// Change this value to something random for your deployment.
if (!defined('PRODUCT_LOOKUP_API_KEY')) define('PRODUCT_LOOKUP_API_KEY', 'kO0W9OvLzMT/Z3/RHw8JErOFEDqytlOXg3BA4rFDHJE=');

// MySQL client paths (optional). On Windows/XAMPP these are commonly under C:\xampp\mysql\bin
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    if (!defined('MYSQLDUMP_PATH')) define('MYSQLDUMP_PATH', 'C:\\xampp\\mysql\\bin\\mysqldump.exe');
    if (!defined('MYSQL_PATH')) define('MYSQL_PATH', 'C:\\xampp\\mysql\\bin\\mysql.exe');
}

// Email configuration - Outlook SMTP (more reliable than Gmail)
define('EMAIL_DISABLED', false);

// Outlook SMTP settings
define('SMTP_HOST', 'smtp-mail.outlook.com');
define('SMTP_USER', 'aone79381@gmail.com');  // Replace with your Outlook email
define('SMTP_PASS', 'ufnm fryo odng ocpt');           // Replace with your Outlook password
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');

// Session configuration
session_start();

// Autoload classes
spl_autoload_register(function ($className) {
    $directories = [
        'classes/',
        'controllers/',
        'helpers/'
    ];
    
    foreach ($directories as $dir) {
        $file = __DIR__ . '/' . $dir . $className . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Helper functions for backward compatibility
function isLoggedIn() {
    return User::isLoggedIn();
}

function isAdmin() {
    return User::isAdmin();
}

function requireLogin() {
    User::requireLogin();
}

function requireAdmin() {
    User::requireAdmin();
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function formatCurrency($amount) {
    return Layout::formatCurrency($amount);
}

// Create MySQLi connection for backward compatibility
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4
$conn->set_charset("utf8mb4");


// Set MySQL timezone to match PHP timezone (Asia/Manila, UTC+8)
$conn->query("SET time_zone = '+08:00'");
// Update last activity timestamp for logged-in users on each request to help monitoring
if (isset($_SESSION['user_id'])) {
    try {
        $uid = intval($_SESSION['user_id']);
        $uStmt = $conn->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?");
        if ($uStmt) {
            $uStmt->bind_param('i', $uid);
            $uStmt->execute();
            // best-effort, ignore failures
            $uStmt->close();
        }
    } catch (Throwable $e) {
        // ignore update errors; we don't want to break every request if DB write fails
    }
}
?>
