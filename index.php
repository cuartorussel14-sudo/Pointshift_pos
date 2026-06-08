<?php
// staff/index.php - friendly folder entry point
// If someone opens /staff/ in the browser, send them to login or dashboard.
require_once __DIR__ . '/../config.php';

// If user is logged in redirect to staff dashboard, otherwise to login
if (isset($_SESSION['user_id'])) {
    header('Location: ' . rtrim(SITE_URL, '/') . '/staff/dashboard.php');
    exit();
} else {
    header('Location: ' . rtrim(SITE_URL, '/') . '/login.php');
    exit();
}
