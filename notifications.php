<?php
require_once __DIR__ . '/src/bootstrap.php';
require_auth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Mark notifications as read
    $stmt = db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = :u AND is_read = 0');
    $stmt->execute(['u' => $_SESSION['user_id']]);
    echo json_encode(['success' => true]);
    exit;
}

header('Content-Type: application/json');
$u = current_user();

$stmt = db()->prepare('SELECT n.*, a.name as actor_name, a.avatar_url FROM notifications n JOIN users a ON a.id = n.actor_id WHERE n.user_id = :u ORDER BY n.id DESC LIMIT 20');
$stmt->execute(['u' => $u['id']]);
$notifications = $stmt->fetchAll();

$unreadCountStmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :u AND is_read = 0');
$unreadCountStmt->execute(['u' => $u['id']]);
$unreadCount = $unreadCountStmt->fetchColumn();

echo json_encode([
    'notifications' => $notifications,
    'unread_count' => (int)$unreadCount
]);
