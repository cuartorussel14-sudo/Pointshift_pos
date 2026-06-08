<?php
require_once __DIR__ . '/../config.php';
User::requireLogin();

$q = trim($_GET['q'] ?? '');
$db = Database::getInstance()->getConnection();

if ($q !== '') {
    $stmt = $db->prepare("SELECT p.id, p.name, p.sku, p.barcode, p.price, p.stock_quantity, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.barcode LIKE ? OR p.sku LIKE ? OR p.name LIKE ? ORDER BY p.name ASC LIMIT 200");
    $like = '%' . $q . '%';
    $stmt->execute([$like, $like, $like]);
} else {
    $stmt = $db->prepare("SELECT p.id, p.name, p.sku, p.barcode, p.price, p.stock_quantity, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE COALESCE(NULLIF(p.barcode, ''), '') <> '' ORDER BY p.name ASC LIMIT 500");
    $stmt->execute();
}

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

?><!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Debug - Products with Barcode</title>
    <link href="/point-shift_pos-system/assets/css/bootstrap.min.css" rel="stylesheet">
    <style>body{padding:20px;font-family:Segoe UI,Arial}</style>
</head>
<body>
    <h3>Products with Barcode</h3>
    <p>Use the search box to filter by barcode, SKU or name. This is a temporary debug page.</p>
    <form class="mb-3" method="get">
        <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="search" class="form-control" style="max-width:400px;display:inline-block">
        <button class="btn btn-primary">Search</button>
        <a class="btn btn-secondary" href="list_barcodes.php">Show all</a>
    </form>

    <table class="table table-striped table-bordered">
        <thead>
            <tr><th>ID</th><th>Name</th><th>SKU</th><th>Barcode</th><th>Price</th><th>Stock</th><th>Category</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($products as $p): ?>
            <tr>
                <td><?php echo htmlspecialchars($p['id']); ?></td>
                <td><?php echo htmlspecialchars($p['name']); ?></td>
                <td><?php echo htmlspecialchars($p['sku']); ?></td>
                <td><?php echo htmlspecialchars($p['barcode']); ?></td>
                <td><?php echo htmlspecialchars(number_format($p['price'],2)); ?></td>
                <td><?php echo htmlspecialchars($p['stock_quantity']); ?></td>
                <td><?php echo htmlspecialchars($p['category_name']); ?></td>
                <td><a class="btn btn-sm btn-outline-primary" href="/point-shift_pos-system/staff/manage_product.php?barcode=<?php echo urlencode($p['barcode']); ?>">Open</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <p><a href="/point-shift_pos-system/">Back to app</a></p>
</body>
</html>
