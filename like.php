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
    $stmt = $conn->prepare("INSERT INTO likes (user_id, post_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $user_id, $post_id);
    $stmt->execute();
}

header("Location: dashboard.php");