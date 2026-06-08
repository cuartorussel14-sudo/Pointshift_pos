<?php
require_once 'config.php';

// If user is already logged in, redirect to dashboard
if (isLoggedIn()) {
    redirect('dashboard.php');
}

// Public quick stats (best-effort). Use existing $conn (mysqli)
$total_products = 0;
$total_users = 0;
$total_sales_month = 0.00;
$total_transactions_month = 0;
if (isset($conn)) {
    try {
        $r = $conn->query("SELECT COUNT(*) as cnt FROM products");
        $total_products = $r ? intval($r->fetch_assoc()['cnt'] ?? 0) : 0;

        $r = $conn->query("SELECT COUNT(*) as cnt FROM users");
        $total_users = $r ? intval($r->fetch_assoc()['cnt'] ?? 0) : 0;

        $startMonth = date('Y-m-01');
        $endMonth = date('Y-m-t');
        $stmt = $conn->prepare("SELECT COUNT(*) as cnt, IFNULL(SUM(total_amount),0) as total FROM orders WHERE status = 'completed' AND DATE(created_at) BETWEEN ? AND ?");
        if ($stmt) {
            $stmt->bind_param('ss', $startMonth, $endMonth);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $total_transactions_month = intval($res['cnt'] ?? 0);
            $total_sales_month = floatval($res['total'] ?? 0);
            $stmt->close();
        }
    } catch (Throwable $e) {
        // ignore - these stats are non-critical for homepage
    }
}

