<?php
require_once '../config.php';
User::requireLogin();

// Check if user is cashier
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'cashier') {
    header('Location: ../login.php');
    exit();
}

$page_title = "Account Settings";
$current_user_id = $_SESSION['user_id'];

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Update Profile
    if ($action === 'update_profile') {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);

        // Check if username is already taken by another user
        $check_username_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $check_username_stmt->bind_param("si", $username, $current_user_id);
        $check_username_stmt->execute();
        $username_result = $check_username_stmt->get_result();

        if ($username_result->num_rows > 0) {
            $message = "Username is already taken by another user.";
            $message_type = "danger";
        } else {
            // Check if email is already taken by another user
            $check_email_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check_email_stmt->bind_param("si", $email, $current_user_id);
            $check_email_stmt->execute();
            $email_result = $check_email_stmt->get_result();

            if ($email_result->num_rows > 0) {
                $message = "Email is already taken by another user.";
                $message_type = "danger";
            } else {
                $stmt = $conn->prepare("UPDATE users SET username=?, email=?, first_name=?, last_name=? WHERE id=?");
                $stmt->bind_param("ssssi", $username, $email, $first_name, $last_name, $current_user_id);

                if ($stmt->execute()) {
                    // Update session variables
                    $_SESSION['user_name'] = $first_name . ' ' . $last_name;
                    $message = "Profile updated successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error updating profile.";
                    $message_type = "danger";
                }
            }
        }
    }
    
    // Change Password
    if ($action === 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Verify current password
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $current_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if (!password_verify($current_password, $user['password'])) {
            $message = "Current password is incorrect.";
            $message_type = "danger";
        } elseif ($new_password !== $confirm_password) {
            $message = "New passwords do not match.";
            $message_type = "danger";
        } elseif (strlen($new_password) < 6) {
            $message = "New password must be at least 6 characters long.";
            $message_type = "danger";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
            $update_stmt->bind_param("si", $hashed_password, $current_user_id);
            
            if ($update_stmt->execute()) {
                $message = "Password changed successfully!";
                $message_type = "success";
            } else {
                $message = "Error changing password.";
                $message_type = "danger";
            }
        }
    }
}

