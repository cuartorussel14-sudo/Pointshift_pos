<?php
require_once 'config.php';

// Fetch admin notification email (to display contact email on login page)
$admin_contact_email = '';
if (isset($conn)) {
    $row = $conn->query("SELECT setting_value FROM store_settings WHERE setting_key = 'admin_notification_email' LIMIT 1")->fetch_assoc();
    if ($row && !empty($row['setting_value'])) {
        $admin_contact_email = $row['setting_value'];
    }
}

// If user is already logged in, redirect to dashboard
if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $authController = new AuthController();
    $result = $authController->login($_POST['username'], $_POST['password']);
    
if ($result['success']) {
    // Redirect based on role
    $role = isset($_SESSION['role']) ? $_SESSION['role'] : '';
    if ($role === 'admin') {
        redirect('dashboard.php');
    } elseif ($role === 'staff') {
        redirect('staff/dashboard.php');
    } elseif ($role === 'cashier') {
        redirect('cashier/dashboard.php');
    } else {
        redirect('dashboard.php'); // fallback
    }
} else {
    $error_message = $result['message'];
}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            overflow: hidden;
            max-width: 400px;
            margin: 0 auto;
        }
        .login-header {
            background: linear-gradient(135deg, #dc3545 0%, #b02a37 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .login-body {
            padding: 2rem;
        }
        .form-control {
            border-radius: 10px;
            padding: 0.75rem 1rem;
            border: 2px solid #e9ecef;
        }
        .form-control:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25);
        }
        /* Ensure request-account modal inputs are readable (override any inherited color) */
        #requestAccountModal .form-control,
        #requestAccountModal input,
        #requestAccountModal textarea,
        #requestAccountModal select {
            background: #fff !important;
            color: #000 !important; /* enforce black text */
        }
        #requestAccountModal .form-control::placeholder,
        #requestAccountModal input::placeholder,
        #requestAccountModal textarea::placeholder {
            color: #6c757d !important;
        }
        /* Broader fallback: ensure modal inputs are readable if different id is used */
        .modal #requestAccountModal .form-control,
        .modal .form-control {
            color: #000 !important;
        }
        .btn-login {
            background: linear-gradient(135deg, #dc3545 0%, #b02a37 100%);
            border: none;
            border-radius: 10px;
            padding: 0.75rem;
            font-weight: 600;
            width: 100%;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .alert {
            border-radius: 10px;
        }
        /* Chrome autofill override: force readable colors for autofilled inputs */
        input:-webkit-autofill,
        textarea:-webkit-autofill,
        select:-webkit-autofill,
        input:-webkit-autofill:focus,
        textarea:-webkit-autofill:focus,
        select:-webkit-autofill:focus {
            -webkit-text-fill-color: #000 !important;
            background-color: #fff !important;
            box-shadow: 0 0 0px 1000px #fff inset !important; /* ensure background override */
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="login-card">
                    <div class="login-header">
                        <i class="fas fa-user-circle fa-3x mb-3"></i>
                        <h3>Welcome Back</h3>
                        <p class="mb-0">Sign in to your account</p>
                    </div>
                    <div class="login-body">
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?php echo htmlspecialchars($error_message); ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                                           required>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" required aria-label="Password">
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword" aria-label="Toggle password visibility" title="Show/Hide password">
                                        <i class="fas fa-eye" id="togglePasswordIcon"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-login">
                                <i class="fas fa-sign-in-alt me-2"></i>Sign In
                            </button>
                        </form>
                        
                        <div class="text-center mt-3">
                            <small class="text-muted">
                                Need an account?
                            </small>
                        </div>

                        <div class="text-center mt-2">
                            <small>
                                <a href="#" class="text-decoration-none" data-bs-toggle="modal" data-bs-target="#requestAccountModal">Request an account</a>
                            </small>
                        </div>

                        <!-- demo/security message removed per request -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <!-- Request Account Modal -->
    <div class="modal fade" id="requestAccountModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Request an Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="request_account.php">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Username *</label>
                            <input type="text" name="username" class="form-control" required style="background:#fff;color:#000 !important;">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">First Name</label>
                            <input type="text" name="first_name" class="form-control" style="background:#fff;color:#000 !important;">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Last Name</label>
                            <input type="text" name="last_name" class="form-control" style="background:#fff;color:#000 !important;">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email Address *</label>
                            <input type="email" name="email" class="form-control" required style="background:#fff;color:#000 !important;">
                        </div>
                        <p class="small text-muted">A verification email will be sent to the address you provide. Follow the instructions in that email to verify and activate your account.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Request Account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    // Show message from request_account if present in session (populated after redirect)
    <?php if (!empty($_SESSION['request_account_message'])): ?>
        (function(){
            var msg = <?php echo json_encode($_SESSION['request_account_message']); ?>;
            var type = <?php echo json_encode($_SESSION['request_account_message_type'] ?? 'info'); ?>;
            // Create alert at top of login card
            var container = document.querySelector('.login-body');
            if (container) {
                var div = document.createElement('div');
                div.className = 'alert alert-' + type + '';
                div.role = 'alert';
                div.innerHTML = '<i class="fas fa-info-circle me-2"></i>' + msg;
                container.insertBefore(div, container.firstChild);
            }
        })();
    <?php unset($_SESSION['request_account_message'], $_SESSION['request_account_message_type']); endif; ?>
    </script>
    <script>
    // Ensure modal inputs are visible in Chrome: enforce styles at runtime and on focus/input
    (function(){
        var modal = document.getElementById('requestAccountModal');
        if (!modal) return;

        function fixInputs() {
            var els = modal.querySelectorAll('input, textarea, select');
            els.forEach(function(el){
                try {
                    el.style.background = '#fff';
                    el.style.color = '#000';
                    el.style.webkitTextFillColor = '#000'; // Chrome autofill safety
                } catch(e){}
            });
        }

        modal.addEventListener('shown.bs.modal', function(){
            fixInputs();
            // re-apply on next tick in case browser autofill repaints
            setTimeout(fixInputs, 50);
        });

        // Also apply on focus/input to keep color
        modal.addEventListener('input', function(e){
            var t = e.target;
            if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA')) {
                t.style.color = '#000';
                try { t.style.webkitTextFillColor = '#000'; } catch(e){}
            }
        });
    })();
    </script>
    <script>
    // Password visibility toggle
    document.addEventListener('DOMContentLoaded', function(){
        var toggle = document.getElementById('togglePassword');
        if (!toggle) return;
        var input = document.getElementById('password');
        var icon = document.getElementById('togglePasswordIcon');
        toggle.addEventListener('click', function(){
            if (input.type === 'password') {
                input.type = 'text';
                if (icon) { icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash'); }
            } else {
                input.type = 'password';
                if (icon) { icon.classList.remove('fa-eye-slash'); icon.classList.add('fa-eye'); }
            }
        });
    });
    </script>
</body>
</html>
