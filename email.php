<?php
require_once '../config.php';
requireLogin();

$title = 'Email Admin';
$user_id = $_SESSION['user_id'];

// Handle sending new message
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'send') {
        $subject = trim($_POST['subject']);
        $message_text = trim($_POST['message']);
        $parent_id = !empty($_POST['parent_id']) ? $_POST['parent_id'] : NULL;
        $recipient_email = !empty($_POST['recipient_email']) ? trim($_POST['recipient_email']) : null;
        
        // Determine recipient
        $recipient_id = null;
        $recipient_name = null;
        $recipient_email_final = null;
        
        if ($recipient_email && filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
            // Send to external email address
            $recipient_email_final = $recipient_email;
            $recipient_name = "External Recipient";
        } else {
            // Send to admin (default behavior)
            // Prefer a configured admin notification email from store_settings
            $stmt_setting = $conn->prepare("SELECT setting_value FROM store_settings WHERE setting_key = 'admin_notification_email' LIMIT 1");
            $stmt_setting->execute();
            $setting_row = $stmt_setting->get_result()->fetch_assoc();

            if (!empty($setting_row['setting_value']) && filter_var($setting_row['setting_value'], FILTER_VALIDATE_EMAIL)) {
                $recipient_email_final = $setting_row['setting_value'];
                $recipient_name = 'Admin';

                // Try to find corresponding admin user id by email
                $stmt_admin = $conn->prepare("SELECT id, first_name, last_name FROM users WHERE email = ? LIMIT 1");
                $stmt_admin->bind_param('s', $recipient_email_final);
                $stmt_admin->execute();
                $admin = $stmt_admin->get_result()->fetch_assoc();
                if ($admin) {
                    $recipient_id = $admin['id'];
                    $recipient_name = trim($admin['first_name'] . ' ' . $admin['last_name']);
                }
            } else {
                // Fallback to the first admin user record
                $admin_query = $conn->query("SELECT id, email, first_name, last_name FROM users WHERE role = 'admin' LIMIT 1");
                $admin = $admin_query->fetch_assoc();

                if ($admin) {
                    $recipient_id = $admin['id'];
                    $recipient_email_final = $admin['email'];
                    $recipient_name = trim($admin['first_name'] . ' ' . $admin['last_name']);
                } else {
                    $message = "No admin user found!";
                    $message_type = "danger";
                }
            }
        }
        
        if ($recipient_email_final) {
            // Store message in database only if sending to internal user
            if ($recipient_id) {
            $stmt = $conn->prepare("INSERT INTO messages (sender_id, recipient_id, subject, message, parent_message_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iissi", $user_id, $recipient_id, $subject, $message_text, $parent_id);
                $stmt->execute();
            }
            
            // Send email notification
                require_once __DIR__ . '/../classes/Mailer.php';
            $sender_name = ($_SESSION['first_name'] ?? 'Staff') . ' ' . ($_SESSION['last_name'] ?? '');
            $emailSub = "Message from " . $sender_name . ": " . ($subject ?: 'New message');
            $excerpt = htmlspecialchars(substr($message_text, 0, 300));
            
            $body = "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>";
            $body .= "<h2 style='color: #dc3545; border-bottom: 2px solid #dc3545; padding-bottom: 10px;'>PointShift POS Message</h2>";
            $body .= "<p>Hi " . htmlspecialchars($recipient_name) . ",</p>";
            $body .= "<p>You have received a new message from <strong>" . htmlspecialchars($sender_name) . "</strong> via PointShift POS system.</p>";
            $body .= "<div style='background: #f8f9fa; padding: 15px; border-left: 4px solid #dc3545; margin: 20px 0;'>";
            $body .= "<h3 style='margin-top: 0; color: #dc3545;'>" . htmlspecialchars($subject) . "</h3>";
            $body .= "<p style='white-space: pre-wrap;'>" . nl2br(htmlspecialchars($message_text)) . "</p>";
            $body .= "</div>";
            
            if ($recipient_id) {
                $convLink = SITE_URL . '/messages.php?conversation_id=' . ($parent_id ?? $conn->insert_id);
                $body .= "<p><a href='" . $convLink . "' style='background: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>View in PointShift POS</a></p>";
            }
            
            $body .= "<hr style='margin: 30px 0; border: none; border-top: 1px solid #eee;'>";
            $body .= "<p style='color: #666; font-size: 12px;'>This message was sent from PointShift POS system on " . date('F d, Y \a\t g:i A') . "</p>";
            $body .= "</div>";

            $email_sent = Mailer::sendEmail($recipient_email_final, $emailSub, $body, SITE_NAME, null);
            
                if ($email_sent) {
                    if (defined('EMAIL_DISABLED') && EMAIL_DISABLED) {
                        $message = "Message saved successfully! (Email system is currently disabled)";
                        $message_type = "info";
                    } else {
                        $message = "Message sent successfully to " . htmlspecialchars($recipient_email_final) . "!";
                        $message_type = "success";
                }
            } else {
                    $message = "Message saved but email delivery failed. Check email configuration.";
                    $message_type = "warning";
            }
        } else {
            $message = "Invalid email address or no recipient found!";
            $message_type = "danger";
        }
    }
    
    if ($_POST['action'] === 'mark_read') {
        $message_id = $_POST['message_id'];
        $stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE id = ? AND recipient_id = ?");
        $stmt->bind_param("ii", $message_id, $user_id);
        $stmt->execute();
    }

    if ($_POST['action'] === 'delete_message') {
        $message_id = isset($_POST['message_id']) ? intval($_POST['message_id']) : 0;
        if ($message_id) {
            // ensure the current user is the sender of the message
            $chk = $conn->prepare("SELECT sender_id FROM messages WHERE id = ? LIMIT 1");
            $chk->bind_param('i', $message_id);
            $chk->execute();
            $row = $chk->get_result()->fetch_assoc();
            if ($row && $row['sender_id'] == $user_id) {
                $del = $conn->prepare("DELETE FROM messages WHERE id = ? OR parent_message_id = ?");
                $del->bind_param('ii', $message_id, $message_id);
                if ($del->execute()) {
                    $message = 'Message deleted successfully.';
                    $message_type = 'success';
                    // clear selected conversation if it was deleted
                    if (isset($_POST['conversation_id']) && $_POST['conversation_id'] == $message_id) {
                        unset($_GET['conversation_id']);
                        $selected_conversation = null;
                        $conversation_thread = [];
                    }
                } else {
                    $message = 'Error deleting message: ' . $conn->error;
                    $message_type = 'danger';
                }
            } else {
                $message = 'You are not authorized to delete this message.';
                $message_type = 'danger';
            }
        }
    }
}

