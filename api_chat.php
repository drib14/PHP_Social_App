<?php
require_once 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'get_conversations') {
        // Fetch users the current user has messaged or received messages from
        $sql = "
            SELECT u.id, u.name,
                   (SELECT message FROM messages
                    WHERE (sender_id = ? AND receiver_id = u.id) OR (sender_id = u.id AND receiver_id = ?)
                    ORDER BY created_at DESC LIMIT 1) as last_message,
                   (SELECT is_read FROM messages
                    WHERE sender_id = u.id AND receiver_id = ?
                    ORDER BY created_at DESC LIMIT 1) as last_read_status,
                   (SELECT created_at FROM messages
                    WHERE (sender_id = ? AND receiver_id = u.id) OR (sender_id = u.id AND receiver_id = ?)
                    ORDER BY created_at DESC LIMIT 1) as last_time
            FROM users u
            WHERE u.id != ? AND u.id IN (
                SELECT sender_id FROM messages WHERE receiver_id = ?
                UNION
                SELECT receiver_id FROM messages WHERE sender_id = ?
            )
            ORDER BY last_time DESC
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiiiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $conversations = [];
        while ($row = $result->fetch_assoc()) {
            $conversations[] = $row;
        }

        // Also fetch mutual followers/following to start new chats
        $friends_sql = "
            SELECT u.id, u.name
            FROM users u
            JOIN follows f ON f.following_id = u.id
            WHERE f.follower_id = ?
        ";
        $friends_stmt = $conn->prepare($friends_sql);
        $friends_stmt->bind_param("i", $user_id);
        $friends_stmt->execute();
        $friends_result = $friends_stmt->get_result();
        $friends = [];
        while ($row = $friends_result->fetch_assoc()) {
            // Only add if not already in conversations
            $exists = false;
            foreach ($conversations as $c) {
                if ($c['id'] == $row['id']) $exists = true;
            }
            if (!$exists) {
                $row['last_message'] = 'Start a conversation';
                $row['last_read_status'] = 1;
                $conversations[] = $row;
            }
        }

        echo json_encode($conversations);
        exit;
    }
    elseif ($action === 'get_messages') {
        $other_user_id = $_GET['user_id'] ?? 0;

        if (!$other_user_id) {
            http_response_code(400);
            exit;
        }

        // Mark unread messages as read
        $mark_read = $conn->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?");
        $mark_read->bind_param("ii", $other_user_id, $user_id);
        $mark_read->execute();

        $sql = "
            SELECT id, sender_id, receiver_id, message, media_type, media_name, created_at
            FROM messages
            WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
            ORDER BY created_at ASC
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiii", $user_id, $other_user_id, $other_user_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $messages = [];
        while ($row = $result->fetch_assoc()) {
            // We do not send media_data itself in JSON, we will use the media.php endpoint
            $row['has_media'] = !empty($row['media_type']);
            $row['is_mine'] = $row['sender_id'] == $user_id;
            $messages[] = $row;
        }

        echo json_encode($messages);
        exit;
    }
}
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'send_message') {
        $receiver_id = $_POST['receiver_id'] ?? 0;
        $message = trim($_POST['message'] ?? '');

        if (!$receiver_id) {
            http_response_code(400);
            exit;
        }

        $media_data = null;
        $media_type = null;
        $media_name = null;

        if (isset($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
            $tmp_name = $_FILES['media']['tmp_name'];
            $media_name = $_FILES['media']['name'];
            $media_type = mime_content_type($tmp_name);
            $media_data = file_get_contents($tmp_name);
        }

        if ($message !== '' || $media_data !== null) {
            $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message, media_data, media_type, media_name) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iissss", $user_id, $receiver_id, $message, $media_data, $media_type, $media_name);
            if ($media_data !== null) {
                $stmt->send_long_data(3, $media_data);
            }
            $stmt->execute();

            echo json_encode(['status' => 'success', 'message_id' => $conn->insert_id]);
        } else {
            echo json_encode(['status' => 'empty']);
        }
        exit;
    }
}

http_response_code(400);
echo json_encode(['error' => 'Invalid action']);