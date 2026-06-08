<?php
// Simple migration runner: apply a single .sql file to the database using mysqli.
// Usage from browser (not recommended) or CLI: php tools/apply_sql.php migrations/2025-10-28_add_pending_to_users.sql

require_once __DIR__ . '/../config.php';

if ($argc < 2) {
    echo "Usage: php tools/apply_sql.php path/to/migration.sql\n";
    exit(1);
}

$file = $argv[1];
if (!file_exists($file)) {
    echo "SQL file not found: $file\n";
    exit(1);
}

$sql = file_get_contents($file);
if (!$sql) {
    echo "Failed to read SQL file or file is empty: $file\n";
    exit(1);
}

// Confirm with user in CLI
fwrite(STDOUT, "About to execute SQL file: $file\nProceed? (y/N): ");
$confirm = trim(fgets(STDIN));
if (strtolower($confirm) !== 'y') {
    echo "Aborted by user.\n";
    exit(0);
}

if ($conn->multi_query($sql)) {
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());
    echo "SQL file applied successfully.\n";
} else {
    echo "Error applying SQL: " . $conn->error . "\n";
}

?>