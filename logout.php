<?php
require __DIR__ . '/config.php';
echo 'SITE_URL=' . SITE_URL . '<br>';
echo 'SCRIPT_NAME=' . ($_SERVER['SCRIPT_NAME'] ?? '') . '<br>';
echo 'REQUEST_URI=' . ($_SERVER['REQUEST_URI'] ?? '');

$authController = new AuthController();
$authController->logout();
?>
