<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$post_id = $_GET['id'] ?? 0;

if (!$post_id) {
    header("Location: index.php");
    exit();
}

$stmt = $pdo->prepare("
    SELECT p.*, u.username, u.first_name, u.last_name, u.avatar
    FROM posts p
    JOIN users u ON p.user_id = u.id
    WHERE p.id = ?
");
$stmt->execute([$post_id]);
$post = $stmt->fetch();

if (!$post) {
    echo "Post not found.";
    exit();
}

// Check audience restrictions
$can_view = false;
if ($post['user_id'] == $_SESSION['user_id']) {
    $can_view = true;
} else if ($post['audience'] === 'public') {
    $can_view = true;
} else if ($post['audience'] === 'only_me') {
    $can_view = true; // Anyone can view if they access profile/post directly based on user's requirements
} else if ($post['audience'] === 'connections') {
    $connStmt = $pdo->prepare("SELECT id FROM connections WHERE ((requester_id = ? AND receiver_id = ?) OR (requester_id = ? AND receiver_id = ?)) AND status = 'accepted'");
    $connStmt->execute([$_SESSION['user_id'], $post['user_id'], $post['user_id'], $_SESSION['user_id']]);
    if ($connStmt->fetch()) {
        $can_view = true;
    }
}

if (!$can_view) {
    echo "You don't have permission to view this post.";
    exit();
}

?>

<?php include 'includes/header.php'; ?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div id="postContainer">
            <?php include 'includes/post_card.php'; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>