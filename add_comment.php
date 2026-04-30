<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_id = $_POST['post_id'] ?? 0;
    $parent_comment_id = $_POST['parent_comment_id'] ?? null;
    $content = trim($_POST['content'] ?? '');
    $user_id = $_SESSION['user_id'];

    if (empty($parent_comment_id)) {
        $parent_comment_id = null;
    }

    if ($post_id && !empty($content)) {
        $stmt = $pdo->prepare("INSERT INTO comments (post_id, user_id, parent_comment_id, content) VALUES (?, ?, ?, ?)");
        $stmt->execute([$post_id, $user_id, $parent_comment_id, $content]);
        $comment_id = $pdo->lastInsertId();

        // Mentions notification inside comment
        preg_match_all('/@([a-zA-Z0-9_]+)/', $content, $matches);
        if (!empty($matches[1])) {
            $mentioned_usernames = array_unique($matches[1]);
            foreach ($mentioned_usernames as $username) {
                $uStmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $uStmt->execute([$username]);
                $u = $uStmt->fetch();
                if ($u && $u['id'] != $user_id) {
                    $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, sender_id, type, target_id) VALUES (?, ?, 'mention_comment', ?)");
                    $notifStmt->execute([$u['id'], $user_id, $post_id]);
                }
            }
        }

        // Notify post owner or parent comment owner
        $receiver_id = 0;
        if ($parent_comment_id) {
            $rStmt = $pdo->prepare("SELECT user_id FROM comments WHERE id = ?");
            $rStmt->execute([$parent_comment_id]);
            $res = $rStmt->fetch();
            $receiver_id = $res['user_id'] ?? 0;
        } else {
            $rStmt = $pdo->prepare("SELECT user_id FROM posts WHERE id = ?");
            $rStmt->execute([$post_id]);
            $res = $rStmt->fetch();
            $receiver_id = $res['user_id'] ?? 0;
        }

        if ($receiver_id && $receiver_id != $user_id) {
            $nStmt = $pdo->prepare("INSERT INTO notifications (user_id, sender_id, type, target_id) VALUES (?, ?, 'comment', ?)");
            $nStmt->execute([$receiver_id, $user_id, $post_id]);
        }
    }

    // In a real app we might redirect back to exact post, but for now index is fine
    header("Location: index.php");
    exit();
}
?>