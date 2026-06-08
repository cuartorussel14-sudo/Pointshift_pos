<?php
require_once 'Encryption.php';

class Sale {
    private $db;
    private $encryption;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->encryption = Encryption::getInstance();
    }
    
    public function createSale($data) {
        try {
            $this->db->beginTransaction();
            
            // Encrypt sensitive data
            $customerInfo = $this->encryption->encrypt(json_encode([
                'name' => $data['customer_name'] ?? '',
                'contact' => $data['customer_contact'] ?? '',
                'address' => $data['customer_address'] ?? ''
            ]));
            
            $paymentDetails = $this->encryption->encrypt(json_encode([
                'method' => $data['payment_method'],
                'reference' => $data['payment_reference'] ?? '',
                'card_last4' => $data['card_last4'] ?? ''
            ]));
            
            $stmt = $this->db->prepare("
                INSERT INTO orders (
                    user_id, total_amount, discount,
                    customer_info_encrypted, customer_info_iv, customer_info_tag,
                    payment_details_encrypted, payment_details_iv, payment_details_tag,
                    status
                ) VALUES (
                    ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ?,
                    ?
                )
            ");
            
            $result = $stmt->execute([
                $data['user_id'],
                $data['total_amount'],
                $data['discount'] ?? 0,
                $customerInfo['data'],
                $customerInfo['iv'],
                $customerInfo['tag'],
                $paymentDetails['data'],
                $paymentDetails['iv'],
                $paymentDetails['tag'],
                'completed'
            ]);
            
            if (!$result) {
                $this->db->rollBack();
                return false;
            }
            
            $saleId = $this->db->lastInsertId();
            
            // Insert sale items
            foreach ($data['items'] as $item) {
                $stmt = $this->db->prepare("
                    INSERT INTO order_items (
                        order_id, product_id, quantity, unit_price,
                        subtotal_encrypted, subtotal_iv, subtotal_tag
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)
                ");

                $subtotal = $this->encryption->encrypt((string)($item['quantity'] * $item['unit_price']));

                $result = $stmt->execute([
                    $saleId,
                    $item['product_id'],
                    $item['quantity'],
                    $item['unit_price'],
                    $subtotal['data'],
                    $subtotal['iv'],
                    $subtotal['tag']
                ]);

                if (!$result) {
                    $this->db->rollBack();
                    return false;
                }
            }
            
            $this->db->commit();
            return $saleId;
            
        } catch(PDOException $e) {
            $this->db->rollBack();
            error_log("Sale creation error: " . $e->getMessage());
            return false;
        }
    }
    
    public function getSale($id) {
        try {
            // Get sale header
            $stmt = $this->db->prepare("
                SELECT * FROM orders WHERE id = ?
            ");
            $stmt->execute([$id]);
            $sale = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$sale) {
                return false;
            }

            // Decrypt customer info
            if (!empty($sale['customer_info_encrypted'])) {
                $encryptedCustomerInfo = [
                    'data' => $sale['customer_info_encrypted'],
                    'iv' => $sale['customer_info_iv'],
                    'tag' => $sale['customer_info_tag']
                ];
                $sale['customer_info'] = json_decode(
                    $this->encryption->decrypt($encryptedCustomerInfo),
                    true
                );
            }

            // Decrypt payment details
            if (!empty($sale['payment_details_encrypted'])) {
                $encryptedPaymentDetails = [
                    'data' => $sale['payment_details_encrypted'],
                    'iv' => $sale['payment_details_iv'],
                    'tag' => $sale['payment_details_tag']
                ];
                $sale['payment_details'] = json_decode(
                    $this->encryption->decrypt($encryptedPaymentDetails),
                    true
                );
            }

            // Get and decrypt sale items
            $stmt = $this->db->prepare("
                SELECT * FROM order_items WHERE order_id = ?
            ");
            $stmt->execute([$id]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($items as &$item) {
                if (!empty($item['subtotal_encrypted'])) {
                    $encryptedSubtotal = [
                        'data' => $item['subtotal_encrypted'],
                        'iv' => $item['subtotal_iv'],
                        'tag' => $item['subtotal_tag']
                    ];
                    $item['subtotal'] = $this->encryption->decrypt($encryptedSubtotal);
                }
            }

            $sale['items'] = $items;
            return $sale;

        } catch(PDOException $e) {
            error_log("Sale retrieval error: " . $e->getMessage());
            return false;
        }
    }
    
    public function getSalesReport($startDate, $endDate) {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    s.id, s.created_at as sale_date, s.total_amount, s.discount,
                    s.customer_info_encrypted, s.customer_info_iv, s.customer_info_tag,
                    s.payment_details_encrypted, s.payment_details_iv, s.payment_details_tag,
                    u.username as cashier_name
                FROM orders s
                JOIN users u ON s.user_id = u.id
                WHERE s.created_at BETWEEN ? AND ?
                ORDER BY s.created_at DESC
            ");

            $stmt->execute([$startDate, $endDate]);
            $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($sales as &$sale) {
                // Decrypt customer info
                if (!empty($sale['customer_info_encrypted'])) {
                    $encryptedCustomerInfo = [
                        'data' => $sale['customer_info_encrypted'],
                        'iv' => $sale['customer_info_iv'],
                        'tag' => $sale['customer_info_tag']
                    ];
                    $sale['customer_info'] = json_decode(
                        $this->encryption->decrypt($encryptedCustomerInfo),
                        true
                    );
                }

                // Decrypt payment details
                if (!empty($sale['payment_details_encrypted'])) {
                    $encryptedPaymentDetails = [
                        'data' => $sale['payment_details_encrypted'],
                        'iv' => $sale['payment_details_iv'],
                        'tag' => $sale['payment_details_tag']
                    ];
                    $sale['payment_details'] = json_decode(
                        $this->encryption->decrypt($encryptedPaymentDetails),
                        true
                    );
                }
            }

            return $sales;

        } catch(PDOException $e) {
            error_log("Sales report error: " . $e->getMessage());
            return false;
        }
    }
}