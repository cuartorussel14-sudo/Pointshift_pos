<?php
// Check if user is admin first, before including config
$embedded = isset($_GET['embedded']) && $_GET['embedded'] === '1';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    if ($embedded) {
        // If embedded, return a simple message (parent page should be admin)
        echo "<div style='padding:20px;font-family:Arial,sans-serif;color:#b02a37;'>Access denied</div>";
        exit();
    } else {
        header('Location: ../login.php');
        exit();
    }
}

require_once __DIR__ . '/../config.php';

// Debug output (only in development/testing)
if ($embedded && isset($_GET['debug']) && $_GET['debug'] === '1') {
    echo "<div style='padding:10px;background:#f0f0f0;border:1px solid #ccc;margin-bottom:10px;font-family:monospace;font-size:12px;'>";
    echo "<strong>Debug Info:</strong><br>";
    echo "Embedded: " . ($embedded ? 'Yes' : 'No') . "<br>";
    echo "Session user_id: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'Not set') . "<br>";
    echo "Session role: " . (isset($_SESSION['role']) ? $_SESSION['role'] : 'Not set') . "<br>";
    echo "Config loaded: Yes<br>";
    echo "DB_HOST: " . (defined('DB_HOST') ? DB_HOST : 'Not defined') . "<br>";
    echo "DB_NAME: " . (defined('DB_NAME') ? DB_NAME : 'Not defined') . "<br>";
    echo "DB_USER: " . (defined('DB_USER') ? DB_USER : 'Not defined') . "<br>";
    echo "DB_PASS: " . (defined('DB_PASS') ? 'Set' : 'Not set') . "<br>";
    echo "</div>";
}

$initMessages = [];

try {
    require_once __DIR__ . '/../classes/Database.php';
    $dbTest = Database::getInstance()->getConnection();
    if ($embedded) {
        $initMessages[] = "Database connection successful";
    }
} catch (Exception $e) {
    if ($embedded) {
        echo "<div style='padding:20px;font-family:Arial,sans-serif;color:#b02a37;'>Database connection failed: " . htmlspecialchars($e->getMessage()) . "</div>";
        echo "<div style='padding:10px;background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;margin-top:10px;'>";
        echo "<strong>Full error details:</strong><br>";
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
        echo "</div>";
        exit();
    } else {
        die("Database connection failed: " . $e->getMessage());
    }
}

try {
    require_once __DIR__ . '/../classes/BackupManager.php';
    $backupManager = new BackupManager();
    $message = '';
    $error = '';
    if ($embedded) {
        $initMessages[] = "BackupManager initialized successfully";
    }
} catch (Exception $e) {
    if ($embedded) {
        echo "<div style='padding:20px;font-family:Arial,sans-serif;color:#b02a37;'>Error initializing backup manager: " . htmlspecialchars($e->getMessage()) . "</div>";
        echo "<div style='padding:10px;background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;margin-top:10px;'>";
        echo "<strong>Full error details:</strong><br>";
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
        echo "</div>";
        exit();
    } else {
        die("Error initializing backup manager: " . $e->getMessage());
    }
}

// Handle backup/restore actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // Handle delete action
        if ($_POST['action'] === 'delete' && isset($_POST['filename'])) {
            $filename = basename($_POST['filename']);
            $deletedBy = $_SESSION['user_id'] ?? null;
            $result = $backupManager->deleteBackup($filename, $deletedBy);
            if ($result['success']) {
                $message = $result['message'];
            } else {
                $error = $result['message'];
            }
        }

        if ($_POST['action'] === 'backup') {
            $encrypt = isset($_POST['encrypt']) && $_POST['encrypt'] === '1';
            $result = $backupManager->createBackup($encrypt);
            if ($result['success']) {
                $message = $result['message'];
            } else {
                $error = $result['message'];
            }
        } elseif ($_POST['action'] === 'restore' && isset($_POST['filename'])) {
            $result = $backupManager->restoreBackup($_POST['filename']);
            if ($result['success']) {
                $message = $result['message'];
            } else {
                $error = $result['message'];
            }
        }
    }
}

