<?php
// CLI helper to create a backup quickly. Usage: php tools/run_backup.php [--no-encrypt]
chdir(__DIR__ . '/../');
require_once 'config.php';
require_once 'classes/BackupManager.php';

$encrypt = true;
if (in_array('--no-encrypt', $argv)) $encrypt = false;

$bm = new BackupManager();
$res = $bm->createBackup($encrypt);
if ($res['success']) {
    echo "Backup created: " . ($res['filename'] ?? '(unknown)') . "\n";
    // Try to show latest backup_logs row if available
    try {
        $stmt = $conn->prepare("SELECT id, filename, created_at, size, notes FROM backup_logs ORDER BY created_at DESC LIMIT 1");
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) {
            echo "Backup log: ID=" . $row['id'] . " file=" . $row['filename'] . " created_at=" . $row['created_at'] . " size=" . ($row['size'] ?? 'N/A') . "\n";
            echo "Notes: " . ($row['notes'] ?? '(none)') . "\n";
        }
    } catch (Exception $e) {
        // ignore
    }
    exit(0);
} else {
    echo "Backup failed: " . ($res['message'] ?? 'unknown error') . "\n";
    exit(2);
}
