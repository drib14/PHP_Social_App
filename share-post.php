<?php
require_once 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $content = trim($_POST['content'] ?? '');
    $audience = $_POST['audience'] ?? 'public';
    $shared_post_id = $_POST['shared_post_id'] ?? null;
    $user_id = $_SESSION['user_id'];

    if ($shared_post_id) {
        $stmt = $conn->prepare("INSERT INTO posts (user_id, content, audience, shared_post_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("issi", $user_id, $content, $audience, $shared_post_id);
        $stmt->execute();
    }
}

// Redirect back to referring page
$redirect = $_SERVER['HTTP_REFERER'] ?? 'dashboard.php';
header("Location: $redirect");
exit;