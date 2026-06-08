<?php
require_once 'config.php';
User::requireLogin();
// Only admin can access admin notifications
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("SELECT n.*, p.name AS product_name FROM notifications n LEFT JOIN products p ON n.product_id = p.id ORDER BY n.created_at DESC LIMIT 200");
$stmt->execute();
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
$title = 'Notifications';
ob_start();
?>
<div class="container py-4">
    <h2>Notifications</h2>
    <div class="mb-3">
        <button id="markAllBtn" class="btn btn-primary btn-sm">Mark all as read</button>
        <button id="deleteAllBtn" class="btn btn-danger btn-sm">Delete all</button>
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
// Use the root layout file
include __DIR__ . '/layout.php';
?>
<script>
document.addEventListener('DOMContentLoaded', function(){
    // Ensure SITE_URL is available in JS; fallback to dirname if missing
    const BASE_URL = (typeof SITE_URL !== 'undefined') ? SITE_URL : '<?php echo rtrim(dirname($_SERVER['SCRIPT_NAME']), "/\\"); ?>';
    const notifEndpoint = BASE_URL + '/ajax/notifications.php';

    document.getElementById('markAllBtn').addEventListener('click', async function(){
        if (!confirm('Mark all notifications as read?')) return;
        const res = await fetch(notifEndpoint, {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({action:'mark_all_read', include_system: true})
        });
        const data = await res.json();
        if (data.success) location.reload();
    });

    document.getElementById('deleteAllBtn').addEventListener('click', async function(){
        if (!confirm('Delete ALL notifications? This cannot be undone.')) return;
        const res = await fetch(notifEndpoint, {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({action:'delete_all'})
        });
        const data = await res.json();
        if (data.success) location.reload();
        else alert(data.error || 'Failed');
    });

    document.querySelectorAll('.delete-notif').forEach(btn => {
        btn.addEventListener('click', async function(){
            const tr = this.closest('tr');
            const id = tr?.dataset?.id;
            if (!id) return;
            if (!confirm('Delete this notification?')) return;
            const res = await fetch(notifEndpoint, {
                method: 'POST', headers: {'Content-Type':'application/json'},
                body: JSON.stringify({action:'delete', id: id})
            });
            const data = await res.json();
            if (data.success) tr.remove();
            else alert(data.error || 'Failed');
        });
    });

    // Fix sidebar and logout links that may be broken when using nested paths
    // Convert any relative sidebar/logout anchors to absolute SITE_URL paths
    (function fixNavLinks(){
        try {
            const site = (typeof SITE_URL !== 'undefined') ? SITE_URL : '<?php echo rtrim((defined("SITE_URL") ? SITE_URL : dirname($_SERVER['SCRIPT_NAME'])), "/\\"); ?>';
            // common selectors for logout and sidebar anchors
            document.querySelectorAll('a[href*="logout.php"]').forEach(a => a.href = site + '/logout.php');
            document.querySelectorAll('a[href*="/staff/"]').forEach(a => {
                // if link is missing site prefix, prefix it
                if (!a.href.startsWith(site)) {
                    const path = a.getAttribute('href');
                    a.href = site + (path.startsWith('/') ? path : '/' + path);
                }
            });
        } catch (e) {
            console.warn('Nav link fixer failed', e);
        }
    })();
});
</script>
