<?php
require_once '../config.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON data');
    }
    
    $db = Database::getInstance()->getConnection();
    $db->beginTransaction();
    
    // Create order
    $stmt = $db->prepare("
        INSERT INTO orders (order_number, user_id, total_amount, tax_amount, status, created_at) 
        VALUES (?, ?, ?, ?, 'completed', NOW())
    ");
    
    $orderNumber = 'POS' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    $totalAmount = $input['total'];
    $taxAmount = $input['tax'];
    $userId = $_SESSION['user_id'];
    
    $stmt->execute([$orderNumber, $userId, $totalAmount, $taxAmount]);
    $orderId = $db->lastInsertId();
    
    // Add order items
    $stmt = $db->prepare("
        INSERT INTO order_items (order_id, product_id, quantity, unit_price, total_price) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    foreach ($input['items'] as $item) {
        $totalPrice = $item['price'] * $item['quantity'];
        $stmt->execute([
            $orderId,
            $item['id'],
            $item['quantity'],
            $item['price'],
            $totalPrice
        ]);
        
        // Update product stock
        $updateStock = $db->prepare("
            UPDATE products 
            SET stock_quantity = stock_quantity - ? 
            WHERE id = ?
        ");
        $updateStock->execute([$item['quantity'], $item['id']]);
        // After update, check current stock and insert notification if needed
        try {
            require_once __DIR__ . '/../classes/NotificationManager.php';
            $check = $db->prepare("SELECT stock_quantity, low_stock_threshold FROM products WHERE id = ?");
            $check->execute([$item['id']]);
            $prod = $check->fetch(PDO::FETCH_ASSOC);
            if ($prod) {
                $notificationManager = NotificationManager::getInstance($db);
                $notificationManager->createStockNotification(
                    $item['id'],
                    intval($prod['stock_quantity']),
                    intval($prod['low_stock_threshold'])
                );
            }
        } catch (Exception $e) {
            // ignore notification failures
        }
    }
    
    $db->commit();
    
    $notifications = [];
    // Create a transaction success notification
    try {
        require_once __DIR__ . '/../classes/NotificationManager.php';
        $notificationManager = NotificationManager::getInstance($db);
        $notificationNotif = $notificationManager->createTransactionNotification(
            $orderNumber,
            $totalAmount,
            $input['paymentMethod'] ?? 'N/A',
            'success'
        );
        if ($notificationNotif) {
            $notifications[] = $notificationNotif;
        }
    } catch (Exception $e) {
        // Log error but continue
        error_log("Error creating transaction notification: " . $e->getMessage());
    }

    echo json_encode([
        'success' => true,
        'order_id' => $orderId,
        'message' => 'Order processed successfully',
        'notifications' => $notifications
    ]);
    
} catch (Exception $e) {
    $db->rollback();
    http_response_code(400);
    // transaction failure notification
    try {
        require_once __DIR__ . '/../classes/Notification.php';
        Notification::create($db, "Sales Transaction Failed: " . $e->getMessage(), 'transaction', null);
    } catch (Exception $inner) {
        // ignore
    }

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
