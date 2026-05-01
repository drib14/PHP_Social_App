<?php
require_once __DIR__ . '/src/bootstrap.php';
require_auth();
header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$stmt = db()->prepare('SELECT id, name FROM users WHERE name LIKE :q AND id != :u LIMIT 10');
$stmt->execute(['q' => "%$q%", 'u' => $_SESSION['user_id']]);
$users = $stmt->fetchAll();

echo json_encode($users);