// Get all conversations (parent messages only)
$conversations_query = "
    SELECT m.*, 
           u.first_name as sender_first_name, 
           u.last_name as sender_last_name,
           u.role as sender_role,
           (SELECT COUNT(*) FROM messages WHERE parent_message_id = m.id OR id = m.id) as reply_count,
           (SELECT COUNT(*) FROM messages WHERE parent_message_id = m.id AND recipient_id = ? AND is_read = 0) as unread_replies
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE (m.sender_id = ? OR m.recipient_id = ?)
    AND m.parent_message_id IS NULL
    ORDER BY m.created_at DESC
";

$stmt = $conn->prepare($conversations_query);
$stmt->bind_param("iii", $user_id, $user_id, $user_id);
$stmt->execute();
$conversations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get selected conversation thread
$selected_conversation = null;
$conversation_thread = [];

if (isset($_GET['conversation_id'])) {
    $conversation_id = $_GET['conversation_id'];
    
    // Get parent message
    $stmt = $conn->prepare("
        SELECT m.*, 
               u.first_name as sender_first_name, 
               u.last_name as sender_last_name,
               u.role as sender_role
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.id = ?
    ");
    $stmt->bind_param("i", $conversation_id);
    $stmt->execute();
    $selected_conversation = $stmt->get_result()->fetch_assoc();
    
    // Get all replies in thread
    $stmt = $conn->prepare("
        SELECT m.*, 
               u.first_name as sender_first_name, 
               u.last_name as sender_last_name,
               u.role as sender_role
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.parent_message_id = ?
        ORDER BY m.created_at ASC
    ");
    $stmt->bind_param("i", $conversation_id);
    $stmt->execute();
    $conversation_thread = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Mark messages as read
    $stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE (id = ? OR parent_message_id = ?) AND recipient_id = ?");
    $stmt->bind_param("iii", $conversation_id, $conversation_id, $user_id);
    $stmt->execute();
}

// Count unread messages
$unread_count = $conn->query("SELECT COUNT(*) as count FROM messages WHERE recipient_id = $user_id AND is_read = 0")->fetch_assoc()['count'];

ob_start();
?>

<style>
    .message-container {
        height: calc(100vh - 150px);
        overflow-y: auto;
    }
    .conversation-list {
        border-right: 1px solid #dee2e6;
        height: 100%;
        overflow-y: auto;
    }
    .conversation-item {
        padding: 1rem;
        border-bottom: 1px solid #eee;
        cursor: pointer;
        transition: background 0.2s;
    }
    .conversation-item:hover {
        background: #f8f9fa;
    }
    .conversation-item.active {
        background: #e7f3ff;
        border-left: 3px solid #007bff;
    }
    .conversation-item.unread {
        background: #fff3cd;
    }
    .message-thread {
        padding: 1.5rem;
        height: 100%;
        overflow-y: auto;
    }
    .message-bubble {
        padding: 1rem;
        border-radius: 10px;
        margin-bottom: 1rem;
        max-width: 70%;
    }
    .message-bubble.sent {
        background: #007bff;
        color: white;
        margin-left: auto;
    }
    .message-bubble.received {
        background: #f1f3f5;
        color: #000;
    }
    .reply-form {
        border-top: 1px solid #dee2e6;
        padding: 1rem;
        background: white;
    }
</style>

<!-- Statistics Cards -->
<div class="row mb-4 p-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Total Conversations</h6>
                        <h3 class="mb-0"><?php echo count($conversations); ?></h3>
                    </div>
                    <div class="text-primary">
                        <i class="fas fa-comments fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Unread Messages</h6>
                        <h3 class="mb-0"><?php echo $unread_count; ?></h3>
                    </div>
                    <div class="text-warning">
                        <i class="fas fa-envelope fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <button class="btn btn-danger w-100 h-100" data-bs-toggle="modal" data-bs-target="#newMessageModal">
            <i class="fas fa-plus me-2"></i>Send Message
        </button>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show mx-4" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="container-fluid px-4">
    <div class="row message-container">
        <!-- Conversations List -->
        <div class="col-md-4 p-0 conversation-list">
            <div class="p-3 bg-light border-bottom">
                <h5 class="mb-0"><i class="fas fa-inbox me-2"></i>Conversations</h5>
            </div>
            
            <?php if (empty($conversations)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No conversations yet</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newMessageModal">
                        Send Your First Message
                    </button>
                </div>
            <?php else: ?>
                <?php foreach ($conversations as $conv): ?>
                    <div class="conversation-item <?php echo isset($_GET['conversation_id']) && $_GET['conversation_id'] == $conv['id'] ? 'active' : ''; ?> <?php echo $conv['unread_replies'] > 0 ? 'unread' : ''; ?>" 
                         onclick="window.location.href='email.php?conversation_id=<?php echo $conv['id']; ?>'">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <h6 class="mb-1">
                                    <?php echo htmlspecialchars($conv['subject']); ?>
                                    <?php if ($conv['unread_replies'] > 0): ?>
                                        <span class="badge bg-danger"><?php echo $conv['unread_replies']; ?></span>
                                    <?php endif; ?>
                                </h6>
                                <small class="text-muted">
                                    <i class="fas fa-user me-1"></i>
                                    <?php echo htmlspecialchars($conv['sender_first_name'] . ' ' . $conv['sender_last_name']); ?>
                                    <span class="badge bg-<?php echo $conv['sender_role'] == 'admin' ? 'danger' : 'primary'; ?> ms-1">
                                        <?php echo ucfirst($conv['sender_role']); ?>
                                    </span>
                                </small>
                                <p class="mb-0 mt-2 small text-truncate"><?php echo htmlspecialchars(substr($conv['message'], 0, 100)); ?></p>
                            </div>
                            <small class="text-muted ms-2"><?php echo date('M d', strtotime($conv['created_at'])); ?></small>
                        </div>
                        <div class="mt-2">
                            <small class="text-muted">
                                <i class="fas fa-reply me-1"></i><?php echo $conv['reply_count'] - 1; ?> replies
                            </small>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Message Thread -->
        <div class="col-md-8 p-0">
            <?php if ($selected_conversation): ?>
                <div class="p-3 bg-light border-bottom">
                    <h5 class="mb-0"><?php echo htmlspecialchars($selected_conversation['subject']); ?></h5>
                    <small class="text-muted">Started on <?php echo date('F d, Y \a\t g:i A', strtotime($selected_conversation['created_at'])); ?></small>
                </div>
                
                <div class="message-thread">
                    <!-- Original Message -->
                    <div class="message-bubble <?php echo $selected_conversation['sender_id'] == $user_id ? 'sent' : 'received'; ?>">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <strong>
                                <?php echo htmlspecialchars($selected_conversation['sender_first_name'] . ' ' . $selected_conversation['sender_last_name']); ?>
                                <span class="badge bg-<?php echo $selected_conversation['sender_role'] == 'admin' ? 'danger' : 'primary'; ?> ms-1">
                                    <?php echo ucfirst($selected_conversation['sender_role']); ?>
                                </span>
                            </strong>
                            <div class="d-flex align-items-center">
                                <small class="me-2"><?php echo date('M d, Y g:i A', strtotime($selected_conversation['created_at'])); ?></small>
                                <?php if ($selected_conversation['sender_id'] == $user_id): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="delete_message">
                                        <input type="hidden" name="message_id" value="<?php echo $selected_conversation['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this message and all replies?');" title="Delete"><i class="fas fa-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($selected_conversation['message'])); ?></p>
                    </div>
                    
                    <!-- Replies -->
                    <?php foreach ($conversation_thread as $reply): ?>
                        <div class="message-bubble <?php echo $reply['sender_id'] == $user_id ? 'sent' : 'received'; ?>">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <strong>
                                    <?php echo htmlspecialchars($reply['sender_first_name'] . ' ' . $reply['sender_last_name']); ?>
                                    <span class="badge bg-<?php echo $reply['sender_role'] == 'admin' ? 'danger' : 'primary'; ?> ms-1">
                                        <?php echo ucfirst($reply['sender_role']); ?>
                                    </span>
                                </strong>
                                <div class="d-flex align-items-center">
                                    <small class="me-2"><?php echo date('M d, Y g:i A', strtotime($reply['created_at'])); ?></small>
                                    <?php if ($reply['sender_id'] == $user_id): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="delete_message">
                                            <input type="hidden" name="message_id" value="<?php echo $reply['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this reply?');" title="Delete"><i class="fas fa-trash"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($reply['message'])); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Reply Form -->
                <div class="reply-form">
                    <form method="POST">
                        <input type="hidden" name="action" value="send">
                        <input type="hidden" name="parent_id" value="<?php echo $selected_conversation['id']; ?>">
                        <input type="hidden" name="subject" value="Re: <?php echo htmlspecialchars($selected_conversation['subject']); ?>">
                        
                        <div class="input-group">
                            <textarea class="form-control" name="message" rows="3" placeholder="Type your reply..." required></textarea>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-2"></i>Send Reply
                            </button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="d-flex align-items-center justify-content-center h-100">
                    <div class="text-center">
                        <i class="fas fa-envelope-open-text fa-4x text-muted mb-3"></i>
                        <h5 class="text-muted">Select a conversation to view messages</h5>
                        <p class="text-muted">or start a new conversation with admin</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- New Message Modal -->
<div class="modal fade" id="newMessageModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-envelope me-2"></i>Send Message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="send">
                    
                    <div class="mb-3">
                        <label class="form-label">Recipient Email Address *</label>
                        <input type="email" class="form-control" name="recipient_email" placeholder="admin@example.com or leave empty for admin">
                        <small class="form-text text-muted">Enter an email address to send to external recipient, or leave empty to send to admin</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Subject *</label>
                        <input type="text" class="form-control" name="subject" required placeholder="What is this about?">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Message *</label>
                        <textarea class="form-control" name="message" rows="8" required placeholder="Type your message here..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-paper-plane me-2"></i>Send Message
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require_once 'views/layout.php';
?>
