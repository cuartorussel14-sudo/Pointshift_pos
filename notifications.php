<?php
require_once __DIR__ . '/../config.php';
User::requireLogin();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../login.php');
    exit();
}

$db = Database::getInstance()->getConnection();
$sessionUser = $_SESSION['user_id'] ?? null;
// Only show notifications that are system-wide (user_id IS NULL) or target this staff user
$stmt = $db->prepare("SELECT * FROM notifications WHERE (user_id IS NULL OR user_id = ?) ORDER BY created_at DESC LIMIT 200");
$stmt->execute([$sessionUser]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
$title = 'Notifications';
ob_start();
?>
<div class="container py-4">
    <h2>Notifications</h2>
    <div class="mb-3">
        <button id="markAllBtn" class="btn btn-primary btn-sm">Mark all as read</button>
        <div class="form-text small text-muted mt-1">This will mark the notifications shown on this page as read. System-wide notifications visible here will also be marked.</div>
    </div>
    <table class="table table-bordered table-sm">
        <thead>
            <tr><th>Time</th><th>Message</th><th>Type</th><th>Status</th></tr>
        </thead>
        <tbody>
            <?php foreach ($notifications as $n): ?>
            <tr data-id="<?php echo $n['id']; ?>">
                <td><?php echo htmlspecialchars($n['created_at']); ?></td>
                <td><?php echo htmlspecialchars($n['message']); ?></td>
                <td><?php echo htmlspecialchars($n['type']); ?></td>
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
include __DIR__ . '/views/layout.php';
?>
<script>
document.addEventListener('DOMContentLoaded', function(){
    document.getElementById('markAllBtn').addEventListener('click', async function(){
        if (!confirm('Mark all notifications displayed on this page as read?')) return;
        // collect visible notification ids from the table
        const ids = Array.from(document.querySelectorAll('tbody tr[data-id]')).map(tr => parseInt(tr.dataset.id, 10)).filter(Boolean);
        if (ids.length === 0) {
            alert('No notifications to mark.');
            return;
        }
        // use absolute path to ensure we hit the central ajax endpoint
        const res = await fetch('/ajax/notifications.php', {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({action:'mark_all_read', ids: ids})
        });
        const data = await res.json();
        if (data.success) location.reload();
        else alert(data.error || 'Failed to mark notifications');
    });

    document.querySelectorAll('.delete-notif').forEach(btn => {
        btn.addEventListener('click', async function(){
            const tr = this.closest('tr');
            const id = tr?.dataset?.id;
            if (!id) return;
            if (!confirm('Delete this notification?')) return;
            const res = await fetch('<?php echo dirname($_SERVER['SCRIPT_NAME']) . "/ajax/notifications.php"; ?>', {
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
