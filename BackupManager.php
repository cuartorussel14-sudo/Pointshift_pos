<?php

class BackupManager {
    private $db;
    private $backupPath;
    private $encryptionKey;
    
    public function __construct() {
        require_once __DIR__ . '/Database.php';
        $this->db = Database::getInstance()->getConnection();
        $this->backupPath = dirname(__DIR__) . '/backups/';

        // Create backup directory if it doesn't exist
        if (!is_dir($this->backupPath)) {
            mkdir($this->backupPath, 0755, true);
        }

        // Load encryption key if exists
        $keyFile = dirname(__DIR__) . '/config/encryption.key';
        if (file_exists($keyFile)) {
            $this->encryptionKey = base64_decode(file_get_contents($keyFile));
        }
    }

    /**
     * Clean dump file by removing deprecation warnings and sandbox mode lines
     * @param string $filePath
     */
    private function cleanDumpFile($filePath) {
        if (!file_exists($filePath)) return;

        $contents = file_get_contents($filePath);
        $originalSize = strlen($contents);

        // Split by various line endings to handle different formats
        $lines = preg_split('/\r\n|\r|\n/', $contents);
        $cleanLines = [];
        $removedCount = 0;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Remove lines that contain mysqldump warnings, deprecation messages, or problematic SQL comments
            $isBad = false;

            // Check for mysqldump warnings
            if (strpos($trimmed, 'mysqldump:') !== false) {
                $isBad = true;
            }
            // Check for deprecation warnings
            elseif (strpos($trimmed, 'Deprecated') !== false) {
                $isBad = true;
            }
            // Check for separator lines
            elseif (strpos($trimmed, '--------------') !== false) {
                $isBad = true;
            }
            // Check for MySQL/MariaDB version-specific comments that can cause issues
            elseif (preg_match('/^\/\*![0-9]+/', $trimmed)) {
                $isBad = true;
            }
            // Check for sandbox mode comments
            elseif (strpos($trimmed, 'enable the sandbox mode') !== false) {
                $isBad = true;
            }
            // Check for SQL comments that might cause issues
            elseif (preg_match('/^\/\*M!/', $trimmed)) {
                $isBad = true;
            }
            // Remove empty lines or lines with only whitespace
            elseif ($trimmed === '') {
                $isBad = true;
            }

            if (!$isBad) {
                $cleanLines[] = $line;
            } else {
                $removedCount++;
            }
        }

        // Write back the cleaned content
        $cleanContent = implode("\n", $cleanLines);
        file_put_contents($filePath, $cleanContent);

