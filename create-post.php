<?php
require_once 'db.php';
require_once 'cloudinary_helper.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $content = trim($_POST['content'] ?? '');
    $audience = $_POST['audience'] ?? 'public';
    $group_id = $_POST['group_id'] ?? null;
    $page_id = $_POST['page_id'] ?? null;
    $user_id = $_SESSION['user_id'];

    // Handle Media Upload to Cloudinary
    $media_url = null;
    $media_type = null;
    $media_name = null;

    if (isset($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
        $tmp_name = $_FILES['media']['tmp_name'];
        $media_name = $_FILES['media']['name'];
        $media_type = mime_content_type($tmp_name);

        // Determine Cloudinary resource type
        $resource_type = 'auto';
        if (str_starts_with($media_type, 'image/')) $resource_type = 'image';
        elseif (str_starts_with($media_type, 'video/')) $resource_type = 'video';
        else $resource_type = 'raw';

        $media_url = uploadToCloudinary($tmp_name, $resource_type);
    }

    // Only post if there's either text or media
    if (!empty($content) || $media_url !== null) {
        $stmt = $conn->prepare("INSERT INTO posts (user_id, group_id, page_id, content, audience, media_url, media_type, media_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiisssss", $user_id, $group_id, $page_id, $content, $audience, $media_url, $media_type, $media_name);
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