// Fetch some store settings for the homepage (logo, admin email)
$store_name = defined('SITE_NAME') ? SITE_NAME : 'PointShift';
$store_logo = '';
$admin_contact_email = '';
if (isset($conn)) {
    $row = $conn->query("SELECT setting_value FROM store_settings WHERE setting_key = 'store_logo' LIMIT 1")->fetch_assoc();
    if ($row && !empty($row['setting_value'])) {
        $store_logo = $row['setting_value'];
    }
    $row = $conn->query("SELECT setting_value FROM store_settings WHERE setting_key = 'admin_notification_email' LIMIT 1")->fetch_assoc();
    if ($row && !empty($row['setting_value'])) {
        $admin_contact_email = $row['setting_value'];
    }
    $row = $conn->query("SELECT setting_value FROM store_settings WHERE setting_key = 'store_name' LIMIT 1")->fetch_assoc();
    if ($row && !empty($row['setting_value'])) {
        $store_name = $row['setting_value'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($store_name); ?> - POS</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary: #dc3545; --primary-dark: #b02a37; }
        .hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            padding: 4rem 0;
        }
        .feature-card { border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.07); }
        .btn-cta { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); color: #fff; border: none; }
        .brand-logo { max-height: 64px; }
        footer { background: #f8f9fa; padding: 2rem 0; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <?php if (!empty($store_logo)): ?>
                    <img src="<?php echo htmlspecialchars($store_logo); ?>" alt="logo" class="brand-logo me-2">
                <?php else: ?>
                    <i class="fas fa-cash-register fa-lg me-2 text-danger"></i>
                <?php endif; ?>
                <span class="fw-bold text-dark"><?php echo htmlspecialchars($store_name); ?></span>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-3 me-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link active" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
                    <li class="nav-item"><a class="nav-link" href="#about">About / Team</a></li>
                    <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
                </ul>

                <div class="d-flex ms-auto">
                    <a href="login.php" class="btn btn-outline-danger btn-sm me-2">Login</a>
                </div>
            </div>
        </div>
    </nav>

    <header class="hero" id="home">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-12 text-center text-md-start mb-4 mb-md-0">
                    <h1 class="display-5 fw-bold">Streamline Sales and Inventory Management with PointShift</h1>
                    <p class="lead">A web-based POS and inventory tracking system designed for VilMar Minimart — fast, accurate, and efficient.</p>
                    <div class="mt-4">
                        <a href="login.php" class="btn btn-outline-light btn-lg">Login Now</a>
                    </div>
                </div>
                <!-- dashboard preview removed -->
            </div>
        </div>
    </header>

    <main class="py-5">
        <div class="container">
            <div class="row g-4" id="features">
                <div class="col-md-4 col-lg-2">
                    <div class="p-3 feature-card h-100 text-center">
                        <div class="mb-2"><i class="fas fa-credit-card fa-2x text-danger"></i></div>
                        <h6>POS System</h6>
                        <small class="text-muted">Quick transactions, barcode scanning, receipt generation</small>
                    </div>
                </div>
                <div class="col-md-4 col-lg-2">
                    <div class="p-3 feature-card h-100 text-center">
                        <div class="mb-2"><i class="fas fa-boxes fa-2x text-primary"></i></div>
                        <h6>Inventory Management</h6>
                        <small class="text-muted">Real-time stock tracking, restock alerts</small>
                    </div>
                </div>
                <div class="col-md-4 col-lg-2">
                    <div class="p-3 feature-card h-100 text-center">
                        <div class="mb-2"><i class="fas fa-chart-line fa-2x text-success"></i></div>
                        <h6>Sales Analysis</h6>
                        <small class="text-muted">Sales trends & forecasting</small>
                    </div>
                </div>
                <div class="col-md-4 col-lg-2">
                    <div class="p-3 feature-card h-100 text-center">
                        <div class="mb-2"><i class="fas fa-bell fa-2x text-warning"></i></div>
                        <h6>Notifications</h6>
                        <small class="text-muted">Low-stock and expiry alerts</small>
                    </div>
                </div>
                <div class="col-md-4 col-lg-2">
                    <div class="p-3 feature-card h-100 text-center">
                        <div class="mb-2"><i class="fas fa-users-cog fa-2x text-secondary"></i></div>
                        <h6>User Management</h6>
                        <small class="text-muted">Manage Admin, Cashier, and Staff accounts</small>
                    </div>
                </div>
            </div>

            <!-- Quick stats -->
            <div class="row mt-4">
                <div class="col-12 mb-3">
                    <div class="p-3 feature-card text-center">
                        <h6 class="mb-1">Get Started</h6>
                        <a href="login.php" class="btn btn-cta btn-sm">Sign In</a>
                        <a href="request_account.php" class="btn btn-outline-secondary btn-sm ms-2">Request</a>
                    </div>
                </div>
            </div>

            <!-- Quick Links removed as requested -->

            <div class="row mt-5" id="about">
                <div class="col-md-8">
                    <h4>About PointShift</h4>
                    <p class="text-muted">PointShift is a web-based Point of Sale and Inventory Management System developed for VilMar Minimart in Sariaya, Quezon. It simplifies daily operations, tracks sales trends, detects anomalies, and ensures accurate stock management in real-time.</p>
                    <p class="text-muted">Developed by BSIT Students of C.S.T.C.</p>
                </div>
                    <div class="col-md-4 text-md-end">
                    <!-- View System Demo removed -->
                </div>
            </div>

            <!-- Screenshots section removed per request -->

            <div class="row mt-5" id="contact">
                <div class="col-md-8">
                    <h4>Contact</h4>
                    <p class="text-muted mb-1">Developed by BSIT Students of C.S.T.C.</p>
                    <p class="text-muted mb-1">For inquiries or project notes, contact: <a href="mailto:devs@cstc.edu.ph">devs@cstc.edu.ph</a></p>
                    <p class="text-muted mb-0">Source code: <a href="https://github.com/LeeDev428/point-shift_pos-system" target="_blank">GitHub / point-shift_pos-system</a></p>
                </div>
                <div class="col-md-4 text-md-end">
                    <p class="small text-muted mb-1">Made for VilMar Minimart — Sariaya, Quezon</p>
                    <p class="small text-muted mb-0">© 2025 PointShift | All Rights Reserved</p>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <div class="container d-flex justify-content-between align-items-center">
            <div>
                <small class="text-muted">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($store_name); ?>. All rights reserved.</small>
            </div>
            <div>
                <?php if (!empty($admin_contact_email)): ?>
                    <small class="text-muted">Contact admin: <a href="mailto:<?php echo htmlspecialchars($admin_contact_email); ?>"><?php echo htmlspecialchars($admin_contact_email); ?></a></small>
                <?php else: ?>
                    <small class="text-muted">Contact admin via <a href="login.php">Request Account</a></small>
                <?php endif; ?>
            </div>
        </div>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>
