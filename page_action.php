<?php
require_once 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $page_id = $_POST['page_id'] ?? 0;
    $user_id = $_SESSION['user_id'];

    if ($action === 'follow' && $page_id) {
        try {
            $stmt = $conn->prepare("INSERT INTO page_followers (page_id, user_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $page_id, $user_id);
            $stmt->execute();
        } catch (Exception $e) { } // Ignore duplicate follows
    } elseif ($action === 'unfollow' && $page_id) {
        $stmt = $conn->prepare("DELETE FROM page_followers WHERE page_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $page_id, $user_id);
        $stmt->execute();
    }
}

$redirect = $_SERVER['HTTP_REFERER'] ?? 'pages.php';
header("Location: $redirect");
exit;