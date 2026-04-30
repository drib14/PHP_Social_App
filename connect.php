<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $target_id = $_POST['user_id'] ?? 0;
    $user_id = $_SESSION['user_id'];

    if (!$target_id || $target_id == $user_id) {
        header("Location: profile.php?id=$target_id");
        exit();
    }

    try {
        if ($action === 'send') {
            // Check if connection already exists
            $stmt = $pdo->prepare("SELECT id FROM connections WHERE (requester_id = ? AND receiver_id = ?) OR (requester_id = ? AND receiver_id = ?)");
            $stmt->execute([$user_id, $target_id, $target_id, $user_id]);
            if (!$stmt->fetch()) {
                $insert = $pdo->prepare("INSERT INTO connections (requester_id, receiver_id, status) VALUES (?, ?, 'pending')");
                $insert->execute([$user_id, $target_id]);

                // Optional: Insert notification here later
                $notif = $pdo->prepare("INSERT INTO notifications (user_id, sender_id, type, target_id) VALUES (?, ?, 'connection_request', ?)");
                $notif->execute([$target_id, $user_id, $target_id]);
            }
        } elseif ($action === 'accept') {
            $update = $pdo->prepare("UPDATE connections SET status = 'accepted' WHERE requester_id = ? AND receiver_id = ? AND status = 'pending'");
            $update->execute([$target_id, $user_id]);

            // Optional: Notification for accepted request
            $notif = $pdo->prepare("INSERT INTO notifications (user_id, sender_id, type, target_id) VALUES (?, ?, 'connection_accepted', ?)");
            $notif->execute([$target_id, $user_id, $user_id]);
        } elseif ($action === 'decline') {
            $delete = $pdo->prepare("DELETE FROM connections WHERE requester_id = ? AND receiver_id = ? AND status = 'pending'");
            $delete->execute([$target_id, $user_id]);
        } elseif ($action === 'remove') {
            $delete = $pdo->prepare("DELETE FROM connections WHERE ((requester_id = ? AND receiver_id = ?) OR (requester_id = ? AND receiver_id = ?)) AND status = 'accepted'");
            $delete->execute([$user_id, $target_id, $target_id, $user_id]);
        }
    } catch (PDOException $e) {
        // Handle error if needed
    }

    header("Location: profile.php?id=$target_id");
    exit();
} else {
    header("Location: index.php");
    exit();
}
?>