// Fetch current user data
$stmt = $conn->prepare("SELECT username, email, first_name, last_name, role, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();

// My Shifts quick view removed per recent UX change

ob_start();
?>

<style>
    .account-settings-container {
        padding: 2rem;
        max-width: 1400px;
        margin: 0 auto;
        overflow-y: auto;
        height: calc(100vh - 70px);
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
    .profile-header {
        background: linear-gradient(135deg, #dc3545 0%, #b02a37 100%);
        color: white;
        padding: 2rem;
        border-radius: 10px;
        margin-bottom: 2rem;
    }
    .profile-avatar {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        background: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5rem;
        color: #dc3545;
        font-weight: bold;
        margin-bottom: 1rem;
    }
    .shift-mini-card {
        background: #f8f9fa;
        border-left: 4px solid #0d6efd;
        padding: 1rem;
        margin-bottom: 0.75rem;
        border-radius: 5px;
        transition: all 0.2s;
    }
    .shift-mini-card:hover {
        background: #e9ecef;
        transform: translateX(5px);
    }
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
</style>

<div class="account-settings-container">
<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Profile Header -->
<div class="profile-header">
    <div class="row align-items-center">
        <div class="col-md-auto text-center text-md-start">
            <div class="profile-avatar">
                <?php echo strtoupper(substr($user_data['first_name'], 0, 1) . substr($user_data['last_name'], 0, 1)); ?>
            </div>
        </div>
        <div class="col-md text-center text-md-start">
            <h3 class="mb-2"><?php echo htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']); ?></h3>
            <p class="mb-1"><i class="fas fa-user me-2"></i><?php echo htmlspecialchars($user_data['username']); ?></p>
            <p class="mb-1"><i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($user_data['email']); ?></p>
            <p class="mb-0"><i class="fas fa-id-badge me-2"></i><?php echo ucfirst($user_data['role']); ?> • Member since <?php echo date('M Y', strtotime($user_data['created_at'])); ?></p>
        </div>
    </div>
</div>

<!-- Tabs Navigation -->
<ul class="nav nav-tabs mb-4" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile-settings" type="button" role="tab">
            <i class="fas fa-user-edit me-2"></i>Profile Settings
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="password-tab" data-bs-toggle="tab" data-bs-target="#password-settings" type="button" role="tab">
            <i class="fas fa-key me-2"></i>Change Password
        </button>
    </li>
    <!-- My Shifts tab removed -->
</ul>

<!-- Tab Content -->
<div class="tab-content">
    <!-- Profile Settings Tab -->
    <div class="tab-pane fade show active" id="profile-settings" role="tabpanel">
        <div class="settings-section">
            <h5><i class="fas fa-user-edit me-2"></i>Update Profile Information</h5>
            <form method="POST">
                <input type="hidden" name="action" value="update_profile">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">First Name *</label>
                        <input type="text" class="form-control" name="first_name" value="<?php echo htmlspecialchars($user_data['first_name']); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Last Name *</label>
                        <input type="text" class="form-control" name="last_name" value="<?php echo htmlspecialchars($user_data['last_name']); ?>" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email Address *</label>
                    <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Username *</label>
                    <input type="text" class="form-control" name="username" value="<?php echo htmlspecialchars($user_data['username']); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Role</label>
                    <input type="text" class="form-control" value="<?php echo ucfirst($user_data['role']); ?>" readonly disabled>
                </div>
                <div class="text-end">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-save me-2"></i>Update Profile
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Change Password Tab -->
    <div class="tab-pane fade" id="password-settings" role="tabpanel">
        <div class="settings-section">
            <h5><i class="fas fa-key me-2"></i>Change Your Password</h5>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                For security reasons, you'll need to enter your current password to set a new one.
            </div>
            <form method="POST" id="passwordForm">
                <input type="hidden" name="action" value="change_password">
                <div class="mb-3">
                    <label class="form-label">Current Password *</label>
                    <div class="input-group">
                        <input type="password" class="form-control" name="current_password" id="current_password" required aria-label="Current password">
                        <button class="btn btn-outline-secondary" type="button" id="toggleCurrentPassword" aria-label="Toggle current password visibility" title="Show/Hide password">
                            <i class="fas fa-eye" id="toggleCurrentPasswordIcon"></i>
                        </button>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">New Password *</label>
                    <div class="input-group">
                        <input type="password" class="form-control" name="new_password" id="new_password" required minlength="6" aria-label="New password">
                        <button class="btn btn-outline-secondary" type="button" id="toggleNewPassword" aria-label="Toggle new password visibility" title="Show/Hide password">
                            <i class="fas fa-eye" id="toggleNewPasswordIcon"></i>
                        </button>
                    </div>
                    <small class="text-muted">Must be at least 6 characters long</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirm New Password *</label>
                    <div class="input-group">
                        <input type="password" class="form-control" name="confirm_password" id="confirm_password" required minlength="6" aria-label="Confirm new password">
                        <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword" aria-label="Toggle confirm password visibility" title="Show/Hide password">
                            <i class="fas fa-eye" id="toggleConfirmPasswordIcon"></i>
                        </button>
                    </div>
                </div>
                <div class="text-end">
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-key me-2"></i>Change Password
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- My Shifts tab content removed -->
</div>

<script>
// Password validation
document.getElementById('passwordForm')?.addEventListener('submit', function(e) {
    const newPass = document.getElementById('new_password').value;
    const confirmPass = document.getElementById('confirm_password').value;
    
    if (newPass !== confirmPass) {
        e.preventDefault();
        alert('New passwords do not match!');
        return false;
    }
    
    if (newPass.length < 6) {
        e.preventDefault();
        alert('Password must be at least 6 characters long!');
        return false;
    }
});

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
// Password visibility toggles for cashier account settings
document.addEventListener('DOMContentLoaded', function(){
    var toggles = [
        {btnId: 'toggleCurrentPassword', inputId: 'current_password', iconId: 'toggleCurrentPasswordIcon'},
        {btnId: 'toggleNewPassword', inputId: 'new_password', iconId: 'toggleNewPasswordIcon'},
        {btnId: 'toggleConfirmPassword', inputId: 'confirm_password', iconId: 'toggleConfirmPasswordIcon'}
    ];

    toggles.forEach(function(t){
        var btn = document.getElementById(t.btnId);
        var input = document.getElementById(t.inputId);
        var icon = document.getElementById(t.iconId);
        if (!btn || !input) return;
        btn.addEventListener('click', function(){
            if (input.type === 'password') {
                input.type = 'text';
                if (icon) { icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash'); }
            } else {
                input.type = 'password';
                if (icon) { icon.classList.remove('fa-eye-slash'); icon.classList.add('fa-eye'); }
            }
        });
        // make accessible via keyboard (Space/Enter)
        btn.addEventListener('keydown', function(e){
            if (e.key === ' ' || e.key === 'Enter') {
                e.preventDefault();
                btn.click();
            }
        });
    });
});
</script>

</div> <!-- Close account-settings-container -->

<?php
$title = $page_title;
$content = ob_get_clean();
include 'views/layout.php';
?>
