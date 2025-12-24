<?php
// team/messages.php - Team Messaging System
require_once '../config/database.php';
requireLogin();

$database = new Database();
$db = $database->connect();
$user = getCurrentUser();

$team_id = $_GET['team_id'] ?? null;
$recipient_id = $_GET['recipient_id'] ?? null;

// Handle sending a message
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_message'])) {
    $receiver_id = $_POST['receiver_id'];
    $message = trim($_POST['message']);
    $team_id = $_POST['team_id'] ?: null;

    if (!empty($message)) {
        $query = "INSERT INTO messages (sender_id, receiver_id, team_id, message)
                  VALUES (:sender_id, :receiver_id, :team_id, :message)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':sender_id', $user['id']);
        $stmt->bindParam(':receiver_id', $receiver_id);
        $stmt->bindParam(':team_id', $team_id);
        $stmt->bindParam(':message', $message);

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

        .chat-area { flex: 1; display: flex; flex-direction: column; }
        .chat-header { padding: 15px 20px; border-bottom: 1px solid #ddd; background: #fff; display: flex; justify-content: space-between; align-items: center; }
        .messages-list { flex: 1; overflow-y: auto; padding: 20px; background: #f5f5f5; display: flex; flex-direction: column; }
        .message-bubble { max-width: 70%; padding: 10px 15px; border-radius: 15px; margin-bottom: 10px; position: relative; }
        .message-sent { align-self: flex-end; background: #007bff; color: white; border-bottom-right-radius: 5px; }
        .message-received { align-self: flex-start; background: white; color: #333; border-bottom-left-radius: 5px; box-shadow: 0 1px 2px rgba(0,0,0,0.1); }
        .message-time { font-size: 0.7em; margin-top: 5px; opacity: 0.7; text-align: right; }

        .input-area { padding: 20px; background: white; border-top: 1px solid #ddd; }
        .input-form { display: flex; gap: 10px; }
        .input-form textarea { flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 20px; resize: none; height: 40px; font-family: inherit; }
        .input-form textarea:focus { outline: none; border-color: #007bff; }
        .send-btn { background: #007bff; color: white; border: none; padding: 0 20px; border-radius: 20px; cursor: pointer; font-weight: bold; }
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
                    </div>
                </div>

                <div class="messages-list" id="messagesList">
                    <?php foreach ($messages as $msg): ?>
                        <div class="message-bubble <?php echo ($msg['sender_id'] == $user['id']) ? 'message-sent' : 'message-received'; ?>">
                            <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                            <div class="message-time"><?php echo date('g:i A', strtotime($msg['created_at'])); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="input-area">
                    <form method="POST" class="input-form">
                        <input type="hidden" name="receiver_id" value="<?php echo $recipient['id']; ?>">
                        <input type="hidden" name="team_id" value="<?php echo $team_id; ?>">
                        <textarea name="message" placeholder="Type a message..." required></textarea>
                        <button type="submit" name="send_message" class="send-btn">Send</button>
                    </form>
                </div>

                <script>
                    var messagesList = document.getElementById('messagesList');
                    messagesList.scrollTop = messagesList.scrollHeight;

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
