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
        // Fetch 1-on-1 users
        $sql = "
            SELECT u.id, u.name, 'user' as type,
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
                SELECT sender_id FROM messages WHERE receiver_id = ? AND chat_group_id IS NULL
                UNION
                SELECT receiver_id FROM messages WHERE sender_id = ? AND chat_group_id IS NULL
            )
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiiiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $conversations = [];
        while ($row = $result->fetch_assoc()) {
            $conversations[] = $row;
        }

        // Fetch Chat Groups the user belongs to
        $group_sql = "
            SELECT cg.id, cg.name, 'group' as type,
                   (SELECT message FROM messages WHERE chat_group_id = cg.id ORDER BY created_at DESC LIMIT 1) as last_message,
                   1 as last_read_status,
                   (SELECT created_at FROM messages WHERE chat_group_id = cg.id ORDER BY created_at DESC LIMIT 1) as last_time
            FROM chat_groups cg
            JOIN chat_group_members cgm ON cg.id = cgm.chat_group_id
            WHERE cgm.user_id = ?
        ";
        $group_stmt = $conn->prepare($group_sql);
        $group_stmt->bind_param("i", $user_id);
        $group_stmt->execute();
        $group_result = $group_stmt->get_result();
        while ($row = $group_result->fetch_assoc()) {
            $conversations[] = $row;
        }

        // Sort both by time
        usort($conversations, function($a, $b) {
            return strtotime($b['last_time']) - strtotime($a['last_time']);
        });

        // Also fetch mutual followers/following to start new chats
        $friends_sql = "
            SELECT u.id, u.name, 'user' as type
            FROM users u
            JOIN follows f ON f.following_id = u.id
            WHERE f.follower_id = ?
        ";
        $friends_stmt = $conn->prepare($friends_sql);
        $friends_stmt->bind_param("i", $user_id);
        $friends_stmt->execute();
        $friends_result = $friends_stmt->get_result();

        while ($row = $friends_result->fetch_assoc()) {
            // Only add if not already in conversations
            $exists = false;
            foreach ($conversations as $c) {
                if ($c['id'] == $row['id'] && $c['type'] == 'user') $exists = true;
            }
            if (!$exists) {
                $row['last_message'] = 'Start a conversation';
                $row['last_read_status'] = 1;
                $row['last_time'] = '1970-01-01 00:00:00';
                $conversations[] = $row; // Add to end
            }
        }

        echo json_encode($conversations);
        exit;
    }
    elseif ($action === 'search_users') {
        $query = $_GET['query'] ?? '';

        if (empty($query)) {
            echo json_encode([]);
            exit;
        }

        $search_term = "%{$query}%";
        $sql = "SELECT id, name, profile_pic FROM users WHERE name LIKE ? AND id != ? LIMIT 10";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $search_term, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'initial' => strtoupper(substr($row['name'], 0, 1)),
                'profile_pic' => $row['profile_pic'],
                'type' => 'user'
            ];
        }

        echo json_encode($users);
        exit;
    }
    elseif ($action === 'get_messages') {
        $chat_type = $_GET['chat_type'] ?? 'user';
        $target_id = $_GET['target_id'] ?? 0;

        if (!$target_id) {
            http_response_code(400);
            exit;
        }

        if ($chat_type === 'user') {
            // Mark unread messages as read
            $mark_read = $conn->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?");
            $mark_read->bind_param("ii", $target_id, $user_id);
            $mark_read->execute();

            $sql = "
                SELECT m.id, m.sender_id, m.receiver_id, m.message, m.media_url, m.media_type, m.media_name, m.created_at, m.is_deleted, m.is_edited, u.name as sender_name
                FROM messages m
                JOIN users u ON u.id = m.sender_id
                WHERE (m.sender_id = ? AND m.receiver_id = ? AND m.chat_group_id IS NULL)
                   OR (m.sender_id = ? AND m.receiver_id = ? AND m.chat_group_id IS NULL)
                ORDER BY m.created_at ASC
            ";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiii", $user_id, $target_id, $target_id, $user_id);
        } else {
            // Group Chat
            $sql = "
                SELECT m.id, m.sender_id, m.chat_group_id, m.message, m.media_url, m.media_type, m.media_name, m.created_at, m.is_deleted, m.is_edited, u.name as sender_name
                FROM messages m
                JOIN users u ON u.id = m.sender_id
                WHERE m.chat_group_id = ?
                ORDER BY m.created_at ASC
            ";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $target_id);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $messages = [];
        while ($row = $result->fetch_assoc()) {
            if ($row['is_deleted']) {
                $row['message'] = "<em>Message unsent</em>";
                $row['has_media'] = false;
            } else {
                $row['has_media'] = !empty($row['media_type']);
            }
            $row['is_mine'] = $row['sender_id'] == $user_id;
            $messages[] = $row;
        }

        echo json_encode($messages);
        exit;
    }
}
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'send_message') {
        $receiver_id = $_POST['receiver_id'] ?? null;
        $chat_group_id = $_POST['chat_group_id'] ?? null;
        $message = trim($_POST['message'] ?? '');

        if (!$receiver_id && !$chat_group_id) {
            http_response_code(400);
            exit;
        }

        // If receiver ID is passed but it's empty string/0, convert to null
        $receiver_id = $receiver_id ? $receiver_id : null;
        $chat_group_id = $chat_group_id ? $chat_group_id : null;

        require_once 'cloudinary_helper.php';

        $media_url = null;
        $media_type = null;
        $media_name = null;

        if (isset($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
            $tmp_name = $_FILES['media']['tmp_name'];
            $media_name = $_FILES['media']['name'];
            $media_type = mime_content_type($tmp_name);

            $resource_type = 'auto';
            if (str_starts_with($media_type, 'image/')) $resource_type = 'image';
            elseif (str_starts_with($media_type, 'video/')) $resource_type = 'video';
            else $resource_type = 'raw';

            $media_url = uploadToCloudinary($tmp_name, $resource_type);
        }

        if ($message !== '' || $media_url !== null) {
            $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, chat_group_id, message, media_url, media_type, media_name) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iiissss", $user_id, $receiver_id, $chat_group_id, $message, $media_url, $media_type, $media_name);
            $stmt->execute();

            echo json_encode(['status' => 'success', 'message_id' => $conn->insert_id]);
        } else {
            echo json_encode(['status' => 'empty']);
        }
        exit;
    } elseif ($action === 'unsend_message') {
        $msg_id = $_POST['message_id'] ?? 0;

        if ($msg_id) {
            $stmt = $conn->prepare("UPDATE messages SET is_deleted = 1, message = NULL, media_url = NULL, media_type = NULL, media_name = NULL WHERE id = ? AND sender_id = ?");
            $stmt->bind_param("ii", $msg_id, $user_id);
            $stmt->execute();
            echo json_encode(['status' => 'success']);
        }
        exit;
    }
}

http_response_code(400);
echo json_encode(['error' => 'Invalid action']);