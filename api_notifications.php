<?php
require_once 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

$user_id = $_SESSION['user_id'];

// Get all unread notifications
$sql = "
    SELECT n.*, u.name as actor_name
    FROM notifications n
    JOIN users u ON u.id = n.actor_id
    WHERE n.user_id = ? AND n.is_read = FALSE
    ORDER BY n.created_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}

echo json_encode($notifications);
exit;