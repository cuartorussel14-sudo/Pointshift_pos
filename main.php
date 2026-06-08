<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title . ' - ' . SITE_NAME; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="/point-shift_pos-system/assets/css/notifications.css" rel="stylesheet">
    <script src="/point-shift_pos-system/assets/js/notification-system.js" defer></script>
    <style>
        /* Notification Styles */
        .notification-toast {
            background: white;
            border-left: 4px solid #007bff;
            border-radius: 4px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            margin-bottom: 10px;
            min-width: 300px;
            max-width: 400px;
            padding: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            animation: slideIn 0.3s ease-out;
        }

        .notification-content {
            display: flex;
            align-items: center;
            flex: 1;
            margin-right: 10px;
        }

        .notification-success { border-left-color: #28a745; }
        .notification-error { border-left-color: #dc3545; }
        .notification-warning { border-left-color: #ffc107; }
        .notification-low_stock { border-left-color: #ffc107; }
        .notification-out_of_stock { border-left-color: #dc3545; }
        .notification-transaction { border-left-color: #28a745; }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .sidebar {
            background: linear-gradient(180deg, <?php echo $role === 'admin' ? '#dc3545' : '#28a745'; ?> 0%, <?php echo $role === 'admin' ? '#b02a37' : '#1e7e34'; ?> 100%);
            min-height: 100vh;
            width: 250px;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
            transition: all 0.3s;
        }
        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }
        .sidebar-header h4 {
            color: white;
            margin: 0;
            font-weight: bold;
        }
        .sidebar-header small {
            color: rgba(255,255,255,0.8);
        }
        .role-badge {
            display: inline-block;
            padding: .25rem .6rem;
            font-size: .775rem;
            font-weight: 600;
            color: #fff;
            border-radius: 999px;
            line-height: 1;
            vertical-align: middle;
            margin-top: 0.5rem;
        }
        .sidebar-menu {
            padding: 1rem 0;
        }
        .sidebar-menu .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0;
            transition: all 0.3s;
        }
        .sidebar-menu .nav-link:hover,
        .sidebar-menu .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
            transform: translateX(5px);
        }
        .sidebar-menu .nav-link i {
            width: 20px;
            margin-right: 10px;
        }
        .main-content {
            margin-left: 250px;
            min-height: 100vh;
        }
        .top-navbar {
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e9ecef;
        }
        .content-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 1.5rem;
        }
        .content-card .card-header {
            background: transparent;
            border-bottom: 1px solid #e9ecef;
            padding: 1.25rem 1.5rem;
            font-weight: 600;
        }
        .content-card .card-body {
            padding: 1.5rem;
        }
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            border-left: 4px solid <?php echo $role === 'admin' ? '#dc3545' : '#28a745'; ?>;
            transition: transform 0.2s;
        }
        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .btn-custom {
            border-radius: 8px;
            padding: 0.5rem 1rem;
            font-weight: 500;
        }
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-cash-register fa-2x text-white mb-2"></i>
            <h4>PointShift</h4>
            <small><?php echo ucfirst($role); ?> Panel</small>
            <div class="role-badge"><?php echo ucfirst($role); ?></div>
        </div>
        
        <nav class="sidebar-menu">
            <ul class="nav flex-column">
                <?php
                // Provide a sensible default menu when pages don't supply $menuItems.
                if (empty($menuItems) || !is_array($menuItems)) {
                    $menuItems = [];
                    // Minimal defaults per role
                    if (isset($role) && $role === 'cashier') {
                        $menuItems = [
                            ['title' => 'Dashboard', 'url' => '/point-shift_pos-system/cashier', 'icon' => 'fas fa-tachometer-alt', 'active' => false],
                            ['title' => 'View Inventory', 'url' => '/point-shift_pos-system/cashier/inventory.php', 'icon' => 'fas fa-boxes', 'active' => false],
                            ['title' => 'Transactions', 'url' => '/point-shift_pos-system/cashier/transactions.php', 'icon' => 'fas fa-receipt', 'active' => false],
                        ];
                    } else {
                        // Admin / fallback menu
                        $menuItems = [
                            ['title' => 'Dashboard', 'url' => '/point-shift_pos-system/dashboard.php', 'icon' => 'fas fa-tachometer-alt', 'active' => false],
                            ['title' => 'Inventory', 'url' => '/point-shift_pos-system/inventory.php', 'icon' => 'fas fa-boxes', 'active' => false],
                            ['title' => 'Transactions', 'url' => '/point-shift_pos-system/transactions.php', 'icon' => 'fas fa-receipt', 'active' => false],
                            ['title' => 'Reports', 'url' => '/point-shift_pos-system/reports.php', 'icon' => 'fas fa-chart-line', 'active' => false],
                        ];
                    }
                }

                foreach ($menuItems as $item): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo !empty($item['active']) ? 'active' : ''; ?>" href="<?php echo $item['url']; ?>">
                        <i class="<?php echo $item['icon']; ?>"></i>
                        <?php echo $item['title']; ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navigation -->
        <nav class="top-navbar d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <button class="btn btn-light d-md-none me-3" id="sidebar-toggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h5 class="mb-0"><?php echo $title; ?></h5>
            </div>
            
            <div class="d-flex align-items-center">
                <!-- Notifications Dropdown -->
                <?php
                // Prepare notifications DB and data so the badge/count can be rendered
                require_once __DIR__ . '/../../classes/Notification.php';
                $notifDb = Database::getInstance()->getConnection();
                $recentNotifs = [];
                try {
                    $recentNotifs = Notification::fetchRecent($notifDb, 8);
                } catch (Exception $e) {
                    $recentNotifs = [];
                }
                $unreadCount = Notification::getUnreadCount($notifDb);
                ?>
                <div class="dropdown me-3">
                    <button class="btn btn-light position-relative" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-bell"></i>
                        <?php if ($unreadCount > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge">
                            <?php echo $unreadCount; ?>
                        </span>
                        <?php endif; ?>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end notification-dropdown" style="width: 350px;">
                        <div class="dropdown-header d-flex justify-content-between align-items-center">
                            <span>Notifications</span>
                            <?php if ($unreadCount > 0): ?>
                            <button class="btn btn-link btn-sm p-0 text-decoration-none" onclick="markAllNotificationsRead()">
                                Mark all read
                            </button>
                            <?php endif; ?>
                        </div>
                        <div id="notificationsList" style="max-height: 400px; overflow-y: auto;">
                            <?php if (empty($recentNotifs)): ?>
                                <div class="dropdown-item text-muted">No notifications</div>
                            <?php else: ?>
                                <?php foreach ($recentNotifs as $n): ?>
                                    <a class="dropdown-item" href="#" onclick="return false;">
                                        <div class="small text-muted"><?php echo htmlspecialchars($n['created_at'] ?? ''); ?></div>
                                        <div class="mb-1"><?php echo htmlspecialchars($n['message'] ?? ''); ?></div>
                                        <?php if (!empty($n['expiry'])): ?>
                                            <div class="small text-muted">Expiry: <?php echo htmlspecialchars(date('Y-m-d', strtotime($n['expiry']))); ?></div>
                                        <?php endif; ?>
                                    </a>
                                    <div class="dropdown-divider"></div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-center" href="<?php echo SITE_URL; ?>/all_notifications.php">View all notifications</a>
                    </div>
                </div>
            
            
            <div class="d-flex align-items-center">

                <div class="dropdown">
                    <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-2"></i>
                        <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?>
                        <span class="bg-<?php echo $role === 'admin' ? 'danger' : 'success'; ?> ms-2 role-badge"><?php echo ucfirst($role); ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="https://pointshift.online/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </nav>

        <!-- Page Content -->
        <div class="container-fluid p-4">
            <?php echo $content; ?>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <?php if (!empty($include_hardware_js)): ?>
        <script src="https://unpkg.com/qz-tray@2.0.0/qz-tray.js"></script>
        <script src="/point-shift_pos-system/assets/js/hardware.js"></script>
    <?php endif; ?>
    <script>
        // Current session user id for conditional mark-read behavior
        const CURRENT_USER_ID = <?php echo json_encode($_SESSION['user_id'] ?? null); ?>;
        // Sidebar toggle for mobile
        document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('show');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 768) {
                const sidebar = document.getElementById('sidebar');
                const toggle = document.getElementById('sidebar-toggle');
                
                if (!sidebar.contains(e.target) && !toggle.contains(e.target)) {
                    sidebar.classList.remove('show');
                }
            }
        });
    </script>
    <!-- Toast container for pop-up notifications -->
        <div class="notification-container position-fixed top-0 end-0 p-3" style="z-index: 1100; margin-top: 60px;">
        <div id="notifToasts"></div>
    </div>
    <script>
    // Function to mark all notifications as read
    async function markAllNotificationsRead() {
        try {
            const res = await fetch('<?php echo dirname($_SERVER['SCRIPT_NAME']) . "/ajax/notifications.php"; ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'mark_all_read',
                    include_system: true // request to include system-wide notifications when marking all
                })
            });
            const data = await res.json();
            if (data.success) {
                // Update UI
                document.querySelectorAll('.notification-badge').forEach(badge => badge.remove());
                fetchAndShowNotifs();
            }
        } catch (error) {
            console.error('Error marking notifications as read:', error);
        }
    }

    // Poll for unread notifications and show Bootstrap toasts
    async function fetchAndShowNotifs() {
        try {
            const res = await fetch('<?php echo dirname($_SERVER['SCRIPT_NAME']) . "/ajax/notifications.php"; ?>');
            const data = await res.json();
            // Debug: Log what we're getting from the server
            console.log('Notifications data:', data);
            
            if (data.success) {
                // Update unread badge only; do not refresh/replace the dropdown contents so notifications
                // remain visible in the bell until explicitly cleared by user action.
                try {
                    const badge = document.querySelector('.notification-badge');
                    if (data.unreadCount && data.unreadCount > 0) {
                        if (badge) {
                            badge.textContent = data.unreadCount;
                        } else {
                            // Create badge if missing
                            const btn = document.querySelector('.fa-bell')?.closest('button');
                            if (btn) {
                                const span = document.createElement('span');
                                span.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge';
                                span.textContent = data.unreadCount;
                                btn.appendChild(span);
                            }
                        }
                    } else {
                        if (badge) badge.remove();
                    }
                } catch (e) {
                    console.warn('Failed to update notification badge', e);
                }

                // Show toasts for new notifications (do not manipulate dropdown list here)
                if (Array.isArray(data.notifications)) {
                    for (const n of data.notifications) {
                        const expiryDate = n.expiry_date || (n.type === 'expiry' ? n.product_expiry : null);
                        showToast(n.id, n.message, n.type, expiryDate, n.user_id ?? null);
                    }
                }
            }
        } catch (e) {
            console.error('Notif poll error', e);
        }
    }

    function showToast(id, message, type = 'info', expiry = null, notifUserId = null) {
        const container = document.getElementById('notifToasts');
        const toastId = 'toast-' + id + '-' + Date.now();
        const div = document.createElement('div');
        div.className = `toast notification-toast notification-${type} mb-2`;
        div.id = toastId;
        div.setAttribute('role','alert');
        div.setAttribute('aria-live','assertive');
        div.setAttribute('aria-atomic','true');
        
        const icon = getNotificationIcon(type);
        const expiryHtml = expiry ? `<div class="small text-muted mt-1">Expiry: ${escapeHtml(new Date(expiry).toLocaleDateString())}</div>` : '';
        div.innerHTML = `
            <div class="notification-content">
                <i class="fas ${icon} me-2"></i>
                <div>
                    <div class="toast-body">${escapeHtml(message)}</div>
                    ${expiryHtml}
                </div>
            </div>
            <button type="button" class="btn-close me-2" data-bs-dismiss="toast" aria-label="Close"></button>
        `;
        container.appendChild(div);
        const bsToast = new bootstrap.Toast(div, { delay: 8000 });
        bsToast.show();
        // mark this notification as read after showing it only if it's targeted to a specific user
        try {
            if (notifUserId !== null && notifUserId !== undefined) {
                // If notification targets a user, only mark read when it matches the current user
                if (String(notifUserId) === String(CURRENT_USER_ID)) {
                    fetch('<?php echo dirname($_SERVER['SCRIPT_NAME']) . "/ajax/notifications.php"; ?>', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'mark_read', id })
                    }).catch(e => console.warn('Failed to mark notif read:', e));
                }
            } else {
                // System-wide notification (user_id NULL) -> do not mark read on client to allow other users to see it
            }
        } catch (e) {
            console.warn('Mark read call failed', e);
        }
    }

    function getNotificationIcon(type) {
        switch(type) {
            case 'success': return 'fa-check-circle';
            case 'error': return 'fa-times-circle';
            case 'warning': return 'fa-exclamation-triangle';
            case 'low_stock': return 'fa-box';
            case 'out_of_stock': return 'fa-box-open';
            case 'transaction': return 'fa-receipt';
            default: return 'fa-info-circle';
        }
    }

    function escapeHtml(str) {
        return String(str).replace(/[&<>"']/g, function(m) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"}[m]; });
    }

    // Poll every 5 seconds (shorter for quicker expiry notification delivery during testing)
    setInterval(fetchAndShowNotifs, 5000);
    // Run on page load
    fetchAndShowNotifs();
    </script>
    <script>
        // Debug: detect if role badge gets removed and log a stack trace so we can find the culprit.
        (function watchRoleBadge(){
            try {
                const observer = new MutationObserver(function(mutations) {
                    for (const m of mutations) {
                        for (const node of m.removedNodes) {
                            if (node.nodeType === Node.ELEMENT_NODE) {
                                if (node.classList && node.classList.contains('role-badge')) {
                                    console.error('role-badge was removed from DOM. Mutation record:', m);
                                    try { throw new Error('role-badge removed - stack'); } catch (e) { console.error(e.stack); }
                                }
                                const removedRole = node.querySelector && node.querySelector('.role-badge');
                                if (removedRole) {
                                    console.error('role-badge was removed as a descendant. Mutation record:', m);
                                    try { throw new Error('role-badge removed (descendant) - stack'); } catch (e) { console.error(e.stack); }
                                }
                            }
                        }
                    }
                });

                observer.observe(document.documentElement || document.body, {childList: true, subtree: true});
                setTimeout(() => observer.disconnect(), 5 * 60 * 1000);
            } catch (e) {
                console.warn('Role-badge observer failed to initialize', e);
            }
        })();
    </script>
</body>
</html>
