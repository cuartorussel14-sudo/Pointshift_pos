<?php
require_once 'config.php';
User::requireLogin();

$db = Database::getInstance()->getConnection();
// Check for exact name
$stmt = $db->prepare("
    SELECT 
        id, name, status, stock_quantity, category,
        CASE 
            WHEN status != 'active' THEN 'Product is not active'
            WHEN stock_quantity <= 0 THEN 'Out of stock'
            ELSE 'Should be visible in POS'
        END as visibility_status
    FROM products 
    WHERE name LIKE ?
");
$stmt->execute(['%Great Taste Premium Classic 3 in 1 Coffee%']);
$exactProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check for similar coffee products
$stmt = $db->prepare("
    SELECT 
        id, name, status, stock_quantity, category,
        CASE 
            WHEN status != 'active' THEN 'Product is not active'
            WHEN stock_quantity <= 0 THEN 'Out of stock'
            ELSE 'Should be visible in POS'
        END as visibility_status
    FROM products 
    WHERE 
        (category LIKE '%coffee%' OR category LIKE '%beverage%' OR name LIKE '%coffee%')
        AND status = 'active'
    ORDER BY name ASC
");
$stmt->execute();
$similarProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt->execute(['%Great Taste%']);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Check Product</title>
    <style>
        pre { font-family: monospace; }
    </style>
</head>
<body>
<h2>Product Check Results</h2>
<pre>
<?php
echo "=== Exact Product Search ===\n";
if (empty($exactProducts)) {
    echo "No products found matching 'Great Taste Premium Classic 3 in 1 Coffee'\n";
} else {
    foreach ($exactProducts as $p) {
        echo "ID: {$p['id']}\n";
        echo "Name: {$p['name']}\n";
        echo "Status: {$p['status']}\n";
        echo "Stock: {$p['stock_quantity']}\n";
        echo "Category: {$p['category']}\n";
        echo "Visibility: {$p['visibility_status']}\n";
        echo "-------------------\n";
    }
}

echo "\n=== Similar Coffee Products ===\n";
if (empty($similarProducts)) {
    echo "No similar coffee products found\n";
} else {
    foreach ($similarProducts as $p) {
        echo "ID: {$p['id']}\n";
        echo "Name: {$p['name']}\n";
        echo "Status: {$p['status']}\n";
        echo "Stock: {$p['stock_quantity']}\n";
        echo "Category: {$p['category']}\n";
        echo "Visibility: {$p['visibility_status']}\n";
        echo "-------------------\n";
    }
}
?>
</pre>
<hr>
<h3>Debug SQL Query:</h3>
<pre>
SELECT id, name, status, stock_quantity, category 
FROM products 
WHERE name LIKE '%Great Taste%'
</pre>
</body>
</html>