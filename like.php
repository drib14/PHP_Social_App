<?php
require_once 'db.php';

$user_id = $_SESSION['user_id'];
$post_id = $_POST['post_id'];

// Prevent duplicate likes
$check = $conn->prepare("SELECT id FROM likes WHERE user_id=? AND post_id=?");
$check->bind_param("ii", $user_id, $post_id);
$check->execute();
$check->store_result();

if ($check->num_rows == 0) {
    // Like
    $stmt = $conn->prepare("INSERT INTO likes (user_id, post_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $user_id, $post_id);
    $stmt->execute();

    // Get post owner to notify them
    $owner_stmt = $conn->prepare("SELECT user_id FROM posts WHERE id = ?");
    $owner_stmt->bind_param("i", $post_id);
    $owner_stmt->execute();
    $owner_result = $owner_stmt->get_result();

    if ($owner_result->num_rows > 0) {
        $post_owner = $owner_result->fetch_assoc()['user_id'];

        // Only notify if liking someone else's post
        if ($post_owner != $user_id) {
            $type = 'like';
            $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, actor_id, type, reference_id) VALUES (?, ?, ?, ?)");
            $notif_stmt->bind_param("iisi", $post_owner, $user_id, $type, $post_id);
            $notif_stmt->execute();
        }
    }
} else {
    // Unlike
    $del = $conn->prepare("DELETE FROM likes WHERE user_id=? AND post_id=?");
    $del->bind_param("ii", $user_id, $post_id);
    $del->execute();
}

// Redirect back to referring page
$redirect = $_SERVER['HTTP_REFERER'] ?? 'dashboard.php';
header("Location: $redirect");
exit;