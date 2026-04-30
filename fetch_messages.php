<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit();
}

$user_id = $_SESSION['user_id'];
$chat_user_id = $_GET['user'] ?? 0;
$last_id = $_GET['last_id'] ?? 0;

if (!$chat_user_id) exit;

$stmt = $pdo->prepare("
    SELECT * FROM messages
    WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
    AND id > ?
    ORDER BY created_at ASC
");
$stmt->execute([$user_id, $chat_user_id, $chat_user_id, $user_id, $last_id]);
$messages = $stmt->fetchAll();

header('Content-Type: application/json');
echo json_encode(['messages' => $messages]);
exit();
?>