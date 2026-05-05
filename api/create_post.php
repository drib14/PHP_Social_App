<?php
// api/create_post.php
session_start();
require_once '../includes/db.php';
require_once '../includes/cloudinary.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$conn = getDbConnection();
$user_id = $_SESSION['user_id'];
$content = trim($_POST['content'] ?? '');

if (empty($content) && empty($_FILES['media']['name'])) {
    echo json_encode(['success' => false, 'message' => 'Post cannot be empty']);
    exit;
}

$media_url = null;
if (isset($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
    $media_url = uploadToCloudinary($_FILES['media']['tmp_name']);
    if (!$media_url) {
        echo json_encode(['success' => false, 'message' => 'Failed to upload media']);
        exit;
    }
}

$stmt = $conn->prepare("INSERT INTO posts (user_id, content, media_url) VALUES (?, ?, ?)");
$stmt->bind_param("iss", $user_id, $content, $media_url);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>