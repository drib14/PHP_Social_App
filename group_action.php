<?php
require_once 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $group_id = $_POST['group_id'] ?? 0;
    $user_id = $_SESSION['user_id'];

    if ($action === 'join' && $group_id) {
        try {
            $stmt = $conn->prepare("INSERT INTO group_members (group_id, user_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $group_id, $user_id);
            $stmt->execute();
        } catch (Exception $e) { } // Ignore duplicate joins
    } elseif ($action === 'leave' && $group_id) {
        $stmt = $conn->prepare("DELETE FROM group_members WHERE group_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $group_id, $user_id);
        $stmt->execute();
    }
}

$redirect = $_SERVER['HTTP_REFERER'] ?? 'groups.php';
header("Location: $redirect");
exit;