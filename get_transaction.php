<?php
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Missing transaction id']);
    exit;
}

// If not logged in, return JSON (avoid redirect when called via AJAX)
if (!User::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$db = Database::getInstance()->getConnection();

// Get transaction
$stmt = $db->prepare("SELECT o.*, u.first_name, u.last_name, u.username FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.id = ? LIMIT 1");
$stmt->execute([$id]);
$transaction = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$transaction) {
    echo json_encode(['success' => false, 'message' => 'Transaction not found']);
    exit;
}

// Authorization: cashiers can only view their own transactions
if (isset($_SESSION['role']) && $_SESSION['role'] === 'cashier') {
    // Note: session user id is stored in 'user_id'
    if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != $transaction['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Not authorized to view this transaction']);
        exit;
    }
}

// Get items
$stmt = $db->prepare("SELECT oi.*, p.name FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
$stmt->execute([$id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'transaction' => $transaction, 'items' => $items]);
exit;
