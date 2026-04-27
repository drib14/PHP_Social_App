<?php
require_once 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $content = trim($_POST['content'] ?? '');
    $audience = $_POST['audience'] ?? 'public';
    $user_id = $_SESSION['user_id'];

    // Handle Media Upload
    $media_data = null;
    $media_type = null;
    $media_name = null;

    if (isset($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
        $tmp_name = $_FILES['media']['tmp_name'];
        $media_name = $_FILES['media']['name'];
        $media_type = mime_content_type($tmp_name); // safer than trusting $_FILES['type']
        $media_data = file_get_contents($tmp_name);
    }

    // Only post if there's either text or media
    if (!empty($content) || $media_data !== null) {
        $stmt = $conn->prepare("INSERT INTO posts (user_id, content, audience, media_data, media_type, media_name) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssss", $user_id, $content, $audience, $media_data, $media_type, $media_name);
        if ($media_data !== null) {
            $stmt->send_long_data(3, $media_data); // Required for LONGBLOB
        }
        $stmt->execute();
    }
}

// Redirect back to referring page (dashboard or profile)
$redirect = $_SERVER['HTTP_REFERER'] ?? 'dashboard.php';
if (!headers_sent()) {
    header("Location: $redirect");
} else {
    echo "<script>window.location.href='$redirect';</script>";
}
exit;