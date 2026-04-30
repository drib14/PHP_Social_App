<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit();
}

$user_id = $_SESSION['user_id'];
$last_id = $_GET['last_id'] ?? 0;

$stmt = $pdo->prepare("
    SELECT n.*, u.username, u.first_name, u.last_name, u.avatar
    FROM notifications n
    JOIN users u ON n.sender_id = u.id
    WHERE n.user_id = ? AND n.id > ?
    ORDER BY n.created_at DESC
    LIMIT 20
");
$stmt->execute([$user_id, $last_id]);
$notifications = $stmt->fetchAll();

// Get unread count
$cStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = FALSE");
$cStmt->execute([$user_id]);
$unread_count = $cStmt->fetchColumn();

header('Content-Type: application/json');
echo json_encode([
    'notifications' => $notifications,
    'unread_count' => $unread_count,
    'max_id' => $notifications ? max(array_column($notifications, 'id')) : $last_id
]);
exit();
?>