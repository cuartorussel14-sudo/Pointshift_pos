<?php
// Safe download script for backup files
session_start();
require_once __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    echo 'Access denied';
    exit();
}

$filename = $_GET['file'] ?? '';
if (empty($filename)) {
    header('HTTP/1.1 400 Bad Request');
    echo 'Missing file parameter';
    exit();
}

$backupDir = dirname(__DIR__) . '/backups/';
$filePath = realpath($backupDir . $filename);

// security checks
if ($filePath === false || strpos($filePath, realpath($backupDir)) !== 0) {
    header('HTTP/1.1 400 Bad Request');
    echo 'Invalid file';
    exit();
}

if (!file_exists($filePath)) {
    header('HTTP/1.1 404 Not Found');
    echo 'File not found';
    exit();
}

// send appropriate headers
$basename = basename($filePath);
$mime = 'application/octet-stream';
header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $basename . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filePath));

readfile($filePath);
exit();
