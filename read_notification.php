<?php
require_once 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

if (isset($_POST['mark_all'])) {
    // Mark all as read
    $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $redirect = $_SERVER['HTTP_REFERER'] ?? 'dashboard.php';
    header("Location: $redirect");
    exit;
} elseif (isset($_GET['id'])) {
    // Mark specific notification as read
    $notif_id = $_GET['id'];
    $redirect = $_GET['redirect'] ?? 'dashboard.php';

    // Simple validation for redirect to prevent open redirect (only allow relative paths)
    if (!preg_match('/^[a-zA-Z0-9_\-\.\?\=]+$/', $redirect)) {
        $redirect = 'dashboard.php';
    }

    $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notif_id, $user_id);
    $stmt->execute();

    header("Location: " . $redirect);
    exit;
}

header("Location: dashboard.php");
exit;