<?php
require_once 'db.php';
include 'partials/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$current_user_id = $_SESSION['user_id'];

// Default to global feed
$feed_type = $_GET['feed'] ?? 'global';

if ($feed_type === 'following') {
    $sql = "
        SELECT posts.*, users.name, users.id as post_user_id,
        (SELECT COUNT(*) FROM likes WHERE post_id = posts.id) as like_count,
        (SELECT COUNT(*) FROM comments WHERE post_id = posts.id) as comment_count,
        (SELECT COUNT(*) FROM likes WHERE post_id = posts.id AND user_id = ?) as user_liked
        FROM posts
        JOIN users ON users.id = posts.user_id
        JOIN follows ON follows.following_id = posts.user_id
        WHERE follows.follower_id = ?
        ORDER BY posts.created_at DESC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $current_user_id, $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $sql = "
        SELECT posts.*, users.name, users.id as post_user_id,
        (SELECT COUNT(*) FROM likes WHERE post_id = posts.id) as like_count,
        (SELECT COUNT(*) FROM comments WHERE post_id = posts.id) as comment_count,
        (SELECT COUNT(*) FROM likes WHERE post_id = posts.id AND user_id = ?) as user_liked
        FROM posts
        JOIN users ON users.id = posts.user_id
        ORDER BY posts.created_at DESC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
}
?>

<!-- Post Creation Box -->
<div class="card p-3 mb-4">
    <form action="create-post.php" method="POST">
        <div class="mb-3">
            <textarea name="content" class="form-control border-0" rows="3" placeholder="What's on your mind?" required></textarea>
        </div>
        <div class="text-end">
            <button type="submit" class="btn btn-primary px-4 rounded-pill">Post</button>
        </div>
    </form>
</div>

<!-- Feed Tabs -->
<ul class="nav nav-pills mb-4">
    <li class="nav-item">
        <a class="nav-link rounded-pill <?= $feed_type === 'global' ? 'active' : '' ?>" href="dashboard.php?feed=global">Global</a>
    </li>
    <li class="nav-item">
        <a class="nav-link rounded-pill <?= $feed_type === 'following' ? 'active' : '' ?>" href="dashboard.php?feed=following">Following</a>
    </li>
</ul>

<?php if ($result->num_rows == 0): ?>
    <div class="text-center text-muted my-5">
        <i class="fa-solid fa-wind fs-1 mb-3"></i>
        <p>No posts to show.</p>
    </div>
<?php endif; ?>

<?php while ($post = $result->fetch_assoc()): ?>
<div class="card p-4 mb-4">
    <div class="d-flex align-items-center mb-3">
        <a href="user_profile.php?id=<?= $post['post_user_id'] ?>">
            <div class="avatar-placeholder me-3">
                <?= strtoupper(substr($post['name'], 0, 1)) ?>
            </div>
        </a>
        <div>
            <a href="user_profile.php?id=<?= $post['post_user_id'] ?>" class="text-white fw-bold text-decoration-none">
                <?= htmlspecialchars($post['name']) ?>
            </a>
            <div class="text-muted small"><?= date('M j, Y, g:i a', strtotime($post['created_at'])) ?></div>
        </div>
    </div>

    <p class="fs-5 mb-4"><?= nl2br(htmlspecialchars($post['content'])) ?></p>

    <div class="d-flex gap-3 border-top border-secondary pt-3">
        <form method="POST" action="like.php">
            <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
            <button class="btn btn-sm <?= $post['user_liked'] ? 'btn-danger' : 'btn-outline-danger' ?> rounded-pill px-3">
                <i class="fa-heart <?= $post['user_liked'] ? 'fa-solid' : 'fa-regular' ?>"></i> <?= $post['like_count'] ?>
            </button>
        </form>

        <button class="btn btn-sm btn-outline-primary rounded-pill px-3" onclick="document.getElementById('comments-<?= $post['id'] ?>').classList.toggle('d-none')">
            <i class="fa-regular fa-comment"></i> <?= $post['comment_count'] ?>
        </button>
    </div>

    <!-- Comments Section -->
    <div id="comments-<?= $post['id'] ?>" class="d-none mt-4">
        <!-- Add Comment Form -->
        <form method="POST" action="comment.php" class="d-flex gap-2 mb-3">
            <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
            <input type="text" name="content" class="form-control rounded-pill" placeholder="Write a comment..." required>
            <button type="submit" class="btn btn-primary rounded-pill"><i class="fa-solid fa-paper-plane"></i></button>
        </form>

        <!-- Fetch and Display Comments -->
        <?php
        $comments_stmt = $conn->prepare("
            SELECT comments.*, users.name, users.id as comment_user_id
            FROM comments
            JOIN users ON users.id = comments.user_id
            WHERE post_id = ?
            ORDER BY created_at ASC
        ");
        $comments_stmt->bind_param("i", $post['id']);
        $comments_stmt->execute();
        $comments_result = $comments_stmt->get_result();
        ?>

        <?php while ($comment = $comments_result->fetch_assoc()): ?>
            <div class="d-flex mb-2 align-items-start">
                <a href="user_profile.php?id=<?= $comment['comment_user_id'] ?>">
                    <div class="avatar-placeholder me-2 mt-1" style="width: 24px; height: 24px; font-size: 0.8rem;">
                        <?= strtoupper(substr($comment['name'], 0, 1)) ?>
                    </div>
                </a>
                <div class="bg-dark rounded-3 p-2 px-3">
                    <a href="user_profile.php?id=<?= $comment['comment_user_id'] ?>" class="text-white fw-bold small text-decoration-none d-block">
                        <?= htmlspecialchars($comment['name']) ?>
                    </a>
                    <span class="small"><?= htmlspecialchars($comment['content']) ?></span>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</div>
<?php endwhile; ?>

<?php include 'partials/footer.php'; ?>