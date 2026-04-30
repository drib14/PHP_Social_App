<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/cloudinary.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = trim($_POST['content'] ?? '');
    $audience = $_POST['audience'] ?? 'public';
    $original_post_id = $_POST['original_post_id'] ?? null;
    $user_id = $_SESSION['user_id'];

    // Convert empty original_post_id to null
    if (empty($original_post_id)) {
        $original_post_id = null;
    }

    $media_url = null;
    $media_type = 'none';

    if (isset($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['media']['tmp_name'];
        $file_type = mime_content_type($file_tmp);

        if (strpos($file_type, 'image') === 0) {
            $media_type = 'image';
        } elseif (strpos($file_type, 'video') === 0) {
            $media_type = 'video';
        }

        if ($media_type !== 'none') {
            $resource_type = ($media_type === 'video') ? 'video' : 'image';
            $uploaded = uploadToCloudinary($file_tmp, $resource_type);
            if ($uploaded) {
                $media_url = $uploaded;
            } else {
                $_SESSION['error'] = "Media upload failed.";
                header("Location: index.php");
                exit();
            }
        }
    }

    if (!empty($content) || $media_url !== null || $original_post_id !== null) {
        $stmt = $pdo->prepare("INSERT INTO posts (user_id, content, media_url, media_type, audience, original_post_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $content, $media_url, $media_type, $audience, $original_post_id]);

        $post_id = $pdo->lastInsertId();

        // Check for mentions in the content (@username)
        preg_match_all('/@([a-zA-Z0-9_]+)/', $content, $matches);
        if (!empty($matches[1])) {
            $mentioned_usernames = array_unique($matches[1]);
            foreach ($mentioned_usernames as $username) {
                $uStmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $uStmt->execute([$username]);
                $u = $uStmt->fetch();
                if ($u && $u['id'] != $user_id) {
                    $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, sender_id, type, target_id) VALUES (?, ?, 'mention_post', ?)");
                    $notifStmt->execute([$u['id'], $user_id, $post_id]);
                }
            }
        }
    }

    header("Location: index.php");
    exit();
} else {
    header("Location: index.php");
    exit();
}
?>