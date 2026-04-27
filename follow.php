<?php
require_once 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$follower_id = $_SESSION['user_id'];
$following_id = $_POST['following_id'] ?? null;

if ($following_id && $follower_id != $following_id) {
    // Check if already following
    $check_stmt = $conn->prepare("SELECT id FROM follows WHERE follower_id = ? AND following_id = ?");
    $check_stmt->bind_param("ii", $follower_id, $following_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows > 0) {
        // Unfollow
        $del_stmt = $conn->prepare("DELETE FROM follows WHERE follower_id = ? AND following_id = ?");
        $del_stmt->bind_param("ii", $follower_id, $following_id);
        $del_stmt->execute();
    } else {
        // Follow
        $ins_stmt = $conn->prepare("INSERT INTO follows (follower_id, following_id) VALUES (?, ?)");
        $ins_stmt->bind_param("ii", $follower_id, $following_id);
        $ins_stmt->execute();

        // Create Notification for the follow
        $type = 'follow';
        $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, actor_id, type) VALUES (?, ?, ?)");
        $notif_stmt->bind_param("iis", $following_id, $follower_id, $type);
        $notif_stmt->execute();
    }
}

// Redirect back to the profile of the user we just followed/unfollowed
header("Location: user_profile.php?id=" . $following_id);
exit;
