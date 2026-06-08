<?php
require_once 'config.php';
requireLogin();
requireAdmin();

header('Content-Type: application/json');

// Fetch user status based on last activity (online if active within 5 minutes, else last seen or never)
$query = "SELECT id, last_activity FROM users";
$result = $conn->query($query);

$statuses = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $status = 'Never';
        $online = false;
        if ($row['last_activity']) {
            $ts = strtotime($row['last_activity']);
            $status = 'Last seen: ' . date('M j, Y g:i A', $ts);
            // Consider online if last activity within 5 minutes
            if (time() - $ts < 5) {
                $online = true;
            }
        }
        $statuses[$row['id']] = ['status' => $status, 'online' => $online];
    }
}

echo json_encode($statuses);
?>
