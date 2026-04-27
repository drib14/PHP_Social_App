<?php
require_once 'db.php';
include 'partials/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Fetch posts + like count
$sql = "
SELECT posts.*, users.name,
(SELECT COUNT(*) FROM likes WHERE post_id = posts.id) as like_count
FROM posts
JOIN users ON users.id = posts.user_id
ORDER BY posts.created_at DESC
";

$result = $conn->query($sql);
?>

<a href="create-post.php" class="btn btn-primary mb-3 w-100">Create Post</a>

<?php while ($post = $result->fetch_assoc()): ?>
<div class="card p-3 mb-3">
    <strong><?= htmlspecialchars($post['name']) ?></strong>
    <p><?= nl2br(htmlspecialchars($post['content'])) ?></p>

    <div class="d-flex justify-content-between">
        <small><?= $post['created_at'] ?></small>

        <form method="POST" action="like.php">
            <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
            <button class="btn btn-sm btn-outline-light">
                ❤️ <?= $post['like_count'] ?>
            </button>
        </form>
    </div>
</div>
<?php endwhile; ?>

<?php include 'partials/footer.php'; ?>