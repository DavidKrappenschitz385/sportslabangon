<?php
// team/messages.php - Team Messaging System
require_once '../config/database.php';
requireLogin();

$database = new Database();
$db = $database->connect();
$user = getCurrentUser();

$team_id = $_GET['team_id'] ?? null;
$recipient_id = $_GET['recipient_id'] ?? null;
$prefill_message = $_GET['message'] ?? '';

// Handle sending a message
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_message'])) {
    $receiver_id = $_POST['receiver_id'];
    $message = trim($_POST['message']);
    $team_id = $_POST['team_id'] ?: null;
    $attachment_path = null;

    // Handle File Upload
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
        $upload_dir = '../uploads/attachments/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_name = time() . '_' . basename($_FILES['attachment']['name']);
        $target_file = $upload_dir . $file_name;
        $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        // Allow certain file formats
        $allowed_types = ['jpg', 'png', 'jpeg', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt'];
        if (in_array($file_type, $allowed_types)) {
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target_file)) {
                $attachment_path = 'uploads/attachments/' . $file_name;
            }
        }
    }

    if (!empty($message) || $attachment_path) {
        $query = "INSERT INTO messages (sender_id, receiver_id, team_id, message, attachment_path)
                  VALUES (:sender_id, :receiver_id, :team_id, :message, :attachment_path)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':sender_id', $user['id']);
        $stmt->bindParam(':receiver_id', $receiver_id);
        $stmt->bindParam(':team_id', $team_id);
        $stmt->bindParam(':message', $message);
        $stmt->bindParam(':attachment_path', $attachment_path);

        if ($stmt->execute()) {
            // Redirect to prevent resubmission
            if ($team_id) {
                header("Location: messages.php?team_id=$team_id&recipient_id=$receiver_id");
            } else {
                header("Location: messages.php?recipient_id=$receiver_id");
            }
            exit;
        }
    }
}

// Handle deleting a conversation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_conversation'])) {
    $recipient_id_to_delete = $_POST['recipient_id_to_delete'];
    $team_id_to_delete = $_POST['team_id_to_delete'] ?: null;

    $delete_query = "DELETE FROM messages
                     WHERE (sender_id = :user_id AND receiver_id = :recipient_id)
                     OR (sender_id = :recipient_id AND receiver_id = :user_id)";

    if ($team_id_to_delete) {
        $delete_query .= " AND team_id = :team_id";
    }

    $stmt = $db->prepare($delete_query);
    $stmt->bindParam(':user_id', $user['id']);
    $stmt->bindParam(':recipient_id', $recipient_id_to_delete);
    if ($team_id_to_delete) {
        $stmt->bindParam(':team_id', $team_id_to_delete);
    }

    if ($stmt->execute()) {
        header("Location: messages.php");
        exit;
    }
}

// Get conversations list
$conversations_query = "
    SELECT
        u.id as user_id,
        u.first_name,
        u.last_name,
        u.username,
        t.id as team_id,
        t.name as team_name,
        m.message,
        m.created_at,
        m.is_read,
        m.sender_id
    FROM messages m
    JOIN users u ON (m.sender_id = u.id OR m.receiver_id = u.id)
    LEFT JOIN teams t ON m.team_id = t.id
    WHERE (m.sender_id = :user_id OR m.receiver_id = :user_id)
    AND u.id != :user_id
    AND m.id IN (
        SELECT MAX(id)
        FROM messages
        WHERE sender_id = :user_id OR receiver_id = :user_id
        GROUP BY LEAST(sender_id, receiver_id), GREATEST(sender_id, receiver_id), team_id
    )
    ORDER BY m.created_at DESC
";
$stmt = $db->prepare($conversations_query);
$stmt->bindParam(':user_id', $user['id']);
$stmt->execute();
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get messages for current conversation
$messages = [];
$recipient = null;

