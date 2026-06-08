<?php
require_once 'config.php';
requireLogin();
requireAdmin();

$page_title = "Settings";

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Store Configuration Update
    if ($action === 'update_store_config') {
        $settings = [
            'store_name' => $_POST['store_name'],
            'store_branch' => $_POST['store_branch'],
            'store_address' => $_POST['store_address'],
            'store_phone' => $_POST['store_phone'],
            'store_email' => $_POST['store_email'],
            'business_hours_open' => $_POST['business_hours_open'],
            'business_hours_close' => $_POST['business_hours_close'],
            'business_days' => $_POST['business_days'],
            'receipt_header' => $_POST['receipt_header'],
            'receipt_footer' => $_POST['receipt_footer'],
            'tax_rate' => $_POST['tax_rate'],
            'currency_symbol' => $_POST['currency_symbol'],
            'receipt_show_logo' => isset($_POST['receipt_show_logo']) ? '1' : '0',
            'receipt_show_cashier' => isset($_POST['receipt_show_cashier']) ? '1' : '0'
        ];
        
        foreach ($settings as $key => $value) {
            $stmt = $conn->prepare("INSERT INTO store_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->bind_param("sss", $key, $value, $value);
            $stmt->execute();
        }
        
        // Handle logo upload
        if (isset($_FILES['store_logo']) && $_FILES['store_logo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['store_logo']['name'], PATHINFO_EXTENSION);
            $new_filename = 'logo_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['store_logo']['tmp_name'], $upload_path)) {
                $stmt = $conn->prepare("INSERT INTO store_settings (setting_key, setting_value, setting_type) VALUES ('store_logo', ?, 'image') ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->bind_param("ss", $upload_path, $upload_path);
                $stmt->execute();
            }
        }
        
        $message = "Store settings updated successfully!";
        $message_type = "success";
    }
    
    // GCash QR Code Upload
    if ($action === 'upload_gcash_qr') {
        // Require admin to enter their password to authorize upload
        $confirm_password = $_POST['confirm_password'] ?? '';
        if (empty($confirm_password)) {
            $message = "Please enter your password to authorize the upload.";
            $message_type = "danger";
        } else {
            // Verify admin password
            $admin_id = $_SESSION['user_id'] ?? null;
            if (!$admin_id) {
                $message = "Authentication error.";
                $message_type = "danger";
            } else {
                $up = $conn->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
                $up->bind_param('i', $admin_id);
                $up->execute();
                $urow = $up->get_result()->fetch_assoc();
                if (!$urow || !password_verify($confirm_password, $urow['password'])) {
                    $message = "Password incorrect. Upload cancelled.";
                    $message_type = "danger";
                } else {
                    if (isset($_FILES['gcash_qr']) && $_FILES['gcash_qr']['error'] === UPLOAD_ERR_OK) {
                        // enforce max size 5MB
                        $maxBytes = 5 * 1024 * 1024;
                        if ($_FILES['gcash_qr']['size'] > $maxBytes) {
                            $message = "File too large. Maximum allowed size is 5MB.";
                            $message_type = "danger";
                        } else {
                            $upload_dir_fs = __DIR__ . '/uploads/qrcodes/';
                            $upload_dir_db = 'uploads/qrcodes/';
                            if (!is_dir($upload_dir_fs)) {
                                mkdir($upload_dir_fs, 0777, true);
                            }

                            $file_extension = pathinfo($_FILES['gcash_qr']['name'], PATHINFO_EXTENSION);
                            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

                            if (in_array(strtolower($file_extension), $allowed_extensions)) {
                                $new_filename = 'gcash_qr_' . time() . '.' . $file_extension;
                                $fs_path = $upload_dir_fs . $new_filename;
                                $db_path = $upload_dir_db . $new_filename;

                                if (move_uploaded_file($_FILES['gcash_qr']['tmp_name'], $fs_path)) {
                                    // Insert or update in payment_qrcodes table (use SELECT then UPDATE/INSERT to avoid relying on unique key)
                                    $chk = $conn->prepare("SELECT id FROM payment_qrcodes WHERE payment_method = 'gcash' LIMIT 1");
                                    $chk->execute();
                                    $res = $chk->get_result();
                                    if ($res && $row = $res->fetch_assoc()) {
                                        $stmt = $conn->prepare("UPDATE payment_qrcodes SET qr_code_path = ?, is_active = 1, updated_at = NOW() WHERE id = ?");
                                        $stmt->bind_param("si", $db_path, $row['id']);
                                    } else {
                                        $stmt = $conn->prepare("INSERT INTO payment_qrcodes (payment_method, qr_code_path, description, is_active, created_at) VALUES ('gcash', ?, 'GCash Payment QR Code', 1, NOW())");
                                        $stmt->bind_param("s", $db_path);
                                    }
                                    $stmt->execute();

                                    // set file permissions
                                    @chmod($fs_path, 0644);

                                    $message = "GCash QR code uploaded successfully!";
                                    $message_type = "success";
                                } else {
                                    $message = "Error uploading QR code!";
                                    $message_type = "danger";
                                }
                            } else {
                                $message = "Invalid file type! Only JPG, PNG, and GIF allowed.";
                                $message_type = "danger";
                            }
                        }
                    } else {
                        $message = "Please select a QR code image to upload.";
                        $message_type = "warning";
                    }
                }
            }
        }
    }
    
    // Delete GCash QR Code
    if ($action === 'delete_gcash_qr') {
        $stmt = $conn->prepare("SELECT qr_code_path FROM payment_qrcodes WHERE payment_method = 'gcash' AND is_active = 1 LIMIT 1");
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            if (file_exists($row['qr_code_path'])) {
                unlink($row['qr_code_path']);
            }
            
            $stmt = $conn->prepare("UPDATE payment_qrcodes SET qr_code_path = '', is_active = 0 WHERE payment_method = 'gcash'");
            $stmt->execute();
            
            $message = "GCash QR code deleted successfully!";
            $message_type = "success";
        }
    }
    
    // User Management Actions (from user_management.php)
    if ($action === 'create_user') {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $role = $_POST['role'];
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $status = $_POST['status'];
        
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, first_name, last_name, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $username, $email, $hashed_password, $role, $first_name, $last_name, $status);
        
        if ($stmt->execute()) {
            $message = "User created successfully!";
            $message_type = "success";
        } else {
            $message = "Error creating user: " . $conn->error;
            $message_type = "danger";
        }
    }
    
    if ($action === 'update_user') {
        $user_id = $_POST['user_id'];
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $status = $_POST['status'];
        
        $stmt = $conn->prepare("UPDATE users SET email=?, role=?, first_name=?, last_name=?, status=? WHERE id=?");
        $stmt->bind_param("sssssi", $email, $role, $first_name, $last_name, $status, $user_id);
        
        if ($stmt->execute()) {
            $message = "User updated successfully!";
            $message_type = "success";
        } else {
            $message = "Error updating user.";
            $message_type = "danger";
        }
    }
    
    if ($action === 'reset_password') {
        $user_id = $_POST['user_id'];
        $new_password = $_POST['new_password'];
        
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($stmt->execute()) {
            $message = "Password reset successfully!";
            $message_type = "success";
        } else {
            $message = "Error resetting password.";
            $message_type = "danger";
        }
    }
    
    if ($action === 'delete_user') {
        $user_id = $_POST['user_id'];
        
        $check_stmt = $conn->prepare("SELECT COUNT(*) as order_count FROM orders WHERE user_id = ?");
        $check_stmt->bind_param("i", $user_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result()->fetch_assoc();
        
        if ($result['order_count'] > 0) {
            $message = "Cannot delete user with existing orders. Set status to 'inactive' instead.";
            $message_type = "warning";
        } else {
            // Also check for messages referencing this user (sender or recipient)
            $msg_check = $conn->prepare("SELECT COUNT(*) as msg_count FROM messages WHERE sender_id = ? OR recipient_id = ?");
            $msg_check->bind_param("ii", $user_id, $user_id);
            $msg_check->execute();
            $msg_result = $msg_check->get_result()->fetch_assoc();

            if ($msg_result && $msg_result['msg_count'] > 0) {
                $message = "Cannot delete user: there are messages referencing this account. Please reassign or delete their messages first, or set the user to 'inactive'.";
                $message_type = "warning";
            } else {
                $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
                $stmt->bind_param("i", $user_id);

                if ($stmt->execute()) {
                    $message = "User deleted successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error deleting user: " . $conn->error;
                    $message_type = "danger";
                }
            }
        }
    }
    
    // Admin Notification Email Settings
    if ($action === 'update_admin_email') {
        $admin_email = trim($_POST['admin_notification_email'] ?? '');
        
        if ($admin_email !== '' && !filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
            $message = "Please enter a valid email address for Admin notification email.";
            $message_type = "danger";
        } else {
            // Upsert into store_settings (use the same column names used elsewhere)
            $stmt = $conn->prepare("INSERT INTO store_settings (setting_key, setting_value) VALUES ('admin_notification_email', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->bind_param("ss", $admin_email, $admin_email);
            $stmt->execute();
            
            $message = "Admin notification email updated.";
            $message_type = "success";
        }
    }

    // Mobile App Upload
    if ($action === 'upload_mobile_app') {
        $file = $_FILES['mobile_app_zip'] ?? null;

        // Check for upload errors
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $message = 'Upload failed: ' . ($file ? $file['error'] : 'No file uploaded');
            $message_type = "danger";
        } else {
            // Check file type
            $allowedTypes = ['application/zip', 'application/x-zip-compressed'];
            if (!in_array($file['type'], $allowedTypes)) {
                $message = 'Only ZIP files are allowed.';
                $message_type = "danger";
            } else {
                // Check file size (limit to 1GB)
                if ($file['size'] > 1024 * 1024 * 1024) {
                    $message = 'File size must be less than 1GB.';
                    $message_type = "danger";
                } else {
                    // Move file to app root directory consistently
                    $fsTarget = __DIR__ . '/mobile-app.zip';
                    if (move_uploaded_file($file['tmp_name'], $fsTarget)) {
                        // Insert record into mobile_app_uploads table
                        $filename = 'mobile-app.zip';
                        // Store site-relative path (relative to this app's web root)
                        $filepath = 'mobile-app.zip';
                        $uploaded_by = $_SESSION['user_id'];

                        $stmt = $conn->prepare("INSERT INTO mobile_app_uploads (filename, filepath, uploaded_by) VALUES (?, ?, ?)");
                        $stmt->bind_param("ssi", $filename, $filepath, $uploaded_by);
                        $stmt->execute();

                        // set permissions
                        @chmod($fsTarget, 0644);

                        $message = 'Mobile app uploaded successfully!';
                        $message_type = "success";
                    } else {
                        $message = 'Failed to save the uploaded file.';
                        $message_type = "danger";
                    }
                }
            }
        }
    }

    // SMTP Settings Update
    if ($action === 'update_smtp_settings') {
        $smtp_host = trim($_POST['smtp_host'] ?? '');
        $smtp_user = trim($_POST['smtp_user'] ?? '');
        $smtp_pass = trim($_POST['smtp_pass'] ?? '');
        $smtp_port = trim($_POST['smtp_port'] ?? '');
        $smtp_secure = trim($_POST['smtp_secure'] ?? '');

        // Basic validation
        if ($smtp_host !== '' && !filter_var('http://' . $smtp_host, FILTER_VALIDATE_URL) && strpos($smtp_host, '.') === false) {
            // Allow host like smtp.gmail.com - no strict validation here
        }

        // Upsert each setting
        $smtp_settings = [
            'smtp_host' => $smtp_host,
            'smtp_user' => $smtp_user,
            'smtp_pass' => $smtp_pass,
            'smtp_port' => $smtp_port,
            'smtp_secure' => $smtp_secure
        ];

        foreach ($smtp_settings as $k => $v) {
            $stmt = $conn->prepare("INSERT INTO store_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->bind_param("sss", $k, $v, $v);
            $stmt->execute();
        }

        $message = "SMTP settings updated.";
        $message_type = "success";
    }
}

// Fetch store settings
$settings_query = $conn->query("SELECT setting_key, setting_value FROM store_settings");
$store_settings = [];
while ($row = $settings_query->fetch_assoc()) {
    $store_settings[$row['setting_key']] = $row['setting_value'];
}

// Expose current admin notification email for the settings form
$current_admin_notification_email = $store_settings['admin_notification_email'] ?? '';

// Fetch GCash QR Code
$gcash_qr_query = $conn->query("SELECT qr_code_path FROM payment_qrcodes WHERE payment_method = 'gcash' AND is_active = 1 LIMIT 1");
$gcash_qr = $gcash_qr_query->fetch_assoc();
$gcash_qr_path = $gcash_qr['qr_code_path'] ?? '';

// Fetch users
$users = $conn->query("SELECT u.*, 
    (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as total_orders,
    (SELECT SUM(total_amount) FROM orders WHERE user_id = u.id AND status = 'completed') as total_sales
    FROM users u ORDER BY u.created_at DESC")->fetch_all(MYSQLI_ASSOC);

// Get user statistics
$user_stats = $conn->query("SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_count,
    SUM(CASE WHEN role = 'staff' THEN 1 ELSE 0 END) as staff_count,
    SUM(CASE WHEN role = 'cashier' THEN 1 ELSE 0 END) as cashier_count,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count
FROM users")->fetch_assoc();

// Shift queries removed: shifts, statistics and assignment dropdown data are no longer populated
// (Shift management functionality has been removed)

ob_start();
?>

<style>
    .nav-tabs .nav-link {
        color: #6c757d;
        border: none;
        border-bottom: 3px solid transparent;
        font-weight: 500;
    }
    .nav-tabs .nav-link.active {
        color: #dc3545;
        border-bottom: 3px solid #dc3545;
        background: transparent;
    }
    .settings-section {
        background: white;
        border-radius: 10px;
        padding: 2rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    .settings-section h5 {
        color: #dc3545;
        margin-bottom: 1.5rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid #f8f9fa;
    }
    .logo-preview {
        max-width: 200px;
        max-height: 100px;
        margin-top: 10px;
    }
</style>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Page Header -->
<div class="page-header">
    <h4 class="mb-0"><i class="fas fa-cog me-2"></i>System Settings</h4>
    <p class="text-muted mb-0">Manage your store configuration and users</p>
</div>

<!-- Tabs Navigation -->
<ul class="nav nav-tabs mb-4" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="store-tab" data-bs-toggle="tab" data-bs-target="#store-config" type="button" role="tab">
            <i class="fas fa-store me-2"></i>Store Configuration
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="users-tab" data-bs-toggle="tab" data-bs-target="#user-management" type="button" role="tab">
            <i class="fas fa-users-cog me-2"></i>User Management
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="backup-tab" data-bs-toggle="tab" data-bs-target="#backup-recovery" type="button" role="tab">
            <i class="fas fa-database me-2"></i>Backup & Recovery
        </button>
    </li>
</ul>

<!-- Tab Content -->
<div class="tab-content">
    <!-- Store Configuration Tab -->
    <div class="tab-pane fade show active" id="store-config" role="tabpanel">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update_store_config">
            
            <!-- Basic Information -->
            <div class="settings-section">
                <h5><i class="fas fa-info-circle me-2"></i>Basic Information</h5>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Store Name *</label>
                        <input type="text" class="form-control" name="store_name" value="<?php echo htmlspecialchars($store_settings['store_name'] ?? 'PointShift POS'); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Branch Name</label>
                        <input type="text" class="form-control" name="store_branch" value="<?php echo htmlspecialchars($store_settings['store_branch'] ?? ''); ?>">
                    </div>
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Store Address *</label>
                        <textarea class="form-control" name="store_address" rows="2" required><?php echo htmlspecialchars($store_settings['store_address'] ?? ''); ?></textarea>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Phone Number *</label>
                        <input type="text" class="form-control" name="store_phone" value="<?php echo htmlspecialchars($store_settings['store_phone'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Email Address *</label>
                        <input type="email" class="form-control" name="store_email" value="<?php echo htmlspecialchars($store_settings['store_email'] ?? ''); ?>" required>
                    </div>
                </div>
            </div>
            
            <!-- Business Hours -->
            <div class="settings-section">
                <h5><i class="fas fa-clock me-2"></i>Business Hours</h5>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Opening Time *</label>
                        <input type="time" class="form-control" name="business_hours_open" value="<?php echo htmlspecialchars($store_settings['business_hours_open'] ?? '08:00'); ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Closing Time *</label>
                        <input type="time" class="form-control" name="business_hours_close" value="<?php echo htmlspecialchars($store_settings['business_hours_close'] ?? '20:00'); ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Business Days</label>
                        <input type="text" class="form-control" name="business_days" value="<?php echo htmlspecialchars($store_settings['business_days'] ?? 'Monday to Sunday'); ?>" placeholder="e.g., Monday to Sunday">
                    </div>
                </div>
            </div>
            
            <!-- Store Logo -->
            <div class="settings-section">
                <h5><i class="fas fa-image me-2"></i>Store Logo</h5>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Upload Logo</label>
                        <input type="file" class="form-control" name="store_logo" accept="image/*">
                        <small class="text-muted">Recommended size: 300x100px (PNG or JPG)</small>
                        <?php if (!empty($store_settings['store_logo'])): ?>
                            <div class="mt-2">
                                <p class="mb-1"><strong>Current Logo:</strong></p>
                                <img src="<?php echo htmlspecialchars($store_settings['store_logo']); ?>" alt="Store Logo" class="logo-preview img-thumbnail">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Receipt Configuration -->
            <div class="settings-section">
                <h5><i class="fas fa-receipt me-2"></i>Receipt Configuration</h5>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Receipt Header Text</label>
                        <input type="text" class="form-control" name="receipt_header" value="<?php echo htmlspecialchars($store_settings['receipt_header'] ?? ''); ?>" placeholder="e.g., Thank you for your purchase!">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Receipt Footer Text</label>
                        <input type="text" class="form-control" name="receipt_footer" value="<?php echo htmlspecialchars($store_settings['receipt_footer'] ?? ''); ?>" placeholder="e.g., Please come again!">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Tax Rate (%)</label>
                        <input type="number" step="0.01" class="form-control" name="tax_rate" value="<?php echo htmlspecialchars($store_settings['tax_rate'] ?? '12'); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Currency Symbol</label>
                        <input type="text" class="form-control" name="currency_symbol" value="<?php echo htmlspecialchars($store_settings['currency_symbol'] ?? '₱'); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Receipt Options</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="receipt_show_logo" id="receipt_show_logo" <?php echo ($store_settings['receipt_show_logo'] ?? '1') == '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="receipt_show_logo">Show Logo on Receipt</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="receipt_show_cashier" id="receipt_show_cashier" <?php echo ($store_settings['receipt_show_cashier'] ?? '1') == '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="receipt_show_cashier">Show Cashier Name on Receipt</label>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="text-end">
                <button type="submit" class="btn btn-danger btn-lg">
                    <i class="fas fa-save me-2"></i>Save Store Settings
                </button>
            </div>
        </form>
        
        <!-- GCash QR Code Section -->
        <div class="card mt-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-qrcode me-2"></i>GCash Payment QR Code</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">Upload a GCash QR code that will be displayed when customers select GCash as payment method in POS.</p>
                
                <?php if (!empty($gcash_qr_path) && file_exists($gcash_qr_path)): ?>
                    <!-- Current QR Code Display -->
                    <div class="alert alert-success">
                        <h6><i class="fas fa-check-circle me-2"></i>QR Code Uploaded</h6>
                        <div class="text-center my-3">
                            <img src="<?php echo htmlspecialchars($gcash_qr_path); ?>" alt="GCash QR Code" class="img-thumbnail" style="max-width: 300px; max-height: 300px;">
                        </div>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="delete_gcash_qr">
                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete the GCash QR code?')">
                                <i class="fas fa-trash me-1"></i>Delete QR Code
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>No GCash QR code uploaded yet.
                    </div>
                <?php endif; ?>
                
                <!-- Upload Form -->
                <form method="POST" enctype="multipart/form-data" class="mt-3">
                    <input type="hidden" name="action" value="upload_gcash_qr">
                    <div class="row">
                        <div class="col-md-8">
                            <input type="file" class="form-control" name="gcash_qr" accept="image/*" required>
                            <small class="form-text text-muted">Accepted formats: JPG, PNG, GIF. Max size: 5MB</small>
                            <div class="mt-2">
                                <label class="form-label">Confirm Admin Password</label>
                                <input type="password" class="form-control" name="confirm_password" placeholder="Enter your password to authorize upload" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-upload me-2"></i><?php echo !empty($gcash_qr_path) ? 'Replace' : 'Upload'; ?> QR Code
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Mobile App Upload Section -->
        <div class="card mt-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-mobile-alt me-2"></i>Mobile App Upload</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">Upload a ZIP file containing the mobile app build. This will be saved as mobile-app.zip in the root directory.</p>

                <?php if (file_exists(__DIR__ . '/mobile-app.zip')): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>Mobile app uploaded successfully. File: mobile-app.zip
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>No mobile app uploaded yet.
                    </div>
                <?php endif; ?>

                <!-- Upload Form -->
                <form method="POST" enctype="multipart/form-data" class="mt-3">
                    <input type="hidden" name="action" value="upload_mobile_app">
                    <div class="row">
                        <div class="col-md-8">
                            <input type="file" class="form-control" name="mobile_app_zip" accept=".zip" required>
                            <small class="form-text text-muted">Accepted format: ZIP. Max size: 1GB</small>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-info">
                                <i class="fas fa-upload me-2"></i><?php echo file_exists('../mobile-app.zip') ? 'Replace' : 'Upload'; ?> Mobile App
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Admin Notification Email Settings -->
<div class="settings-section">
    <h5><i class="fas fa-envelope me-2"></i>Admin Notification Email</h5>
    <form method="POST" class="mb-4">
        <input type="hidden" name="action" value="update_admin_email">
        <div class="mb-3">
            <label class="form-label">Admin Notification Email (Gmail)</label>
            <input type="email" class="form-control" name="admin_notification_email" value="<?php echo htmlspecialchars($current_admin_notification_email); ?>" placeholder="admin@gmail.com">
            <small class="form-text text-muted">Email that staff and cashier messages should be sent to (Gmail recommended with app password).</small>
        </div>
        <button type="submit" name="save_settings" class="btn btn-primary">Save Settings</button>
    </form>

    <h5 class="mt-4"><i class="fas fa-server me-2"></i>SMTP Configuration</h5>
    <form method="POST">
        <input type="hidden" name="action" value="update_smtp_settings">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">SMTP Host</label>
                <input type="text" class="form-control" name="smtp_host" value="<?php echo htmlspecialchars($store_settings['smtp_host'] ?? 'smtp.gmail.com'); ?>" placeholder="smtp.gmail.com">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">SMTP User (email)</label>
                <input type="email" class="form-control" name="smtp_user" value="<?php echo htmlspecialchars($store_settings['smtp_user'] ?? ''); ?>" placeholder="you@gmail.com">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">SMTP Password / App Password</label>
                <input type="text" class="form-control" name="smtp_pass" value="<?php echo htmlspecialchars($store_settings['smtp_pass'] ?? ''); ?>" placeholder="app password or SMTP password">
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">SMTP Port</label>
                <input type="number" class="form-control" name="smtp_port" value="<?php echo htmlspecialchars($store_settings['smtp_port'] ?? '587'); ?>">
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">Encryption</label>
                <select class="form-select" name="smtp_secure">
                    <option value="" <?php echo (empty($store_settings['smtp_secure']) ? 'selected' : ''); ?>>None</option>
                    <option value="tls" <?php echo (($store_settings['smtp_secure'] ?? '') === 'tls' ? 'selected' : ''); ?>>TLS</option>
                    <option value="ssl" <?php echo (($store_settings['smtp_secure'] ?? '') === 'ssl' ? 'selected' : ''); ?>>SSL</option>
                </select>
            </div>
        </div>
        <div class="mt-2">
            <small class="text-muted">Use Gmail SMTP: <code>smtp.gmail.com</code> with port <code>587</code> (TLS) and an App Password (if using 2FA). For Outlook/Office365 use <code>smtp.office365.com</code> port <code>587</code> (TLS).</small>
        </div>
        <div class="mt-3">
            <button type="submit" class="btn btn-success">Save SMTP Settings</button>
        </div>
    </form>
    <div class="mt-3">
        <div class="input-group mb-2" style="max-width:420px;">
            <input type="email" id="test_email_input" class="form-control" placeholder="Optional: test recipient email (defaults to admin email)">
            <button id="send_test_email_btn" class="btn btn-outline-primary">Send test email</button>
            <button id="send_test_email_debug_btn" class="btn btn-outline-danger ms-2">Send test (debug)</button>
        </div>
        <div id="send_test_result"></div>
    </div>
    </div>
    </div>
    
    <!-- User Management Tab -->
    <div class="tab-pane fade" id="user-management" role="tabpanel">
        <!-- User Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Total Users</h6>
                            <h3 class="mb-0"><?php echo number_format($user_stats['total_users']); ?></h3>
                        </div>
                        <div class="stats-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Admins</h6>
                            <h3 class="mb-0"><?php echo number_format($user_stats['admin_count']); ?></h3>
                        </div>
                        <div class="stats-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <i class="fas fa-user-shield"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Staff</h6>
                            <h3 class="mb-0"><?php echo number_format($user_stats['staff_count']); ?></h3>
                        </div>
                        <div class="stats-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <i class="fas fa-user-tie"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Cashiers</h6>
                            <h3 class="mb-0"><?php echo number_format($user_stats['cashier_count']); ?></h3>
                        </div>
                        <div class="stats-icon" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                            <i class="fas fa-cash-register"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Add User Button -->
        <div class="mb-3">
            <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="fas fa-user-plus me-2"></i>Add New User
            </button>
        </div>
        
        <!-- Users Table -->
        <div class="content-card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>All Users</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Orders</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $user['role'] == 'admin' ? 'bg-danger' : ($user['role'] == 'staff' ? 'bg-primary' : 'bg-info'); ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $user['status'] == 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                            <?php echo ucfirst($user['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo number_format($user['total_orders']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button class="btn btn-sm btn-outline-primary" onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-warning" onclick="resetPassword(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                <i class="fas fa-key"></i>
                                            </button>
                                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Backup & Recovery Tab -->
    <div class="tab-pane fade" id="backup-recovery" role="tabpanel">
        <div class="content-card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-database me-2"></i>Backup & Recovery</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">Manage database backups and restores. The Backup & Recovery page is embedded below.</p>
                <div style="width:100%; height:800px; border:1px solid #e9ecef; border-radius:6px; overflow:hidden;">
                    <iframe src="admin/backup_recovery.php?embedded=1" style="width:100%; height:100%; border:0;" title="Backup & Recovery"></iframe>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Shift Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_user">
                    <div class="mb-3">
                        <label class="form-label">Username *</label>
                        <input type="text" class="form-control" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password *</label>
                        <input type="password" class="form-control" name="password" required minlength="6">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">First Name *</label>
                            <input type="text" class="form-control" name="first_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Last Name *</label>
                            <input type="text" class="form-control" name="last_name" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role *</label>
                            <select class="form-select" name="role" required>
                                <option value="cashier">Cashier</option>
                                <option value="staff">Staff</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status *</label>
                            <select class="form-select" name="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" id="edit_username" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" class="form-control" name="email" id="edit_email" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">First Name *</label>
                            <input type="text" class="form-control" name="first_name" id="edit_first_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Last Name *</label>
                            <input type="text" class="form-control" name="last_name" id="edit_last_name" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role *</label>
                            <select class="form-select" name="role" id="edit_role" required>
                                <option value="cashier">Cashier</option>
                                <option value="staff">Staff</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status *</label>
                            <select class="form-select" name="status" id="edit_status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-key me-2"></i>Reset Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="user_id" id="reset_user_id">
                    <p>Reset password for user: <strong id="reset_username"></strong></p>
                    <div class="mb-3">
                        <label class="form-label">New Password *</label>
                        <input type="password" class="form-control" name="new_password" required minlength="6">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Reset Password</button>
                </div>
            </form>
        </div>
    </div>
</div>



<script>
function editUser(user) {
    document.getElementById('edit_user_id').value = user.id;
    document.getElementById('edit_username').value = user.username;
    document.getElementById('edit_email').value = user.email;
    document.getElementById('edit_first_name').value = user.first_name;
    document.getElementById('edit_last_name').value = user.last_name;
    document.getElementById('edit_role').value = user.role;
    document.getElementById('edit_status').value = user.status;
    
    const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
    modal.show();
}

function resetPassword(userId, username) {
    document.getElementById('reset_user_id').value = userId;
    document.getElementById('reset_username').textContent = username;
    
    const modal = new bootstrap.Modal(document.getElementById('resetPasswordModal'));
    modal.show();
}

function deleteUser(userId, username) {
    if (confirm(`Are you sure you want to delete user "${username}"? This action cannot be undone.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_user">
            <input type="hidden" name="user_id" value="${userId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Shift Management Functions
function editShift(shift) {
    document.getElementById('edit_shift_id').value = shift.id;
    document.getElementById('edit_shift_name').value = shift.shift_name;
    document.getElementById('edit_shift_date').value = shift.shift_date;
    document.getElementById('edit_start_time').value = shift.start_time;
    document.getElementById('edit_end_time').value = shift.end_time;
    document.getElementById('edit_location').value = shift.location || '';
    document.getElementById('edit_max_employees').value = shift.max_employees;
    document.getElementById('edit_status').value = shift.status;
    document.getElementById('edit_description').value = shift.description || '';
    
    const modal = new bootstrap.Modal(document.getElementById('editShiftModal'));
    modal.show();
}

function deleteShift(shiftId, shiftName) {
    if (confirm(`Are you sure you want to delete shift "${shiftName}"? This will also remove all employee assignments. This action cannot be undone.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_shift">
            <input type="hidden" name="shift_id" value="${shiftId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function assignEmployees(shiftId, shiftName) {
    document.getElementById('assign_shift_id').value = shiftId;
    document.getElementById('assign_shift_name').textContent = shiftName;
    
    // Clear all checkboxes first
    document.querySelectorAll('.employee-checkbox').forEach(cb => cb.checked = false);
    
    // Fetch currently assigned employees via AJAX
    fetch(`get_shift_assignments.php?shift_id=${shiftId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                data.user_ids.forEach(userId => {
                    const checkbox = document.getElementById(`user_${userId}`);
                    if (checkbox) checkbox.checked = true;
                });
            }
        })
        .catch(error => console.error('Error fetching assignments:', error));
    
    const modal = new bootstrap.Modal(document.getElementById('assignEmployeesModal'));
    modal.show();
}

// Keep the active tab after form submission
if (window.location.hash) {
    const hash = window.location.hash;
    const tab = document.querySelector(`button[data-bs-target="${hash}"]`);
    if (tab) {
        const bsTab = new bootstrap.Tab(tab);
        bsTab.show();
    }
}
</script>
<script>
document.addEventListener('DOMContentLoaded', function(){
    var btn = document.getElementById('send_test_email_btn');
    if (!btn) return;

    btn.addEventListener('click', function(e){
        e.preventDefault();
        var input = document.getElementById('test_email_input');
        var email = input ? input.value.trim() : '';
        btn.disabled = true;
        btn.innerText = 'Sending...';

        fetch('send_test_email.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'test_email=' + encodeURIComponent(email)
        }).then(function(res){
            return res.json();
        }).then(function(data){
            var out = document.getElementById('send_test_result');
            if (data.success) {
                out.innerHTML = '<div class="alert alert-success mt-2">' + data.message + '</div>';
            } else {
                out.innerHTML = '<div class="alert alert-danger mt-2">' + data.message + '</div>';
            }
        }).catch(function(err){
            var out = document.getElementById('send_test_result');
            out.innerHTML = '<div class="alert alert-danger mt-2">Error sending test request.</div>';
        }).finally(function(){
            btn.disabled = false;
            btn.innerText = 'Send test email';
        });
    });
    var dbgBtn = document.getElementById('send_test_email_debug_btn');
    if (dbgBtn) {
        dbgBtn.addEventListener('click', function(e){
            e.preventDefault();
            var input = document.getElementById('test_email_input');
            var email = input ? input.value.trim() : '';
            dbgBtn.disabled = true;
            dbgBtn.innerText = 'Sending debug...';

            fetch('send_test_email_debug.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'test_email=' + encodeURIComponent(email)
            }).then(function(res){
                return res.json();
            }).then(function(data){
                var out = document.getElementById('send_test_result');
                var html = '';
                if (data.success) {
                    html += '<div class="alert alert-success mt-2">' + data.message + '</div>';
                } else {
                    html += '<div class="alert alert-danger mt-2">' + data.message + '</div>';
                }
                if (data.debug) {
                    html += '<pre class="mt-2" style="white-space:pre-wrap; background:#f8f9fa; padding:10px; border-radius:6px; max-height:300px; overflow:auto;">' + htmlspecialchars(data.debug) + '</pre>';
                }
                out.innerHTML = html;
            }).catch(function(err){
                var out = document.getElementById('send_test_result');
                out.innerHTML = '<div class="alert alert-danger mt-2">Error sending debug request.</div>';
            }).finally(function(){
                dbgBtn.disabled = false;
                dbgBtn.innerText = 'Send test (debug)';
            });
        });
    }
});

function htmlspecialchars(str) {
    if (!str) return '';
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
</script>

<?php
$content = ob_get_clean();
include 'layout.php';
?>
