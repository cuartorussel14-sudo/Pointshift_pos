<?php
require_once 'config.php';
requireAdmin();

// Use PDO connection
$db = Database::getInstance()->getConnection();

$search = $_GET['search'] ?? '';
$category_id = $_GET['category_id'] ?? '';
$stock_filter = $_GET['stock_filter'] ?? '';

$whereConditions = [];
$params = [];
$whereConditions[] = "p.status = 'active'";

if ($search) {
    $whereConditions[] = "(p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ? )";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if (!empty($category_id)) {
    $whereConditions[] = "p.category_id = ?";
    $params[] = $category_id;
}
if ($stock_filter === 'low_stock') {
    $whereConditions[] = "p.stock_quantity <= p.low_stock_threshold AND p.stock_quantity > 0";
} elseif ($stock_filter === 'out_of_stock') {
    $whereConditions[] = "p.stock_quantity = 0";
} elseif ($stock_filter === 'in_stock') {
    $whereConditions[] = "p.stock_quantity > 0";
}
$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Fetch products without pagination
$sql = "SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id $whereClause ORDER BY p.id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch expiries for all product IDs (if product_expiries exists)
$productIds = array_column($products, 'id');
$expiriesMap = [];
if (!empty($productIds)) {
    try {
        // Use placeholders for IN
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $peStmt = $db->prepare("SELECT product_id, expiry_date, quantity FROM product_expiries WHERE product_id IN ($placeholders) ORDER BY expiry_date ASC");
        $peStmt->execute($productIds);
        $rows = $peStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $pid = $r['product_id'];
            if (!isset($expiriesMap[$pid])) $expiriesMap[$pid] = [];
            $expiriesMap[$pid][] = ['expiry' => $r['expiry_date'], 'quantity' => $r['quantity']];
        }
    } catch (Exception $e) {
        // If table doesn't exist, we'll use product.expiry fallback per product
    }
}

// Prepare CSV
$filename = 'inventory_export_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$out = fopen('php://output', 'w');
// CSV header
fputcsv($out, ['ID','Name','Category','SKU','Barcode','Price','Stock Quantity','Low Stock Threshold','Expiries','Status']);

foreach ($products as $p) {
    $pid = $p['id'];
    $expiryList = [];
    if (!empty($expiriesMap[$pid])) {
        foreach ($expiriesMap[$pid] as $e) {
            $qty = ($e['quantity'] !== null && $e['quantity'] !== '') ? ' (Qty: ' . $e['quantity'] . ')' : '';
            $expiryList[] = $e['expiry'] . $qty;
        }
    } else {
        if (!empty($p['expiry'])) {
            $expiryList[] = $p['expiry'];
        }
    }
    $expiryField = implode('; ', $expiryList);

    fputcsv($out, [
        $pid,
        $p['name'] ?? '',
        $p['category_name'] ?? '',
        $p['sku'] ?? '',
        $p['barcode'] ?? '',
        $p['price'] ?? '',
        $p['stock_quantity'] ?? 0,
        $p['low_stock_threshold'] ?? '',
        $expiryField,
        $p['status'] ?? ''
    ]);
}

fclose($out);
exit;
?>