// Get list of backups
try {
    $backups = $backupManager->listBackups();
    if ($embedded) {
        echo "<div style='padding:10px;background:#d4edda;border:1px solid #c3e6cb;color:#155724;margin-bottom:10px;'>Backups list retrieved successfully (" . count($backups) . " backups found)</div>";
    }
} catch (Exception $e) {
    if ($embedded) {
        echo "<div style='padding:20px;font-family:Arial,sans-serif;color:#b02a37;'>Error retrieving backups list: " . htmlspecialchars($e->getMessage()) . "</div>";
        echo "<div style='padding:10px;background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;margin-top:10px;'>";
        echo "<strong>Full error details:</strong><br>";
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
        echo "</div>";
        exit();
    } else {
        die("Error retrieving backups list: " . $e->getMessage());
    }
}

// Diagnostics view (admin-only) for troubleshooting backup client availability
if (isset($_GET['diag']) && $_GET['diag'] === '1') {
    $diag = $backupManager->diagnoseClients();
    header('Content-Type: text/plain; charset=utf-8');
    echo "Backup diagnostic report:\n\n";
    foreach ($diag as $k => $v) {
        if (is_bool($v)) {
            $v = $v ? 'true' : 'false';
        }
        echo "$k: $v\n\n";
    }
    exit();
}

// If not embedded, include root layout which provides header/sidebar
if (!$embedded) {
    $pageTitle = "Backup & Recovery";
    include __DIR__ . '/../layout.php';
} else {
    // Emit minimal head so the embedded UI is styled inside iframe
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Backup & Recovery</title>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
        <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
        <style>body{background:#f8f9fa;font-family:Arial,Helvetica,sans-serif;padding:16px;} .card{box-shadow:0 2px 6px rgba(0,0,0,0.06);}</style>
    </head>
    <body>
    <div class="container-fluid">
    <?php
}
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Backup & Recovery</h1>

    <?php if (!empty($initMessages)): ?>
    <div class="alert alert-info">
        <?php echo htmlspecialchars(implode(' | ', $initMessages)); ?>
    </div>
    <?php endif; ?>

    <?php if ($message): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Create Backup</h6>
                </div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="action" value="backup">
                        <div class="form-group">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="encrypt" name="encrypt" value="1" checked>
                                <label class="custom-control-label" for="encrypt">Encrypt backup</label>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Create Backup</button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Available Backups</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="backupsTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Filename</th>
                                    <th>Created</th>
                                    <th>Size</th>
                                    <th>Encrypted</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($backups as $backup): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($backup['filename']); ?></td>
                                    <td><?php echo htmlspecialchars($backup['created']); ?></td>
                                    <td><?php echo number_format($backup['size'] / 1024 / 1024, 2) . ' MB'; ?></td>
                                    <td><?php echo $backup['encrypted'] ? 'Yes' : 'No'; ?></td>
                                    <td>
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="action" value="restore">
                                            <input type="hidden" name="filename" value="<?php echo htmlspecialchars($backup['filename']); ?>">
                                            <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('Are you sure you want to restore this backup? This will override current data.')">
                                                Restore
                                            </button>
                                        </form>
                                        <a href="download_backup.php?file=<?php echo urlencode($backup['filename']); ?>" class="btn btn-info btn-sm">
                                            Download
                                        </a>
                                        <form method="post" style="display: inline; margin-left:6px;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="filename" value="<?php echo htmlspecialchars($backup['filename']); ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete this backup file? This action cannot be undone.')">
                                                Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($embedded): ?>
<script>
$(document).ready(function() {
    $('#backupsTable').DataTable({
        order: [[1, 'desc']] // Sort by created date by default
    });
});
</script>
</div>
</body>
</html>
<?php else: ?>
<script>
$(document).ready(function() {
    $('#backupsTable').DataTable({
        order: [[1, 'desc']] // Sort by created date by default
    });
});
</script>
<?php endif; ?>
