<?php
require_once 'config.php';
require_once 'classes/Encryption.php';

echo "Starting product encryption migration...\n";

try {
    $encryption = Encryption::getInstance();

    // Get all products that haven't been encrypted yet
    $result = $conn->query("SELECT id, name, sku, barcode, description FROM products WHERE name_encrypted IS NULL");
    $products = $result->fetch_all(MYSQLI_ASSOC);

    echo "Found " . count($products) . " products to encrypt.\n";

    foreach ($products as $product) {
        // Encrypt fields
        $nameEncrypted = $encryption->encrypt($product['name'] ?? '');
        $skuEncrypted = $encryption->encrypt($product['sku'] ?? '');
        $barcodeEncrypted = $encryption->encrypt($product['barcode'] ?? '');
        $descriptionEncrypted = $encryption->encrypt($product['description'] ?? '');

        // Update the product with encrypted data
        $sql = "UPDATE products SET
            name_encrypted = '" . $conn->real_escape_string($nameEncrypted['data']) . "',
            name_iv = '" . $conn->real_escape_string($nameEncrypted['iv']) . "',
            name_tag = '" . $conn->real_escape_string($nameEncrypted['tag']) . "',
            sku_encrypted = '" . $conn->real_escape_string($skuEncrypted['data']) . "',
            sku_iv = '" . $conn->real_escape_string($skuEncrypted['iv']) . "',
            sku_tag = '" . $conn->real_escape_string($skuEncrypted['tag']) . "',
            barcode_encrypted = '" . $conn->real_escape_string($barcodeEncrypted['data']) . "',
            barcode_iv = '" . $conn->real_escape_string($barcodeEncrypted['iv']) . "',
            barcode_tag = '" . $conn->real_escape_string($barcodeEncrypted['tag']) . "',
            description_encrypted = '" . $conn->real_escape_string($descriptionEncrypted['data']) . "',
            description_iv = '" . $conn->real_escape_string($descriptionEncrypted['iv']) . "',
            description_tag = '" . $conn->real_escape_string($descriptionEncrypted['tag']) . "'
            WHERE id = " . (int)$product['id'];

        if ($conn->query($sql)) {
            echo "Encrypted product: {$product['name']}\n";
        } else {
            echo "Error updating product {$product['id']}: " . $conn->error . "\n";
        }
    }

    echo "Migration completed successfully!\n";

} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
