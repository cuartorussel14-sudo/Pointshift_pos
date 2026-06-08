<?php

class Notification
{
    private static $validTypes = [
        'info',
        'success',
        'error',
        'warning',
        'low_stock',
        'out_of_stock',
        'transaction',
        'expiry',
        'stock_update_mobile',
        'stock_update_manual',
        'product_added',
        'product_updated',
        'product_deleted'
    ];

    public static function create(PDO $db, $message, $type = 'info', $product_id = null)
    {
        // Validate notification type
        if (!in_array($type, self::$validTypes)) {
            $type = 'info'; // Default to info if invalid type
        }

        try {
            // Determine expiry_date from product if this is an expiry notification
            $expiry_date = null;
            if ($type === 'expiry' && $product_id) {
                $pStmt = $db->prepare("SELECT expiry FROM products WHERE id = ?");
                $pStmt->execute([$product_id]);
                $expiry_date = $pStmt->fetchColumn();
            }

            // Try to insert including expiry_date (newer schema). If the column doesn't exist,
            // fall back to inserting without expiry_date for compatibility.
            $insertWithExpiry = $db->prepare("\n                INSERT INTO notifications \n                (message, type, product_id, status, created_at, expiry_date) \n                VALUES (?, ?, ?, 'unread', NOW(), ?)\n            ");
            try {
                $result = $insertWithExpiry->execute([$message, $type, $product_id, $expiry_date]);
            } catch (PDOException $e) {
                error_log('Notification insert with expiry_date failed: ' . $e->getMessage());
                $fallbackInsert = $db->prepare("\n                    INSERT INTO notifications \n                    (message, type, product_id, status, created_at) \n                    VALUES (?, ?, ?, 'unread', NOW())\n                ");
                $result = $fallbackInsert->execute([$message, $type, $product_id]);
            }

            if ($result) {
                // Get the inserted notification for immediate display
                $id = $db->lastInsertId();
                // Try to fetch including expiry_date; fall back if column missing
                try {
                    $fetchStmt = $db->prepare("\n                        SELECT n.*, p.name as product_name, p.expiry as product_expiry, COALESCE(n.expiry_date, CASE WHEN n.type = 'expiry' THEN p.expiry ELSE NULL END) AS expiry_date \n                        FROM notifications n \n                        LEFT JOIN products p ON n.product_id = p.id \n                        WHERE n.id = ?\n                    ");
                    $fetchStmt->execute([$id]);
                    return $fetchStmt->fetch(PDO::FETCH_ASSOC);
                } catch (PDOException $_e) {
                    error_log('Notification fetch with expiry_date failed: ' . $_e->getMessage());
                    $fetchStmt2 = $db->prepare("\n                        SELECT n.*, p.name as product_name, p.expiry as product_expiry, n.status, n.type \n                        FROM notifications n \n                        LEFT JOIN products p ON n.product_id = p.id \n                        WHERE n.id = ?\n                    ");
                    $fetchStmt2->execute([$id]);
                    $row = $fetchStmt2->fetch(PDO::FETCH_ASSOC);
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

    public static function fetchRecent(PDO $db, $limit = 10)
    {
        $sql = "
            SELECT n.*, p.name as product_name, p.stock_quantity, p.low_stock_threshold,
            COALESCE(n.expiry_date, CASE WHEN n.type = 'expiry' THEN p.expiry ELSE NULL END) as expiry_date
            FROM notifications n
            LEFT JOIN products p ON n.product_id = p.id
            ORDER BY n.created_at DESC 
            LIMIT ?
        ";

        try {
            $stmt = $db->prepare($sql);
            $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Notification::fetchRecent fallback: ' . $e->getMessage());
            $stmt2 = $db->prepare("
                SELECT n.*, p.name as product_name, p.stock_quantity, p.low_stock_threshold, p.expiry as product_expiry
                FROM notifications n
                LEFT JOIN products p ON n.product_id = p.id
                ORDER BY n.created_at DESC
                LIMIT ?
            ");
            $stmt2->bindValue(1, (int)$limit, PDO::PARAM_INT);
            $stmt2->execute();
            $rows = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$r) {
                $r['expiry_date'] = ($r['type'] ?? '') === 'expiry' ? ($r['product_expiry'] ?? null) : null;
            }
            return $rows;
        }
    }

    public static function fetchUnread(PDO $db, $limit = 10)
    {
        $sql = "
            SELECT n.*, p.name as product_name, p.stock_quantity, p.low_stock_threshold,
            COALESCE(n.expiry_date, CASE WHEN n.type = 'expiry' THEN p.expiry ELSE NULL END) as expiry_date
            FROM notifications n
            LEFT JOIN products p ON n.product_id = p.id
            WHERE n.status = 'unread'
            ORDER BY n.created_at DESC 
            LIMIT ?
        ";

        try {
            $stmt = $db->prepare($sql);
            $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Notification::fetchUnread fallback: ' . $e->getMessage());
            $stmt2 = $db->prepare("
                SELECT n.*, p.name as product_name, p.stock_quantity, p.low_stock_threshold, p.expiry as product_expiry
                FROM notifications n
                LEFT JOIN products p ON n.product_id = p.id
                WHERE n.status = 'unread'
                ORDER BY n.created_at DESC
                LIMIT ?
            ");
            $stmt2->bindValue(1, (int)$limit, PDO::PARAM_INT);
            $stmt2->execute();
            $rows = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$r) {
                $r['expiry_date'] = ($r['type'] ?? '') === 'expiry' ? ($r['product_expiry'] ?? null) : null;
            }
            return $rows;
        }
    }

    public static function markRead(PDO $db, $id)
    {
        $stmt = $db->prepare("UPDATE notifications SET status = 'read' WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public static function markAllRead(PDO $db)
    {
        $stmt = $db->prepare("UPDATE notifications SET status = 'read' WHERE status = 'unread'");
        return $stmt->execute();
    }

    public static function getUnreadCount(PDO $db)
    {
        $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE status = 'unread'");
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    public static function deleteOld(PDO $db, $days = 30)
    {
        $stmt = $db->prepare("DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        return $stmt->execute([$days]);
    }
}
