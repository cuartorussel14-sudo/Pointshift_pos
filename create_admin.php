<?php
/**
 * create_admin.php
 * Lightweight script to create an admin user in the database.
 * Usage: open in browser, fill details, submit. Remove this file after use.
 */
require_once 'config.php';
// Do not require login — this is an administrative helper. Make sure to delete after use.

// Load encryption helper
require_once __DIR__ . '/classes/Encryption.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');

    if ($username === '' || $email === '' || $password === '') {
        $error = 'Username, email and password are required.';
    } else {
        try {
            $db = Database::getInstance()->getConnection();

            // Check uniqueness
            $stmt = $db->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $error = 'A user with that username or email already exists.';
            } else {
                $encryption = Encryption::getInstance();
                $enc = $encryption->encrypt($email);
                $hashed = password_hash($password, PASSWORD_DEFAULT);

                $insert = $db->prepare(
                    'INSERT INTO users (username, email, email_encrypted, email_iv, email_tag, password, first_name, last_name, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
                );

                $role = 'admin';
                $status = 'active';

                $successInsert = $insert->execute([
                    $username,
                    $email,
                    $enc['data'],
                    $enc['iv'],
                    $enc['tag'],
                    $hashed,
                    $first_name,
                    $last_name,
                    $role,
                    $status
                ]);

                if ($successInsert) {
                    $success = 'Admin user created successfully.';
                } else {
                    $error = 'Failed to insert user.';
                }
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Create Admin User</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Create Admin User (remove this file after use)</div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>

                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input name="username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input name="email" type="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input name="password" type="password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">First name</label>
                            <input name="first_name" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Last name</label>
                            <input name="last_name" class="form-control">
                        </div>
                        <div class="d-grid">
                            <button class="btn btn-primary">Create Admin</button>
                        </div>
                    </form>
                    <hr>
                    <div class="small text-muted">Warning: delete this file after creating the account to prevent unauthorized access.</div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
