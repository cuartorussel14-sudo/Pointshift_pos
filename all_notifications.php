<?php
require_once 'config.php';
User::requireLogin();

// Allow both admin and staff to view this page
$role = $_SESSION['role'] ?? null;
if (!in_array($role, ['admin', 'staff'])) {
    header('Location: login.php');
    exit();
}

$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT 
    n.*,
    p.name AS product_name,
    p.expiry as product_expiry,
    CASE 
        WHEN n.type = 'expiry' THEN p.expiry
        ELSE NULL 
    END as expiry_date
FROM notifications n 
LEFT JOIN products p ON n.product_id = p.id 
ORDER BY n.created_at DESC LIMIT 1000");
$stmt->execute();
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
$title = 'All Notifications';
ob_start();
?>
<div class="container py-4">
    <h2>All Notifications</h2>
    <div class="mb-3">
        <button id="markAllBtn" class="btn btn-primary btn-sm">Mark all as read</button>
        <button id="deleteAllBtn" class="btn btn-danger btn-sm ms-2">Delete all</button>
    </div>

    <table class="table table-bordered table-sm">
        <thead>
            <tr>
                <th>Time</th>
                <th>Message</th>
                <th>Type</th>
                <th>Product</th>
                <th>Expiry</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($notifications as $n): ?>
            <tr data-id="<?php echo $n['id']; ?>">
                <td><?php echo htmlspecialchars($n['created_at']); ?></td>
                <td><?php echo htmlspecialchars($n['message']); ?></td>
                <td><?php echo htmlspecialchars($n['type']); ?></td>
                <td><?php echo htmlspecialchars($n['product_name'] ?? ''); ?></td>
                <td><?php 
                    $expiryDate = $n['expiry_date'] ?? ($n['type'] === 'expiry' ? $n['product_expiry'] : null);
                    echo !empty($expiryDate) ? htmlspecialchars(date('Y-m-d', strtotime($expiryDate))) : ''; 
                ?></td>
                <td><?php echo htmlspecialchars($n['status']); ?></td>
                <td>
                    <button class="btn btn-sm btn-danger delete-notif">Delete</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php
$content = ob_get_clean();
// Use proper layout depending on role
if ($role === 'staff') {
    include __DIR__ . '/staff/views/layout.php';
} else {
    include __DIR__ . '/layout.php';
}
?>
<script>
document.addEventListener('DOMContentLoaded', function(){
    const ajaxUrl = '<?php echo rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'); ?>/ajax/notifications.php';

    document.getElementById('markAllBtn').addEventListener('click', async function(){
        if (!confirm('Mark all notifications as read?')) return;
        const res = await fetch(ajaxUrl, {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({action:'mark_all_read', include_system: true})
        });
        const data = await res.json();
        if (data.success) location.reload();
        else alert(data.error || 'Failed');
    });

    const deleteAllBtn = document.getElementById('deleteAllBtn');
    if (deleteAllBtn) {
        deleteAllBtn.addEventListener('click', async function(){
            if (!confirm('Delete ALL notifications? This cannot be undone.')) return;
            const res = await fetch(ajaxUrl, {
                method: 'POST', headers: {'Content-Type':'application/json'},
                body: JSON.stringify({action:'delete_all'})
            });
            const data = await res.json();
            if (data.success) location.reload();
            else alert(data.error || 'Failed');
        });
    }

    document.querySelectorAll('.delete-notif').forEach(btn => {
        btn.addEventListener('click', async function(){
            const tr = this.closest('tr');
            const id = tr?.dataset?.id;
            if (!id) return;
            if (!confirm('Delete this notification?')) return;
            const res = await fetch(ajaxUrl, {
                method: 'POST', headers: {'Content-Type':'application/json'},
                body: JSON.stringify({action:'delete', id: id})
            });
            const data = await res.json();
            if (data.success) tr.remove();
            else alert(data.error || 'Failed');
        });
    });
});
</script>
