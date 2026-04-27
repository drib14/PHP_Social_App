<?php
require_once 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $post_id = $_POST['post_id'] ?? null;
    $content = trim($_POST['content'] ?? '');

    if ($post_id && !empty($content)) {
        $stmt = $conn->prepare("INSERT INTO comments (user_id, post_id, content) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $user_id, $post_id, $content);
        $stmt->execute();

        // Get post owner to notify them
        $owner_stmt = $conn->prepare("SELECT user_id FROM posts WHERE id = ?");
        $owner_stmt->bind_param("i", $post_id);
        $owner_stmt->execute();
        $owner_result = $owner_stmt->get_result();

        if ($owner_result->num_rows > 0) {
            $post_owner = $owner_result->fetch_assoc()['user_id'];

            // Only notify if commenting on someone else's post
            if ($post_owner != $user_id) {
                $type = 'comment';
                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, actor_id, type, reference_id) VALUES (?, ?, ?, ?)");
                $notif_stmt->bind_param("iisi", $post_owner, $user_id, $type, $post_id);
                $notif_stmt->execute();
            }
        }
    }
}

// Redirect back to referring page (dashboard or profile)
$redirect = $_SERVER['HTTP_REFERER'] ?? 'dashboard.php';
header("Location: $redirect");
exit;