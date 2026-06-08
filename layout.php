<?php
// Backwards-compatibility wrapper for older includes that expect views/layout.php
// New layout files live in views/layouts/main.php — include that file here.
$wrapperPath = __DIR__ . '/layouts/main.php';
if (file_exists($wrapperPath)) {
    require_once $wrapperPath;
} else {
    // Fallback: emit a minimal HTML page so CLI/requests don't fatally error
    if (!headers_sent()) header('Content-Type: text/html; charset=utf-8');
    echo "<!doctype html><html><head><meta charset=\"utf-8\"><title>Layout missing</title></head><body>";
    echo "<h1>Layout file missing</h1><p>Expected layout at: " . htmlspecialchars($wrapperPath) . "</p>";
    echo "</body></html>";
    exit;
}
