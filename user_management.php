<?php
require_once 'config.php';
requireLogin();
requireAdmin();

// Update last_activity for current user
$stmt = $conn->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();

$page_title = "User Management";

// Handle user actions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $role = $_POST['role'];
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $status = $_POST['status'];
        
        // Hash password
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
    
    if ($action === 'update') {
        $user_id = $_POST['user_id'];
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $status = $_POST['status'];

        // Check if username is already taken by another user
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $check_stmt->bind_param("si", $username, $user_id);
        $check_stmt->execute();
        $existing_user = $check_stmt->get_result()->fetch_assoc();

        if ($existing_user) {
            $message = "Username already exists. Please choose a different username.";
            $message_type = "danger";
        } elseif (empty($username)) {
            $message = "Username cannot be empty.";
            $message_type = "danger";
        } else {
            $stmt = $conn->prepare("UPDATE users SET username=?, email=?, role=?, first_name=?, last_name=?, status=? WHERE id=?");
            $stmt->bind_param("ssssssi", $username, $email, $role, $first_name, $last_name, $status, $user_id);

            if ($stmt->execute()) {
                $message = "User updated successfully!";
                $message_type = "success";
            } else {
                $message = "Error updating user: " . $conn->error;
                $message_type = "danger";
            }
        }
    }
    
    if ($action === 'approve') {
        $user_id = $_POST['user_id'];
        try {
            // Fetch user info
            $ucheck = $conn->prepare("SELECT username, email, first_name, last_name FROM users WHERE id = ? LIMIT 1");
            $ucheck->bind_param('i', $user_id);
            $ucheck->execute();
            $urow = $ucheck->get_result()->fetch_assoc();

            if (!$urow) {
                $message = "User not found.";
                $message_type = "danger";
            } else {
                // Generate verification code and set account active (but keep email unverified until user confirms)
                $verification_code = strval(mt_rand(100000, 999999));
                $verification_expires = date('Y-m-d H:i:s', time() + 3600);

                // Set user status to active but do NOT mark email_verified = 1; user must verify using the code sent
                $ustmt = $conn->prepare("UPDATE users SET status='active', email_verified = 0, email_verification_code = ?, email_verification_expires_at = ?, email_verified_at = NULL WHERE id = ?");
                $ustmt->bind_param('ssi', $verification_code, $verification_expires, $user_id);
                $updated = $ustmt->execute();

                // Send verification email to user (contains verification code and instructions)
                require_once __DIR__ . '/classes/Mailer.php';
                $display = trim(($urow['first_name'] ?? '') . ' ' . ($urow['last_name'] ?? '')) ?: ($urow['username'] ?? ('User ' . $user_id));
                $subject = "Your account for " . SITE_NAME . " has been approved";
                $verifyLink = SITE_URL . '/verify_email.php?user_id=' . $user_id;
                $body = "<p>Hi " . htmlspecialchars($display) . ",</p>";
                $body .= "<p>Your account request has been approved by an administrator. To complete your account setup, please verify your email address using the code below:</p>";
                $body .= "<p><strong>Verification code:</strong> " . htmlspecialchars($verification_code) . "</p>";
                $body .= "<p><a href='" . $verifyLink . "'>Click here to open the verification page</a> and enter the code. This code will expire in 1 hour.</p>";

                $email_sent = Mailer::sendEmail($urow['email'], $subject, $body, SITE_NAME, null);

                if ($updated) {
                    $message = $email_sent ? "User approved and verification code sent to the user." : "User approved (failed to send verification email).";
                    $message_type = $email_sent ? "success" : "warning";
                } else {
                    $message = "Error activating user: " . $conn->error;
                    $message_type = "danger";
                }

                // Create notification about approval
                try {
                    require_once __DIR__ . '/classes/Notification.php';
                    require_once __DIR__ . '/classes/Database.php';
                    $notifMsg = "Account approved: {$display} (" . ($urow['email'] ?? '') . ")";
                    $dbp = Database::getInstance()->getConnection();
                    Notification::create($dbp, $notifMsg, $email_sent ? 'success' : 'warning', null);
                } catch (Throwable $e) {
                    error_log('Notification error (approve): ' . $e->getMessage());
                }
            }
        } catch (Throwable $e) {
            $message = "Error processing approval: " . $e->getMessage();
            $message_type = "danger";
        }
    }

    if ($action === 'reject') {
        $user_id = $_POST['user_id'];
        // Mark rejected requests as inactive to keep historical data
        $stmt = $conn->prepare("UPDATE users SET status='inactive' WHERE id=?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $message = "User request has been rejected.";
            $message_type = "warning";
            // Create notification about rejection
            try {
                require_once __DIR__ . '/classes/Notification.php';
                require_once __DIR__ . '/classes/Database.php';
                $ucheck = $conn->prepare("SELECT username, email, first_name, last_name FROM users WHERE id = ? LIMIT 1");
                $ucheck->bind_param('i', $user_id);
                $ucheck->execute();
                $urow = $ucheck->get_result()->fetch_assoc();
                $display = trim(($urow['first_name'] ?? '') . ' ' . ($urow['last_name'] ?? '')) ?: ($urow['username'] ?? ('User ' . $user_id));
                $notifMsg = "Account request rejected: {$display} ({" . ($urow['email'] ?? '') . "})";
                $dbp = Database::getInstance()->getConnection();
                Notification::create($dbp, $notifMsg, 'warning', null);
            } catch (Throwable $e) {
                error_log('Notification error (reject): ' . $e->getMessage());
            }
        } else {
            $message = "Error rejecting user: " . $conn->error;
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
            $message = "Error resetting password: " . $conn->error;
            $message_type = "danger";
        }
    }
    
    if ($action === 'delete') {
        $user_id = $_POST['user_id'];
        
        // Check if user has orders
        $check_stmt = $conn->prepare("SELECT COUNT(*) as order_count FROM orders WHERE user_id = ?");
        $check_stmt->bind_param("i", $user_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result()->fetch_assoc();
        
        if ($result['order_count'] > 0) {
            $message = "Cannot delete user with existing orders. Set status to 'inactive' instead.";
            $message_type = "warning";
        } else {
            // Check if user has messages (sender or recipient) to avoid foreign key constraint errors
            $check_msgs = $conn->prepare("SELECT COUNT(*) as msg_count FROM messages WHERE sender_id = ? OR recipient_id = ?");
            $check_msgs->bind_param("ii", $user_id, $user_id);
            $check_msgs->execute();
            $msg_result = $check_msgs->get_result()->fetch_assoc();

            if ($msg_result && $msg_result['msg_count'] > 0) {
                $message = "Cannot delete user who has messages. Reassign or delete their messages first, or set status to 'inactive'.";
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
}

// Fetch all users
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role_filter'] ?? '';
$status_filter = strtolower($_GET['status_filter'] ?? '');

$query = "SELECT u.*, 
          (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as total_orders,
          (SELECT SUM(total_amount) FROM orders WHERE user_id = u.id AND status = 'completed') as total_sales
          FROM users u WHERE 1=1";

if ($search) {
    $query .= " AND (u.username LIKE '%$search%' OR u.email LIKE '%$search%' OR u.first_name LIKE '%$search%' OR u.last_name LIKE '%$search%')";
}

if ($role_filter) {
    $query .= " AND u.role = '$role_filter'";
}

if ($status_filter) {
    // Compare statuses case-insensitively
    $query .= " AND LOWER(u.status) = '$status_filter'";
}

$query .= " ORDER BY u.created_at DESC";

$users = $conn->query($query)->fetch_all(MYSQLI_ASSOC);

// Get statistics
$stats = $conn->query("SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_count,
    SUM(CASE WHEN role = 'staff' THEN 1 ELSE 0 END) as staff_count,
    SUM(CASE WHEN role = 'cashier' THEN 1 ELSE 0 END) as cashier_count,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count
FROM users")->fetch_assoc();

// Pending requests for quick admin visibility
$pending_count = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE LOWER(status)='pending'")->fetch_assoc()['cnt'] ?? 0;
$pending_users = [];
if ($pending_count > 0) {
    $res = $conn->query("SELECT id, username, email, first_name, last_name, created_at FROM users WHERE LOWER(status)='pending' ORDER BY created_at ASC");
    if ($res) {
        $pending_users = $res->fetch_all(MYSQLI_ASSOC);
    }
}

ob_start();
?>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="stats-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-muted mb-2">Total Users</h6>
                    <h3 class="mb-0"><?php echo number_format($stats['total_users']); ?></h3>
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
                    <h3 class="mb-0"><?php echo number_format($stats['admin_count']); ?></h3>
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
                    <h3 class="mb-0"><?php echo number_format($stats['staff_count']); ?></h3>
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
                    <h6 class="text-muted mb-2">Active Users</h6>
                    <h3 class="mb-0"><?php echo number_format($stats['active_count']); ?></h3>
                </div>
                <div class="stats-icon" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                    <i class="fas fa-user-check"></i>
                </div>
            </div>
        </div>
    </div>
</div>

            <?php if ($pending_count > 0): ?>
            <!-- Pending account requests quick view for admins -->
                <div class="content-card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-user-clock me-2"></i>Pending Account Requests (<?php echo $pending_count; ?>)</h5>
                        <a href="?status_filter=pending" class="btn btn-outline-danger btn-sm">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <?php foreach ($pending_users as $puser): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars(trim(($puser['first_name'] ?? '') . ' ' . ($puser['last_name'] ?? '')) ?: $puser['username']); ?></strong>
                                        <div class="small text-muted"><?php echo htmlspecialchars($puser['email']); ?> • <?php echo date('M d, Y', strtotime($puser['created_at'])); ?></div>
                                    </div>
                                    <div>
                                        <form method="POST" style="display:inline-block;margin:0 4px;">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="user_id" value="<?php echo $puser['id']; ?>">
                                            <button type="submit" class="btn btn-sm" title="Approve user" style="width:34px;height:34px;padding:6px;border:1px solid #28a745;color:#28a745;background:transparent;border-radius:4px;">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>

                                        <form method="POST" style="display:inline-block;margin:0 4px;" onsubmit="return confirm('Are you sure you want to reject this account request?');">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="user_id" value="<?php echo $puser['id']; ?>">
                                            <button type="submit" class="btn btn-sm" title="Reject user" style="width:34px;height:34px;padding:6px;border:1px solid #dc3545;color:#dc3545;background:transparent;border-radius:4px;">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Filter and Add User Section -->
<div class="content-card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter & Search</h5>
        <button class="btn btn-danger btn-custom" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="fas fa-user-plus me-2"></i>Add New User
        </button>
    </div>
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-4">
                <input type="text" class="form-control" name="search" placeholder="Search by name, username, or email..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-3">
                <select class="form-select" name="role_filter">
                    <option value="">All Roles</option>
                    <option value="admin" <?php echo $role_filter == 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="staff" <?php echo $role_filter == 'staff' ? 'selected' : ''; ?>>Staff</option>
                    <option value="cashier" <?php echo $role_filter == 'cashier' ? 'selected' : ''; ?>>Cashier</option>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="status_filter">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-danger w-100 btn-custom">
                    <i class="fas fa-search me-2"></i>Search
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Users Table -->
<div class="content-card">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-list me-2"></i>All Users (<?php echo count($users); ?>)</h5>
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
                        <th>Total Sales</th>
                        <th>Created</th>
                        <th>Last Active</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4">No users found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="badge <?php 
                                        echo $user['role'] == 'admin' ? 'bg-danger' : 
                                            ($user['role'] == 'staff' ? 'bg-primary' : 'bg-info'); 
                                    ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td>
                                        <?php
                                        $status = $user['status'];
                                        $badgeClass = 'bg-secondary';
                                        // Use case-insensitive checks for status
                                        if (strtolower($status) == 'active') $badgeClass = 'bg-success';
                                        if (strtolower($status) == 'pending') $badgeClass = 'bg-warning text-dark';
                                        if (strtolower($status) == 'inactive') $badgeClass = 'bg-secondary';
                                    ?>
                                    <span class="badge <?php echo $badgeClass; ?>">
                                        <?php echo ucfirst($status); ?>
                                    </span>
                                </td>
                                <td><?php echo number_format($user['total_orders']); ?></td>
                                <td>₱<?php echo number_format($user['total_sales'] ?? 0, 2); ?></td>
                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <?php
                                        $badgeClass = 'bg-secondary';
                                        $displayText = 'Never';
                                        if (!empty($user['last_activity'])) {
                                            $ts = strtotime($user['last_activity']);
                                            $displayText = 'Last seen: ' . date('M j, Y g:i A', $ts);
                                        }
                                    ?>
                                    <span class="badge status-badge <?php echo $badgeClass; ?>" data-user-id="<?php echo $user['id']; ?>" data-last-activity="<?php echo htmlspecialchars($user['last_activity'] ?? ''); ?>">
                                        <?php echo $displayText; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-sm btn-outline-primary" onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-warning" onclick="resetPassword(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                            <i class="fas fa-key"></i>
                                        </button>
                                        <?php if (strtolower($user['status']) == 'pending'): ?>
                                            <!-- Approve (check) -->
                                            <form method="POST" action="" style="display:inline-block; margin:0 4px;">
                                                <input type="hidden" name="action" value="approve">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="btn btn-sm" title="Approve user" style="width:34px;height:34px;padding:6px;border:1px solid #28a745;color:#28a745;background:transparent;border-radius:4px;">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>

                                            <!-- Reject (X) -->
                                            <form method="POST" action="" style="display:inline-block; margin:0 4px;" onsubmit="return confirmReject(event)">
                                                <input type="hidden" name="action" value="reject">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="btn btn-sm" title="Reject user" style="width:34px;height:34px;padding:6px;border:1px solid #dc3545;color:#dc3545;background:transparent;border-radius:4px;">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create">
                    
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
                        <div class="input-group">
                            <input type="password" class="form-control" name="password" id="add_password" required minlength="6" aria-label="Password">
                            <button class="btn btn-outline-secondary" type="button" id="toggleAddPassword" aria-label="Toggle password visibility" title="Show/Hide password">
                                <i class="fas fa-eye" id="toggleAddPasswordIcon"></i>
                            </button>
                        </div>
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
                                <option value="pending">Pending</option>
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
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Username *</label>
                        <input type="text" class="form-control" name="username" id="edit_username" required>
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
                                <option value="pending">Pending</option>
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
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="user_id" id="reset_user_id">
                    
                    <p>Reset password for user: <strong id="reset_username"></strong></p>
                    
                    <div class="mb-3">
                        <label class="form-label">New Password *</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="new_password" id="reset_new_password" required minlength="6" aria-label="New password">
                            <button class="btn btn-outline-secondary" type="button" id="toggleResetPassword" aria-label="Toggle password visibility" title="Show/Hide password">
                                <i class="fas fa-eye" id="toggleResetPasswordIcon"></i>
                            </button>
                        </div>
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
const currentUserId = <?php echo $_SESSION['user_id']; ?>;

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
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="user_id" value="${userId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function confirmReject(e) {
    if (!confirm('Are you sure you want to reject this account request?')) {
        e.preventDefault();
        return false;
    }
    return true;
}

// Real-time user status updates
function updateUserStatuses() {
    fetch('get_user_status.php')
        .then(response => response.json())
        .then(statuses => {
            // Update each user's status badge
            Object.keys(statuses).forEach(userId => {
                const badge = document.querySelector(`tr[data-user-id="${userId}"] .status-badge`);
                if (badge) {
                    const data = statuses[userId];
                    const displayText = data.status;
                    const badgeClass = data.online ? 'bg-success' : 'bg-secondary';
                    badge.className = `badge status-badge ${badgeClass}`;
                    badge.textContent = displayText;
                }
            });
        })
        .catch(error => console.error('Error updating user statuses:', error));
}

// Password toggle handlers for Add User and Reset Password modals
document.addEventListener('DOMContentLoaded', function(){
    // Add User password toggle
    var toggleAdd = document.getElementById('toggleAddPassword');
    var addInput = document.getElementById('add_password');
    var addIcon = document.getElementById('toggleAddPasswordIcon');
    if (toggleAdd && addInput) {
        toggleAdd.addEventListener('click', function(){
            if (addInput.type === 'password') {
                addInput.type = 'text';
                if (addIcon) { addIcon.classList.remove('fa-eye'); addIcon.classList.add('fa-eye-slash'); }
            } else {
                addInput.type = 'password';
                if (addIcon) { addIcon.classList.remove('fa-eye-slash'); addIcon.classList.add('fa-eye'); }
            }
        });
    }

    // Reset Password toggle
    var toggleReset = document.getElementById('toggleResetPassword');
    var resetInput = document.getElementById('reset_new_password');
    var resetIcon = document.getElementById('toggleResetPasswordIcon');
    if (toggleReset && resetInput) {
        toggleReset.addEventListener('click', function(){
            if (resetInput.type === 'password') {
                resetInput.type = 'text';
                if (resetIcon) { resetIcon.classList.remove('fa-eye'); resetIcon.classList.add('fa-eye-slash'); }
            } else {
                resetInput.type = 'password';
                if (resetIcon) { resetIcon.classList.remove('fa-eye-slash'); resetIcon.classList.add('fa-eye'); }
            }
        });
    }

    // Add data-user-id attributes to table rows for real-time updates
    document.querySelectorAll('tbody tr').forEach(row => {
        const userId = row.cells[0].textContent.trim();
        row.setAttribute('data-user-id', userId);
        const statusCell = row.querySelector('td:nth-child(10)');
        if (statusCell) {
            const badge = statusCell.querySelector('.badge');
            if (badge) {
                badge.classList.add('status-badge');
            }
        }
    });

    // Update statuses immediately and then every 1 second
    updateUserStatuses();
    setInterval(updateUserStatuses, 1000);
});
</script>

<?php
$content = ob_get_clean();
include 'layout.php';
?>
