<?php
// Simple, read-only diagnostics for admins/owners to help debug environment issues.
// This file intentionally avoids printing sensitive secrets (DB_PASS).
header('Content-Type: text/plain; charset=utf-8');
echo "PointShift - simple environment diagnostic\n\n";

// Basic PHP/runtime info
echo "PHP version: " . PHP_VERSION . "\n";
echo "PHP SAPI: " . PHP_SAPI . "\n";
echo "OS: " . PHP_OS . "\n";

// PDO checks
echo "\nPDO available: " . (class_exists('PDO') ? 'yes' : 'no') . "\n";
echo "pdo_mysql extension loaded: " . (extension_loaded('pdo_mysql') ? 'yes' : 'no') . "\n";

// Disabled functions
$disabled = ini_get('disable_functions');
echo "\nDisabled functions: " . ($disabled ? $disabled : '(none)') . "\n";

// exec availability
$execAvailable = function_exists('exec') && stripos($disabled, 'exec') === false;
echo "exec() available: " . ($execAvailable ? 'yes' : 'no') . "\n";

// Backup path checks
$backupPath = realpath(dirname(__DIR__) . '/backups') ?: (dirname(__DIR__) . '/backups');
echo "\nBackup path (resolved): " . $backupPath . "\n";
echo "Backup path exists: " . (is_dir($backupPath) ? 'yes' : 'no') . "\n";
echo "Backup path writable: " . (is_writable($backupPath) ? 'yes' : 'no') . "\n";

// mysqldump / mysql info (only when exec available)
function tryExecVersion($cmd) {
    $out = [];
    $rc = -1;
    @exec($cmd . ' 2>&1', $out, $rc);
    return ['rc' => $rc, 'output' => implode("\n", $out)];
}

if ($execAvailable) {
    $mysqldump = defined('MYSQLDUMP_PATH') ? constant('MYSQLDUMP_PATH') : 'mysqldump';
    $mysql = defined('MYSQL_PATH') ? constant('MYSQL_PATH') : 'mysql';

    echo "\nmysqldump candidate: " . $mysqldump . "\n";
    $r = tryExecVersion($mysqldump . ' --version');
    echo "mysqldump rc: " . $r['rc'] . "\n";
    echo "mysqldump output:\n" . ($r['output'] ? $r['output'] : '(none)') . "\n";

    echo "\nmysql candidate: " . $mysql . "\n";
    $r2 = tryExecVersion($mysql . ' --version');
    echo "mysql rc: " . $r2['rc'] . "\n";
    echo "mysql output:\n" . ($r2['output'] ? $r2['output'] : '(none)') . "\n";
} else {
    echo "\nSkipping mysqldump/mysql checks because exec() is unavailable or disabled.\n";
}

// Show a masked example of the constructed mysqldump command (password masked)
if (defined('DB_HOST') && defined('DB_USER') && defined('DB_NAME')) {
    $masked = (defined('DB_PASS') && DB_PASS !== '') ? '****' : '(empty)';
    echo "\nConstructed command example (password masked):\n";
    echo sprintf("%s --host=%s --user=%s %s > %sbackup_...sql\n",
        (defined('MYSQLDUMP_PATH') ? MYSQLDUMP_PATH : 'mysqldump'),
        DB_HOST,
        DB_USER,
        DB_NAME,
        $backupPath . DIRECTORY_SEPARATOR
    );
    echo "(DB password: " . $masked . ")\n";
} else {
    echo "\nDatabase constants (DB_HOST/DB_USER/DB_NAME) are not defined in this context.\n";
}

echo "\nNext steps:\n";
echo " - If PDO or pdo_mysql are missing, enable the PHP extensions (Hostinger hPanel -> PHP -> Extensions) or enable them in php.ini for local XAMPP.\n";
echo " - If exec() is disabled or mysqldump isn't found, either enable exec/ensure binaries exist or set MYSQLDUMP_PATH / MYSQL_PATH in config.php to the correct paths.\n";
echo " - If backup path isn't writable, ensure the webserver user can write to the 'backups/' directory.\n";

// End
?>
