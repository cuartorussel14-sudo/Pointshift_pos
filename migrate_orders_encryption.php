<?php
require_once 'config.php';
require_once 'classes/Encryption.php';

echo "Starting orders encryption migration...\n";

try {
    $encryption = Encryption::getInstance();

    // Migrate orders table
    echo "Migrating orders table...\n";
    $stmt = $conn->query("SELECT id, user_id, total_amount, payment_method FROM orders WHERE customer_info_encrypted IS NULL");

    while ($order = $stmt->fetch_assoc()) {
        // Encrypt customer info (placeholder data since original orders don't have customer info)
        $customerInfo = $encryption->encrypt(json_encode([
            'name' => '',
            'contact' => '',
            'address' => ''
        ]));

        // Encrypt payment details
        $paymentDetails = $encryption->encrypt(json_encode([
            'method' => $order['payment_method'],
            'reference' => '',
            'card_last4' => ''
        ]));

        $updateStmt = $conn->prepare("
            UPDATE orders SET
                customer_info_encrypted = ?,
                customer_info_iv = ?,
                customer_info_tag = ?,
                payment_details_encrypted = ?,
                payment_details_iv = ?,
                payment_details_tag = ?
            WHERE id = ?
        ");

        $updateStmt->bind_param("ssssssi",
            $customerInfo['data'], $customerInfo['iv'], $customerInfo['tag'],
            $paymentDetails['data'], $paymentDetails['iv'], $paymentDetails['tag'],
            $order['id']
        );

        if (!$updateStmt->execute()) {
            echo "Error updating order {$order['id']}: " . $updateStmt->error . "\n";
        }
    }

    // Migrate order_items table
    echo "Migrating order_items table...\n";
    $stmt = $conn->query("SELECT id, quantity, unit_price FROM order_items WHERE subtotal_encrypted IS NULL");

    while ($item = $stmt->fetch_assoc()) {
        $subtotal = $encryption->encrypt((string)($item['quantity'] * $item['unit_price']));

        $updateStmt = $conn->prepare("
            UPDATE order_items SET
                subtotal_encrypted = ?,
                subtotal_iv = ?,
                subtotal_tag = ?
            WHERE id = ?
        ");

        $updateStmt->bind_param("sssi",
            $subtotal['data'], $subtotal['iv'], $subtotal['tag'],
            $item['id']
        );

        if (!$updateStmt->execute()) {
            echo "Error updating order item {$item['id']}: " . $updateStmt->error . "\n";
        }
    }

    echo "Migration completed successfully!\n";

} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>
