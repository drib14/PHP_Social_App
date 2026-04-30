<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit();
}

$pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ?")->execute([$_SESSION['user_id']]);
echo json_encode(['success' => true]);
?>