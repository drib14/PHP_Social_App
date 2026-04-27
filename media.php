<?php
require_once 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

$type = $_GET['type'] ?? '';
$id = $_GET['id'] ?? 0;

if (!in_array($type, ['post', 'message']) || !$id) {
    header("HTTP/1.0 404 Not Found");
    exit;
}

if ($type === 'post') {
    $stmt = $conn->prepare("SELECT media_data, media_type, media_name FROM posts WHERE id = ?");
} else {
    $stmt = $conn->prepare("SELECT media_data, media_type, media_name FROM messages WHERE id = ?");
}

$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("HTTP/1.0 404 Not Found");
    exit;
}

$row = $result->fetch_assoc();

if (!$row['media_data']) {
    header("HTTP/1.0 404 Not Found");
    exit;
}

// Set appropriate headers
header("Content-Type: " . $row['media_type']);
// If it's a file that should be downloaded (not an image/video), force download
if (!str_starts_with($row['media_type'], 'image/') && !str_starts_with($row['media_type'], 'video/')) {
    header('Content-Disposition: attachment; filename="' . basename($row['media_name']) . '"');
}

echo $row['media_data'];
exit;