if ($recipient_id) {
    // Get recipient details
    $stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->bindParam(':id', $recipient_id);
    $stmt->execute();
    $recipient = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get messages
    $query = "SELECT m.*, u.first_name, u.last_name
              FROM messages m
              JOIN users u ON m.sender_id = u.id
              WHERE (m.sender_id = :user_id AND m.receiver_id = :recipient_id)
              OR (m.sender_id = :recipient_id AND m.receiver_id = :user_id)";

    if ($team_id) {
        $query .= " AND m.team_id = :team_id";
    }

    $query .= " ORDER BY m.created_at ASC";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user['id']);
    $stmt->bindParam(':recipient_id', $recipient_id);
    if ($team_id) {
        $stmt->bindParam(':team_id', $team_id);
    }
    $stmt->execute();
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Mark as read
    $update = "UPDATE messages SET is_read = 1
               WHERE receiver_id = :user_id AND sender_id = :recipient_id AND is_read = 0";
    if ($team_id) {
        $update .= " AND team_id = :team_id";
    }
    $stmt = $db->prepare($update);
    $stmt->bindParam(':user_id', $user['id']);
    $stmt->bindParam(':recipient_id', $recipient_id);
    if ($team_id) {
        $stmt->bindParam(':team_id', $team_id);
    }
    $stmt->execute();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Messages - Sports League</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; }
        .container { display: flex; height: 100vh; max-width: 1200px; margin: 0 auto; background: white; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .sidebar { width: 300px; border-right: 1px solid #ddd; display: flex; flex-direction: column; }
        .sidebar-header { padding: 20px; border-bottom: 1px solid #ddd; background: #f8f9fa; }
        .conversation-list { overflow-y: auto; flex: 1; }
        .conversation-item { padding: 15px; border-bottom: 1px solid #eee; cursor: pointer; transition: background 0.2s; }
        .conversation-item:hover { background: #f8f9fa; }
        .conversation-item.active { background: #e3f2fd; border-left: 4px solid #007bff; }
        .conversation-item h4 { margin: 0 0 5px 0; color: #333; }
        .conversation-item p { margin: 0; color: #666; font-size: 0.9em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .conversation-item .time { font-size: 0.8em; color: #999; float: right; }
        .unread { font-weight: bold; color: #000; }

        .delete-btn {
            background: none;
            border: none;
            color: #dc3545;
            cursor: pointer;
            font-size: 0.9em;
            padding: 5px;
            float: right;
            margin-left: 5px;
        }
        .delete-btn:hover {
            text-decoration: underline;
        }

        .chat-area { flex: 1; display: flex; flex-direction: column; }
        .chat-header { padding: 15px 20px; border-bottom: 1px solid #ddd; background: #fff; display: flex; justify-content: space-between; align-items: center; }
        .messages-list { flex: 1; overflow-y: auto; padding: 20px; background: #f5f5f5; display: flex; flex-direction: column; }
        .message-bubble { max-width: 70%; padding: 10px 15px; border-radius: 15px; margin-bottom: 10px; position: relative; }
        .message-sent { align-self: flex-end; background: #007bff; color: white; border-bottom-right-radius: 5px; }
        .message-received { align-self: flex-start; background: white; color: #333; border-bottom-left-radius: 5px; box-shadow: 0 1px 2px rgba(0,0,0,0.1); }
        .message-time { font-size: 0.7em; margin-top: 5px; opacity: 0.7; text-align: right; }

        .attachment { margin-top: 5px; padding: 5px; background: rgba(0,0,0,0.1); border-radius: 5px; }
        .attachment a { color: inherit; text-decoration: none; display: flex; align-items: center; gap: 5px; }
        .attachment i { font-size: 1.2em; }
        .message-sent .attachment { background: rgba(255,255,255,0.2); }

        .input-area { padding: 20px; background: white; border-top: 1px solid #ddd; }
        .input-form { display: flex; gap: 10px; align-items: center; }
        .input-form textarea { flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 20px; resize: none; height: 40px; font-family: inherit; }
        .input-form textarea:focus { outline: none; border-color: #007bff; }
        .file-upload-label { cursor: pointer; color: #666; padding: 10px; border-radius: 50%; transition: background 0.2s; }
        .file-upload-label:hover { background: #f0f0f0; color: #333; }
        .send-btn { background: #007bff; color: white; border: none; padding: 0 20px; border-radius: 20px; cursor: pointer; font-weight: bold; height: 40px; }
        .send-btn:hover { background: #0056b3; }

        .empty-state { flex: 1; display: flex; align-items: center; justify-content: center; color: #999; flex-direction: column; }
        .back-link { display: inline-block; padding: 5px 10px; text-decoration: none; color: #666; margin-bottom: 10px; }

        @media (max-width: 768px) {
            .container { flex-direction: column; }
            .sidebar { width: 100%; height: <?php echo $recipient_id ? '0' : '100%'; ?>; display: <?php echo $recipient_id ? 'none' : 'flex'; ?>; }
            .chat-area { height: <?php echo $recipient_id ? '100%' : '0'; ?>; display: <?php echo $recipient_id ? 'flex' : 'none'; ?>; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <div class="sidebar-header">
                <a href="../dashboard.php" class="back-link">← Dashboard</a>
                <h2>Messages</h2>
            </div>
            <div class="conversation-list">
                <?php if (empty($conversations)): ?>
                    <div style="padding: 20px; text-align: center; color: #666;">No conversations yet.</div>
                <?php else: ?>
                    <?php foreach ($conversations as $conv): ?>
                        <div class="conversation-item <?php echo ($conv['user_id'] == $recipient_id) ? 'active' : ''; ?>"
                             onclick="window.location.href='?recipient_id=<?php echo $conv['user_id']; ?><?php echo $conv['team_id'] ? '&team_id='.$conv['team_id'] : ''; ?>'">
                            <span class="time"><?php echo date('M j', strtotime($conv['created_at'])); ?></span>
                            <h4><?php echo htmlspecialchars($conv['first_name'] . ' ' . $conv['last_name']); ?></h4>
                            <?php if ($conv['team_name']): ?>
                                <small style="color: #666; display: block; margin-bottom: 2px;">Re: <?php echo htmlspecialchars($conv['team_name']); ?></small>
                            <?php endif; ?>
                            <p class="<?php echo ($conv['is_read'] == 0 && $conv['sender_id'] != $user['id']) ? 'unread' : ''; ?>">
                                <?php echo ($conv['sender_id'] == $user['id'] ? 'You: ' : '') . htmlspecialchars($conv['message']); ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="chat-area">
            <?php if ($recipient): ?>
                <div class="chat-header">
                    <div>
                        <a href="messages.php" class="back-link" style="display: none;">← Back</a>
                        <h3 style="margin: 0;"><?php echo htmlspecialchars($recipient['first_name'] . ' ' . $recipient['last_name']); ?></h3>
                        <?php if ($recipient['role'] == 'admin'): ?>
                            <span style="font-size: 0.8em; color: #dc3545; font-weight: bold;">Administrator</span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this entire conversation? This cannot be undone.');">
                            <input type="hidden" name="recipient_id_to_delete" value="<?php echo $recipient['id']; ?>">
                            <input type="hidden" name="team_id_to_delete" value="<?php echo $team_id; ?>">
                            <button type="submit" name="delete_conversation" class="delete-btn">
                                <i class="fas fa-trash"></i> Delete Conversation
                            </button>
                        </form>
                    </div>
                </div>

                <div class="messages-list" id="messagesList">
                    <?php foreach ($messages as $msg): ?>
                        <div class="message-bubble <?php echo ($msg['sender_id'] == $user['id']) ? 'message-sent' : 'message-received'; ?>">
                            <?php echo nl2br(htmlspecialchars($msg['message'])); ?>

                            <?php if (!empty($msg['attachment_path'])): ?>
                                <div class="attachment">
                                    <a href="../<?php echo htmlspecialchars($msg['attachment_path']); ?>" download target="_blank">
                                        <i class="fas fa-file-download"></i> Download Attachment
                                    </a>
                                </div>
                            <?php endif; ?>

                            <div class="message-time"><?php echo date('g:i A', strtotime($msg['created_at'])); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="input-area">
                    <form method="POST" class="input-form" enctype="multipart/form-data">
                        <input type="hidden" name="receiver_id" value="<?php echo $recipient['id']; ?>">
                        <input type="hidden" name="team_id" value="<?php echo $team_id; ?>">

                        <label for="attachment" class="file-upload-label" title="Attach file">
                            <i class="fas fa-paperclip"></i>
                        </label>
                        <input type="file" id="attachment" name="attachment" style="display: none;" onchange="updateFileLabel(this)">

                        <textarea name="message" placeholder="Type a message..." required><?php echo htmlspecialchars($prefill_message); ?></textarea>
                        <button type="submit" name="send_message" class="send-btn">Send</button>
                    </form>
                    <div id="file-name" style="font-size: 0.8em; color: #666; margin-left: 40px; margin-top: 5px;"></div>
                </div>

                <script>
                    var messagesList = document.getElementById('messagesList');
                    messagesList.scrollTop = messagesList.scrollHeight;

                    function updateFileLabel(input) {
                        var fileName = input.files[0] ? input.files[0].name : '';
                        document.getElementById('file-name').textContent = fileName;
                    }

                    // Show back button on mobile
                    if (window.innerWidth <= 768) {
                        document.querySelector('.chat-header .back-link').style.display = 'inline-block';
                    }
                </script>
            <?php else: ?>
                <div class="empty-state">
                    <h3>Select a conversation</h3>
                    <p>Choose a conversation from the list or start a new one.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
