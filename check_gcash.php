<?php
require_once __DIR__ . '/../config.php';

header_remove(); // ensure CLI output only

$res = $conn->query("SELECT * FROM payment_qrcodes WHERE payment_method = 'gcash' AND is_active = 1 LIMIT 1");
if (!$res) {
    echo "DB query failed: " . $conn->error . PHP_EOL;
    exit(1);
}
$row = $res->fetch_assoc();
if (!$row) {
    echo "No active GCash QR row found in payment_qrcodes.\n";
    exit(0);
}

echo "DB row:\n";
echo json_encode($row, JSON_PRETTY_PRINT) . PHP_EOL;

$path = __DIR__ . '/../' . $row['qr_code_path'];
if (file_exists($path)) {
    echo "File exists at: " . $path . PHP_EOL;
} else {
    echo "File missing. Expected at: " . $path . PHP_EOL;
}

// Show web path used by POS
$webPath = $row['qr_code_path'] ?? '';
echo "Web path used in UI: " . $webPath . PHP_EOL;

?>