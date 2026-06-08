<?php

class NotificationManager
{
    /**
     * Fetch active system-wide notifications
     */
    public function fetchSystemNotifications()
    {
        $stmt = $this->db->prepare("
            SELECT * FROM system_notifications
            WHERE status = 'active'
            AND (expires_at IS NULL OR expires_at > NOW())
            ORDER BY created_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    private static $instance = null;
    private $db;
    private $validTypes = [
        'info',
        'success',
        'error',
        'warning',
        'low_stock',
        'out_of_stock',
        'transaction',
        'expiry'
    ];

    private function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public static function getInstance(PDO $db)
    {
        if (self::$instance === null) {
            self::$instance = new self($db);
        }
        return self::$instance;
    }

    /**
     * Create a new notification
     */
    public function create($message, $type = 'info', $product_id = null, $user_id = null)
    {
        if (!in_array($type, $this->validTypes)) {
            $type = 'info';
        }

        try {
            // For expiry notifications, always include the product's expiry date
            $expiry_date = null;
            if ($type === 'expiry' && $product_id) {
                $pStmt = $this->db->prepare("SELECT expiry FROM products WHERE id = ?");
                $pStmt->execute([$product_id]);
                $expiry_date = $pStmt->fetchColumn();
            }
            // Try to insert including expiry_date (newer schema). If the column doesn't exist,
            // fall back to inserting without expiry_date for compatibility.
            try {
                $stmt = $this->db->prepare("
                    INSERT INTO notifications 
                    (message, type, product_id, user_id, status, created_at, expiry_date) 
                    VALUES (?, ?, ?, ?, 'unread', NOW(), ?)
                ");
                $result = $stmt->execute([$message, $type, $product_id, $user_id, $expiry_date]);
            } catch (PDOException $e) {
                error_log('NotificationManager::create insert with expiry_date failed: ' . $e->getMessage());
                $stmt2 = $this->db->prepare("
                    INSERT INTO notifications 
                    (message, type, product_id, user_id, status, created_at) 
                    VALUES (?, ?, ?, ?, 'unread', NOW())
                ");
                $result = $stmt2->execute([$message, $type, $product_id, $user_id]);
            }

            if ($result) {
                $id = $this->db->lastInsertId();
                try {
                    return $this->getNotificationById($id);
                } catch (PDOException $_e) {
                    error_log('NotificationManager::create fetch after insert failed: ' . $_e->getMessage());
                    // Fallback fetch without expiry_date column
                    $stmt3 = $this->db->prepare("SELECT n.*, p.name as product_name, p.expiry as product_expiry FROM notifications n LEFT JOIN products p ON n.product_id = p.id WHERE n.id = ?");
                    $stmt3->execute([$id]);
                    $row = $stmt3->fetch(PDO::FETCH_ASSOC);
                    if ($row) {
                        $row['expiry_date'] = ($row['type'] === 'expiry') ? $row['product_expiry'] : null;
                    }
                    return $row;
                }
            }
            return false;
        } catch (PDOException $e) {
            error_log("Error creating notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get a notification by ID
     */
    public function getNotificationById($id)
    {
        $stmt = $this->db->prepare("
            SELECT 
                n.*,
                p.name as product_name,
                p.stock_quantity,
                p.low_stock_threshold,
                p.expiry as product_expiry,
                COALESCE(n.expiry_date, 
                    CASE WHEN n.type = 'expiry' THEN p.expiry ELSE NULL END
                ) as expiry_date
            FROM notifications n
            LEFT JOIN products p ON n.product_id = p.id
            WHERE n.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Fetch recent notifications
     */
    public function fetchRecent($limit = 10, $user_id = null)
    {
        $sql = "
            SELECT 
                n.*,
                p.name as product_name,
                p.stock_quantity,
                p.low_stock_threshold,
                COALESCE(n.expiry_date, 
                    CASE WHEN n.type = 'expiry' THEN p.expiry ELSE NULL END
                ) as expiry_date
            FROM notifications n
            LEFT JOIN products p ON n.product_id = p.id
            WHERE 1=1
        ";

        $params = [];
        if ($user_id !== null) {
            $sql .= " AND (n.user_id IS NULL OR n.user_id = ?)";
            $params[] = $user_id;
        }

        $sql .= " ORDER BY n.created_at DESC LIMIT " . (int)$limit;

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Fallback for older schemas without expiry_date
            error_log('fetchRecent fallback due to: ' . $e->getMessage());
            $fallback = "
                SELECT n.*, p.name as product_name, p.stock_quantity, p.low_stock_threshold, p.expiry as product_expiry
                FROM notifications n
                LEFT JOIN products p ON n.product_id = p.id
                WHERE 1=1
            ";
            if ($user_id !== null) {
                $fallback .= " AND (n.user_id IS NULL OR n.user_id = ?)";
            }
            $fallback .= " ORDER BY n.created_at DESC LIMIT " . (int)$limit;
            $stmt2 = $this->db->prepare($fallback);
            $stmt2->execute($params);
            $rows = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$r) {
                $r['expiry_date'] = ($r['type'] ?? '') === 'expiry' ? ($r['product_expiry'] ?? null) : null;
            }
            return $rows;
        }
    }

    /**
     * Fetch unread notifications
     */
    public function fetchUnread($limit = 10, $user_id = null)
    {
        $sql = "
            SELECT 
                n.*,
                p.name as product_name,
                p.stock_quantity,
                p.low_stock_threshold,
                COALESCE(n.expiry_date, 
                    CASE WHEN n.type = 'expiry' THEN p.expiry ELSE NULL END
                ) as expiry_date
            FROM notifications n
            LEFT JOIN products p ON n.product_id = p.id
            WHERE n.status = 'unread'
        ";

        $params = [];
        if ($user_id !== null) {
            $sql .= " AND (n.user_id IS NULL OR n.user_id = ?)";
            $params[] = $user_id;
        }

        $sql .= " ORDER BY n.created_at DESC LIMIT " . (int)$limit;

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Fallback for older schemas without expiry_date
            error_log('fetchUnread fallback due to: ' . $e->getMessage());
            $fallback = "
                SELECT n.*, p.name as product_name, p.stock_quantity, p.low_stock_threshold, p.expiry as product_expiry
                FROM notifications n
                LEFT JOIN products p ON n.product_id = p.id
                WHERE n.status = 'unread'
            ";
            if ($user_id !== null) {
                $fallback .= " AND (n.user_id IS NULL OR n.user_id = ?)";
            }
            $fallback .= " ORDER BY n.created_at DESC LIMIT " . (int)$limit;
            $stmt2 = $this->db->prepare($fallback);
            $stmt2->execute($params);
            $rows = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$r) {
                $r['expiry_date'] = ($r['type'] ?? '') === 'expiry' ? ($r['product_expiry'] ?? null) : null;
            }
            return $rows;
        }
    }

    /**
     * Mark a notification as read
     */
    public function markRead($id)
    {
        $stmt = $this->db->prepare("UPDATE notifications SET status = 'read' WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Mark all notifications as read
     */
    /**
     * Mark all notifications as read.
     *
     * @param int|null $user_id If null, mark all notifications (admin).
     * @param bool $includeSystem When true and $user_id is provided, also include system-wide notifications. Default false.
     * @return bool
     */
    public function markAllRead($user_id = null, $includeSystem = false)
    {
        $sql = "UPDATE notifications SET status = 'read' WHERE status = 'unread'";
        $params = [];

        if ($user_id !== null) {
            if ($includeSystem) {
                // mark both system (user_id IS NULL or user_id = 0) and the user's notifications
                $sql .= " AND (user_id IS NULL OR user_id = 0 OR user_id = ?)";
                $params[] = $user_id;
            } else {
                // only mark notifications that target this specific user (treat 0 as system marker in some schemas)
                $sql .= " AND (user_id = ? OR user_id = 0)";
                $params[] = $user_id;
            }
        }

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Get unread notification count
     */
    public function getUnreadCount($user_id = null)
    {
        $sql = "SELECT COUNT(*) FROM notifications WHERE status = 'unread'";
        $params = [];
        
        if ($user_id !== null) {
            $sql .= " AND (user_id IS NULL OR user_id = ?)";
            $params[] = $user_id;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Delete old notifications
     */
    public function deleteOld($days = 30)
    {
        $stmt = $this->db->prepare("
            DELETE FROM notifications 
            WHERE status = 'read' 
            AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        return $stmt->execute([$days]);
    }

    /**
     * Create a stock-related notification
     */
    public function createStockNotification($product_id, $stock_quantity, $threshold)
    {
        $stmt = $this->db->prepare("SELECT name FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) return false;

        if ($stock_quantity <= 0) {
            // Individual notification
            $this->create(
                "Out of Stock: {$product['name']} is now out of stock.",
                'out_of_stock',
                $product_id
            );
            return true;
        } elseif ($stock_quantity <= $threshold) {
            // Individual notification
            $this->create(
                "Low Stock Alert: {$product['name']} has only {$stock_quantity} items left.",
                'low_stock',
                $product_id
            );
            return true;
        }
        return true;
    }

    /**
     * Create a transaction notification
     */
    public function createTransactionNotification($transaction_id, $total, $payment_method, $type = 'success')
    {
        $message = $type === 'success' 
            ? "Transaction completed: #{$transaction_id} - Total: {$total} ({$payment_method})"
            : "Transaction failed: #{$transaction_id}";

        return $this->create($message, 'transaction');
    }
}