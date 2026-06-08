<?php
require_once '../config.php';
User::requireLogin();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../login.php');
    exit();
}

$title = 'Mobile App Downloads';
$message = '';
$messageType = '';
try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    $message = 'Database connection error: ' . $e->getMessage();
    $messageType = 'danger';
    $db = null;
}

// Upload functionality removed

// Get uploaded apps
if ($db) {
    $stmt = $db->prepare("SELECT * FROM mobile_app_uploads ORDER BY upload_date DESC");
    $stmt->execute();
    $uploadedApps = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Remove entries where file no longer exists
    foreach ($uploadedApps as $key => $app) {
        // Resolve filesystem path relative to staff/ to check existence/size
        $fsPath = __DIR__ . '/../' . ltrim($app['filepath'], '/');
        if (!file_exists($fsPath)) {
            $deleteStmt = $db->prepare("DELETE FROM mobile_app_uploads WHERE id = ?");
            $deleteStmt->execute([$app['id']]);
            unset($uploadedApps[$key]);
        } else {
            // Overwrite displayed size using resolved FS path
            $uploadedApps[$key]['_fsPath'] = $fsPath;
        }
    }
} else {
    $uploadedApps = [];
}

ob_start();
?>
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">


            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">📋 Uploaded Mobile Apps</h5>
                    <small class="text-muted">Download uploaded mobile app versions</small>
                </div>
                <div class="card-body">
                    <?php if (empty($uploadedApps)): ?>
                        <div class="alert alert-info">No mobile apps uploaded yet.</div>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($uploadedApps as $app): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($app['filename']); ?></h6>
                                        <small class="text-muted">
                                            Uploaded: <?php echo date('M j, Y g:i A', strtotime($app['upload_date'])); ?> |
                                            Size: <?php echo isset($app['_fsPath']) && file_exists($app['_fsPath']) ? round(filesize($app['_fsPath']) / 1024 / 1024, 2) . ' MB' : 'File not found'; ?>
                                        </small>
                                    </div>
                                    <div>
                                        <a href="<?php echo htmlspecialchars('../' . ltrim($app['filepath'], '/')); ?>" class="btn btn-sm btn-outline-primary" download>
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mt-3">
                <a href="barcode_scanner.php" class="btn btn-secondary">Back to Scanner</a>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/views/layout.php';
?>
