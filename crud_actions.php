<?php
require_once 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id = $_SESSION['user_id'];

    if ($action === 'delete_post') {
        $post_id = $_POST['post_id'];

        $stmt = $conn->prepare("DELETE FROM posts WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $post_id, $user_id);
        $stmt->execute();

    } elseif ($action === 'edit_post') {
        $post_id = $_POST['post_id'];
        $content = trim($_POST['content']);

        if (!empty($content)) {
            $stmt = $conn->prepare("UPDATE posts SET content = ?, is_edited = TRUE WHERE id = ? AND user_id = ?");
            $stmt->bind_param("sii", $content, $post_id, $user_id);
            $stmt->execute();
        }

    } elseif ($action === 'delete_comment') {
        $comment_id = $_POST['comment_id'];

        $stmt = $conn->prepare("DELETE FROM comments WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $comment_id, $user_id);
        $stmt->execute();

    } elseif ($action === 'edit_comment') {
        $comment_id = $_POST['comment_id'];
        $content = trim($_POST['content']);

        if (!empty($content)) {
            $stmt = $conn->prepare("UPDATE comments SET content = ?, is_edited = TRUE WHERE id = ? AND user_id = ?");
            $stmt->bind_param("sii", $content, $comment_id, $user_id);
            $stmt->execute();
        }
    }
}

// Redirect back to referring page
$redirect = $_SERVER['HTTP_REFERER'] ?? 'dashboard.php';
header("Location: $redirect");
exit;
