<?php
// Diagnostics endpoint to show what the server returns for unread notifications
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/NotificationManager.php';
header('Content-Type: application/json');

try {
    // Ensure user session is present (use same browser session as app)
    $db = Database::getInstance()->getConnection();
    $nm = NotificationManager::getInstance($db);
    $user_id = $_SESSION['user_id'] ?? null;

    $unread = $nm->fetchUnread(50, $user_id);
    $recent = $nm->fetchRecent(50, $user_id);
    $count = $nm->getUnreadCount($user_id);

    echo json_encode([
        'success' => true,
        'session_user_id' => $user_id,
        'unreadCount' => $count,
        'unread' => $unread,
        'recent' => $recent,
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
