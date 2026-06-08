<?php
// Sample: Add a system-wide notification
require_once __DIR__ . '/../classes/Database.php';
$db = Database::getInstance()->getConnection();
$sql = "INSERT INTO system_notifications (message, type, status, created_at, expires_at) VALUES (?, ?, 'active', NOW(), ?)";
$stmt = $db->prepare($sql);
$message = 'Scheduled maintenance on Oct 25, 2025, 2:00AM-4:00AM. Expect downtime.';
$type = 'warning'; // info, success, error, warning
$expires_at = '2025-10-25 04:00:00';
$stmt->execute([$message, $type, $expires_at]);
echo "System notification added.";