        // Log the cleaning operation for debugging
        $newSize = strlen($cleanContent);
        error_log("Cleaned dump file: removed $removedCount lines, size: $originalSize -> $newSize bytes");
    }
    
    /**
     * Create a backup of the database
     * @param bool $encrypt Whether to encrypt the backup
     * @return array Status of the backup operation
     */
    public function createBackup($encrypt = true) {
        try {
            $timestamp = date('Y-m-d_H-i-s');
            $filename = "backup_{$timestamp}.sql";
            $backupFile = $this->backupPath . $filename;
            
            // Get database credentials from config
            require_once dirname(__DIR__) . '/config.php';
            

            // Determine mysqldump path (allow override via config, prefer mariadb-dump for MariaDB)
            $mysqldump = defined('MYSQLDUMP_PATH') ? constant('MYSQLDUMP_PATH') : (file_exists('/usr/bin/mariadb-dump') ? 'mariadb-dump' : 'mysqldump');

            // Prepare verification snapshot (capture MAX ids / latest timestamps before dump)
            $verifyTables = [
                'products' => ['id_col' => 'id', 'time_col' => 'updated_at'],
                'orders' => ['id_col' => 'id', 'time_col' => 'created_at']
            ];
            $preSnapshot = [];
            foreach ($verifyTables as $table => $cols) {
                try {
                    $res = $this->db->query("SELECT 1 FROM information_schema.tables WHERE table_schema = '" . DB_NAME . "' AND table_name = '" . $table . "' LIMIT 1");
                    if (!$res || $res->rowCount() == 0) continue;
                } catch (Exception $_) {
                    continue;
                }

                try {
                    $maxIdStmt = $this->db->prepare("SELECT MAX(" . $cols['id_col'] . ") AS max_id FROM " . $table);
                    $maxIdStmt->execute();
                    $maxId = $maxIdStmt->fetchColumn();
                } catch (Exception $_) {
                    $maxId = null;
                }

                $latestTime = null;
                try {
                    $timeCol = $cols['time_col'];
                    $timeCheck = $this->db->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '" . DB_NAME . "' AND TABLE_NAME = '" . $table . "' AND COLUMN_NAME = '" . $timeCol . "' LIMIT 1");
                    if ($timeCheck && $timeCheck->rowCount() > 0) {
                        $timeStmt = $this->db->prepare("SELECT MAX(" . $timeCol . ") AS latest FROM " . $table);
                        $timeStmt->execute();
                        $latestTime = $timeStmt->fetchColumn();
                    }
                } catch (Exception $_) {
                    $latestTime = null;
                }

                $preSnapshot[$table] = ['max_id' => $maxId, 'latest' => $latestTime];
            }

            // Decide whether to include --events based on server setting.
            // Query the server for @@event_scheduler; if it's ON (or 1) include events.
            $includeEvents = false;
            try {
                $stmtEvt = $this->db->query("SELECT @@event_scheduler AS evs");
                $val = $stmtEvt->fetchColumn();
                if ($val !== false && (strtoupper((string)$val) === 'ON' || (int)$val === 1)) {
                    $includeEvents = true;
                }
            } catch (Exception $_) {
                // ignore and default to not including events
                $includeEvents = false;
            }

            // Build command using options that include routines, triggers and
            // transaction-safe dumping for InnoDB so the dump contains all DB objects
            // (tables like products, stored routines, triggers) and is consistent.
            $command = sprintf(
                '%s --host=%s --user=%s --routines %s --triggers --single-transaction --quick --skip-lock-tables --databases %s',
                escapeshellcmd($mysqldump),
                escapeshellarg(DB_HOST),
                escapeshellarg(DB_USER),
                $includeEvents ? '--events' : '',
                escapeshellarg(DB_NAME)
            );

            $dbPass = defined('DB_PASS') ? DB_PASS : '';
            if ($dbPass !== '') {
                // use --password to avoid a space which some shells interpret
                $command .= ' --password=' . escapeshellarg($dbPass);
            }

            // Redirect output to the backup file
            $command .= ' > ' . escapeshellarg($backupFile) . ' 2>&1';

            // Capture start time for duration measurement
            $startTime = microtime(true);
            // Run the dump and capture output
            exec($command, $output, $returnCode);

            // After dump: verify that expected indicators are present in the dump file
            $verificationResults = [];
            if (file_exists($backupFile) && is_readable($backupFile)) {
                // read (potentially large) file but reasonable for typical DB sizes; if very large this could be optimized
                $contents = file_get_contents($backupFile);
                foreach ($preSnapshot as $table => $info) {
                    $found = false;
                    if (!empty($info['max_id'])) {
                        // look for the id value in the dump; allow for patterns like '(123,' or 'VALUES (123,'
                        $pattern1 = '(' . $info['max_id'] . ',';
                        $pattern2 = 'VALUES (' . $info['max_id'] . ',';
                        if (strpos($contents, $pattern1) !== false || strpos($contents, $pattern2) !== false || strpos($contents, (string)$info['max_id']) !== false) {
                            $found = true;
                        }
                    }
                    $verificationResults[$table] = $found ? 'present' : 'missing';
                }
                unset($contents);
            } else {
                foreach ($preSnapshot as $table => $info) {
                    $verificationResults[$table] = 'file_missing';
                }
            }

            if ($returnCode !== 0) {
                $outputStr = is_array($output) ? implode("\n", $output) : (string)$output;

                // If mysqldump failed because the event scheduler is disabled, retry
                // without --events (some servers disable events). Also remove any
                // partial backup file created by the failed run.
                if (file_exists($backupFile)) {
                    @unlink($backupFile);
                }

                $lowerOut = strtolower($outputStr);
                if (str_contains($lowerOut, 'event scheduler is disabled') || str_contains($lowerOut, "couldn't execute 'show events'") || str_contains($lowerOut, 'couldn\'t execute')) {
                    // rebuild command without --events
                    $commandNoEvents = str_replace('--events ', '', $command);
                    exec($commandNoEvents, $output2, $returnCode2);
                    $output2Str = is_array($output2) ? implode("\n", $output2) : (string)$output2;
                    if ($returnCode2 === 0) {
                        // succeeded on retry
                        $output = $output2;
                        $returnCode = $returnCode2;
                    } else {
                        throw new Exception("Backup failed (after retry without --events) with code $returnCode2. Output:\n" . $output2Str);
                    }
                }

                if ($returnCode !== 0) {
                    throw new Exception("Backup failed with code $returnCode. Output:\n" . $outputStr);
                }
            }

            // Clean the dump file to remove deprecation warnings and sandbox mode lines
            $this->cleanDumpFile($backupFile);

            // Encrypt the backup if requested
            if ($encrypt && $this->encryptionKey) {
                $encryptedFile = $backupFile . '.enc';
                $iv = random_bytes(16);
                $tag = null;
                
                $encrypted = openssl_encrypt(
                    file_get_contents($backupFile),
                    'aes-256-gcm',
                    $this->encryptionKey,
                    OPENSSL_RAW_DATA,
                    $iv,
                    $tag
                );
                
                if ($encrypted === false) {
                    throw new Exception("Encryption failed");
                }
                
                // Save encrypted data with IV and tag
                file_put_contents($encryptedFile, base64_encode($iv) . "\n" . base64_encode($tag) . "\n" . base64_encode($encrypted));
                unlink($backupFile); // Remove unencrypted file
                $filename .= '.enc';
            }
            
            // Calculate duration and size for logging
            $durationSeconds = 0;
            if (isset($startTime)) {
                $durationSeconds = (int) round(microtime(true) - $startTime);
            }
            $filePath = $this->backupPath . $filename;
            $fileSize = file_exists($filePath) ? filesize($filePath) : null;

            // Log the backup with additional metadata (file_path, size, duration, created_by)
            try {
                if (session_status() === PHP_SESSION_NONE) {
                    @session_start();
                }
                $createdBy = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
            } catch (Exception $_) {
                $createdBy = null;
            }

            // Insert into backup_logs using only columns that exist in the current schema
            try {
                $colsRes = $this->db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='" . DB_NAME . "' AND TABLE_NAME='backup_logs'");
                $existingCols = [];
                if ($colsRes) {
                    foreach ($colsRes->fetchAll(PDO::FETCH_COLUMN) as $c) {
                        $existingCols[] = $c;
                    }
                }
            } catch (Exception $_) {
                // If the information_schema query fails, fallback to a conservative insert
                $existingCols = [];
            }

            // Build insert dynamically
            $insertCols = [];
            $placeholders = [];
            $params = [];

            // Always include filename
            if (in_array('filename', $existingCols) || empty($existingCols)) {
                $insertCols[] = 'filename'; $placeholders[] = '?'; $params[] = $filename;
            }
            if (in_array('file_path', $existingCols)) { $insertCols[] = 'file_path'; $placeholders[] = '?'; $params[] = $filePath; }
            if (in_array('created_by', $existingCols)) { $insertCols[] = 'created_by'; $placeholders[] = '?'; $params[] = $createdBy; }
            if (in_array('created_at', $existingCols)) { $insertCols[] = 'created_at'; $placeholders[] = 'NOW()'; }
            if (in_array('encrypted', $existingCols)) { $insertCols[] = 'encrypted'; $placeholders[] = '?'; $params[] = $encrypt ? 1 : 0; }
            if (in_array('size', $existingCols)) { $insertCols[] = 'size'; $placeholders[] = '?'; $params[] = $fileSize; }
            if (in_array('duration_seconds', $existingCols)) { $insertCols[] = 'duration_seconds'; $placeholders[] = '?'; $params[] = $durationSeconds; }
            if (in_array('status', $existingCols)) { $insertCols[] = 'status'; $placeholders[] = '?'; $params[] = 'success'; }

            if (empty($insertCols)) {
                // No backup_logs table or no columns discovered: skip logging
            } else {
                $colsSql = implode(', ', $insertCols);
                $placeSql = implode(', ', $placeholders);
                $sql = "INSERT INTO backup_logs ({$colsSql}) VALUES ({$placeSql})";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }
            
            // Automated verification: record current max ids and latest timestamps for key tables
            $verifyTables = [
                'products' => ['id_col' => 'id', 'time_col' => 'updated_at'],
                'orders' => ['id_col' => 'id', 'time_col' => 'created_at']
            ];
            $notes = [];
            foreach ($verifyTables as $table => $cols) {
                // Check if table exists
                try {
                    $res = $this->db->query("SELECT 1 FROM information_schema.tables WHERE table_schema = '" . DB_NAME . "' AND table_name = '" . $table . "' LIMIT 1");
                    if (!$res || $res->rowCount() == 0) continue;
                } catch (Exception $_) {
                    // skip if information_schema not accessible via this DB wrapper
                }

                // Get max id
                try {
                    $maxIdStmt = $this->db->prepare("SELECT MAX(" . $cols['id_col'] . ") AS max_id FROM " . $table);
                    $maxIdStmt->execute();
                    $maxId = $maxIdStmt->fetchColumn();
                } catch (Exception $_) {
                    $maxId = null;
                }

                // Get latest timestamp (if column exists)
                $latestTime = null;
                try {
                    $timeCol = $cols['time_col'];
                    $timeCheck = $this->db->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='" . DB_NAME . "' AND TABLE_NAME='" . $table . "' AND COLUMN_NAME='" . $timeCol . "' LIMIT 1");
                    if ($timeCheck && $timeCheck->rowCount() > 0) {
                        $timeStmt = $this->db->prepare("SELECT MAX(" . $timeCol . ") AS latest FROM " . $table);
                        $timeStmt->execute();
                        $latestTime = $timeStmt->fetchColumn();
                    }
                } catch (Exception $_) {
                    $latestTime = null;
                }

                $notes[] = $table . ":max_id=" . ($maxId ?? 'null') . ",latest=" . ($latestTime ?? 'null');
            }

            if (!empty($notes)) {
                $noteStr = implode("; ", $notes);
                try {
                    $upd = $this->db->prepare("UPDATE backup_logs SET notes = ? WHERE filename = ?");
                    $upd->execute([$noteStr, $filename]);
                } catch (Exception $_) {
                    // ignore update failure
                }
            }
            
            return [
                'success' => true,
                'message' => 'Backup created successfully',
                'filename' => $filename
            ];
            
        } catch (Exception $e) {
            // Persist error output to a log file for easier debugging
            try {
                $errFile = $this->backupPath . 'backup_error_' . date('Ymd_His') . '.log';
                file_put_contents($errFile, $e->getMessage());
            } catch (Exception $_) {
                // ignore file write errors
            }

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Restore database from a backup file
     * @param string $filename Name of the backup file
     * @return array Status of the restore operation
     */
    public function restoreBackup($filename) {
        try {
            $backupFile = $this->backupPath . $filename;
            
            if (!file_exists($backupFile)) {
                throw new Exception("Backup file not found");
            }
            
            // Check if file is encrypted
            $isEncrypted = substr($filename, -4) === '.enc';
            
            if ($isEncrypted) {
                if (!$this->encryptionKey) {
                    throw new Exception("Encryption key not found");
                }
                
                // Read and decrypt the file
                $contents = file_get_contents($backupFile);
                list($iv, $tag, $encrypted) = explode("\n", $contents, 3);
                
                $decrypted = openssl_decrypt(
                    base64_decode($encrypted),
                    'aes-256-gcm',
                    $this->encryptionKey,
                    OPENSSL_RAW_DATA,
                    base64_decode($iv),
                    base64_decode($tag)
                );
                
                if ($decrypted === false) {
                    throw new Exception("Decryption failed");
                }
                
                $tempFile = $this->backupPath . 'temp_restore.sql';
                file_put_contents($tempFile, $decrypted);
                $backupFile = $tempFile;
            }
            
            // Get database credentials
            require_once dirname(__DIR__) . '/config.php';

            // Determine mysql client path (allow override via config, prefer mariadb for MariaDB)
            $mysql = defined('MYSQL_PATH') ? constant('MYSQL_PATH') : (file_exists('/usr/bin/mariadb') ? 'mariadb' : 'mysql');

            // Restore the backup using mysql client with foreign key checks disabled
            // Using the DB name here is fine because the dump includes the database;
            // mysql will execute CREATE/USE statements present in the dump produced by --databases as needed.
            $command = sprintf(
                '%s --init-command="SET FOREIGN_KEY_CHECKS=0;" --host=%s --user=%s %s',
                escapeshellcmd($mysql),
                escapeshellarg(DB_HOST),
                escapeshellarg(DB_USER),
                escapeshellarg(DB_NAME)
            );

            $dbPass = defined('DB_PASS') ? DB_PASS : '';
            if ($dbPass !== '') {
                $command .= ' --password=' . escapeshellarg($dbPass);
            }

            $command .= ' < ' . escapeshellarg($backupFile) . ' 2>&1';

            exec($command, $output, $returnCode);

            // Clean up temp file if it exists
            if ($isEncrypted && file_exists($tempFile)) {
                unlink($tempFile);
            }

            if ($returnCode !== 0) {
                $outputStr = is_array($output) ? implode("\n", $output) : (string)$output;
                throw new Exception("Restore failed with code $returnCode. Output:\n" . $outputStr);
            }
            
            // Log the restore
            $stmt = $this->db->prepare("INSERT INTO restore_logs (filename, restored_at) VALUES (?, NOW())");
            $stmt->execute([$filename]);
            
            return [
                'success' => true,
                'message' => 'Restore completed successfully'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get list of available backups
     * @return array List of backups with details
     */
    public function listBackups() {
        try {
            $backups = [];
            $files = scandir($this->backupPath);

            foreach ($files as $file) {
                if ($file === '.' || $file === '..') continue;

                $filePath = $this->backupPath . $file;
                $backups[] = [
                    'filename' => $file,
                    'size' => filesize($filePath),
                    'created' => date('Y-m-d H:i:s', filemtime($filePath)),
                    'encrypted' => substr($file, -4) === '.enc'
                ];
            }

            return $backups;

        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Delete a backup file and mark it in the logs
     * @param string $filename
     * @param int|null $deletedBy
     * @return array
     */
    public function deleteBackup($filename, $deletedBy = null) {
        try {
            $backupFile = $this->backupPath . $filename;
            $real = realpath($backupFile);
            $backupDirReal = realpath($this->backupPath);
            if ($real === false || strpos($real, $backupDirReal) !== 0) {
                throw new Exception("Invalid backup file");
            }

            if (file_exists($real)) {
                if (!unlink($real)) {
                    throw new Exception("Failed to delete backup file");
                }
            }

            // Update backup_logs if exists
            try {
                $stmt = $this->db->prepare("UPDATE backup_logs SET status = 'deleted', notes = CONCAT(IFNULL(notes, ''), :note), size = 0 WHERE filename = :filename");
                $note = "Deleted by user id: " . ($deletedBy ?? 'unknown') . " at " . date('Y-m-d H:i:s') . "\\n";
                $stmt->execute([':note' => $note, ':filename' => $filename]);
            } catch (Exception $e) {
                // ignore logging errors
            }

            return ['success' => true, 'message' => 'Backup deleted'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Diagnostic helper: checks mysqldump/mysql paths, backup dir, and key file
     * Returns an array of diagnostic info useful for debugging failures.
     */
    public function diagnoseClients() {
        $info = [];

        $mysqldump = defined('MYSQLDUMP_PATH') ? constant('MYSQLDUMP_PATH') : (file_exists('/usr/bin/mariadb-dump') ? 'mariadb-dump' : 'mysqldump');
        $mysql = defined('MYSQL_PATH') ? constant('MYSQL_PATH') : (file_exists('/usr/bin/mariadb') ? 'mariadb' : 'mysql');

        $info['mysqldump'] = $mysqldump;
        $info['mysql'] = $mysql;
        $info['mysqldump_exists'] = file_exists($mysqldump);
        $info['mysql_exists'] = file_exists($mysql);
        $info['backup_path'] = $this->backupPath;
        $info['backup_path_writable'] = is_writable($this->backupPath);

        $keyFile = dirname(__DIR__) . '/config/encryption.key';
        $info['encryption_key_file'] = $keyFile;
        $info['encryption_key_exists'] = file_exists($keyFile);
        $info['encryption_key_readable'] = is_readable($keyFile);

        // Try to run --version on mysqldump and mysql to capture any immediate errors
        $versionCmd = escapeshellcmd($mysqldump) . ' --version 2>&1';
        exec($versionCmd, $outDump, $rcDump);
        $info['mysqldump_version_rc'] = $rcDump;
        $info['mysqldump_version_output'] = is_array($outDump) ? implode("\n", $outDump) : (string)$outDump;

        $versionCmd2 = escapeshellcmd($mysql) . ' --version 2>&1';
        exec($versionCmd2, $outMysql, $rcMysql);
        $info['mysql_version_rc'] = $rcMysql;
        $info['mysql_version_output'] = is_array($outMysql) ? implode("\n", $outMysql) : (string)$outMysql;

        // Show the constructed backup command (with password masked)
        $maskedPass = '';
        $dbPass = defined('DB_PASS') ? DB_PASS : '';
        if ($dbPass !== '') $maskedPass = '****';

        $constructed = sprintf(
            "%s --host=%s --user=%s %s",
            $mysqldump,
            DB_HOST,
            DB_USER,
            DB_NAME
        );
        if ($maskedPass !== '') {
            $constructed .= ' --password=' . $maskedPass;
        }
        $constructed .= ' > ' . $this->backupPath . 'backup_...sql';
        $info['constructed_command_example'] = $constructed;

        return $info;
    }
}