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

<?php
// Get current user name for avatar placeholder
$user_stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
$user_stmt->bind_param("i", $current_user_id);
$user_stmt->execute();
$current_user_name = $user_stmt->get_result()->fetch_assoc()['name'];
?>

<div class="feed-container">
    <!-- Post Creation Box (Facebook Style) -->
    <div class="card p-3 mb-4">
        <div class="d-flex align-items-center mb-3">
            <div class="avatar-placeholder me-2">
                <?= strtoupper(substr($current_user_name, 0, 1)) ?>
            </div>
            <div class="create-post-trigger text-muted w-100" data-bs-toggle="modal" data-bs-target="#createPostModal">
                What's on your mind, <?= explode(' ', htmlspecialchars($current_user_name))[0] ?>?
            </div>
        </div>
        <hr class="border-secondary my-0">
        <div class="d-flex pt-2">
            <button class="action-btn text-muted" data-bs-toggle="modal" data-bs-target="#createPostModal">
                <i class="fa-solid fa-video text-danger me-2"></i> Live Video
            </button>
            <button class="action-btn text-muted" data-bs-toggle="modal" data-bs-target="#createPostModal">
                <i class="fa-regular fa-image text-success me-2"></i> Photo/video
            </button>
            <button class="action-btn text-muted d-none d-md-block" data-bs-toggle="modal" data-bs-target="#createPostModal">
                <i class="fa-regular fa-face-smile text-warning me-2"></i> Feeling/activity
            </button>
        </div>
    </div>

    <!-- Create Post Modal -->
    <div class="modal fade" id="createPostModal" tabindex="-1" aria-labelledby="createPostModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                <div class="modal-header border-bottom border-secondary">
                    <h5 class="modal-title w-100 text-center fw-bold" id="createPostModalLabel">Create post</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="avatar-placeholder me-2">
                            <?= strtoupper(substr($current_user_name, 0, 1)) ?>
                        </div>
                        <div class="fw-bold"><?= htmlspecialchars($current_user_name) ?></div>
                    </div>
                    <form action="create-post.php" method="POST">
                        <textarea name="content" class="form-control border-0 bg-transparent fs-5 px-0 text-white" rows="4" placeholder="What's on your mind, <?= explode(' ', htmlspecialchars($current_user_name))[0] ?>?" required style="resize: none;"></textarea>
                        <button type="submit" class="btn btn-primary w-100 mt-3 py-2">Post</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php if ($result->num_rows == 0): ?>
        <div class="text-center text-muted my-5">
            <i class="fa-solid fa-wind fs-1 mb-3"></i>
            <p>No posts to show.</p>
        </div>
    <?php endif; ?>

    <?php while ($post = $result->fetch_assoc()): ?>
    <div class="card mb-4 pb-2">
        <div class="p-3 pb-2 d-flex align-items-center">
            <a href="user_profile.php?id=<?= $post['post_user_id'] ?>">
                <div class="avatar-placeholder me-2">
                    <?= strtoupper(substr($post['name'], 0, 1)) ?>
                </div>
            </a>
            <div>
                <a href="user_profile.php?id=<?= $post['post_user_id'] ?>" class="text-white fw-bold text-decoration-none">
                    <?= htmlspecialchars($post['name']) ?>
                </a>
                <div class="text-muted" style="font-size: 0.8rem;"><?= date('M j \a\t g:i a', strtotime($post['created_at'])) ?> · <i class="fa-solid fa-earth-americas"></i></div>
            </div>
        </div>

        <div class="px-3 pb-2 fs-6">
            <?= nl2br(htmlspecialchars($post['content'])) ?>
        </div>

        <?php if ($post['like_count'] > 0 || $post['comment_count'] > 0): ?>
        <div class="px-3 py-2 text-muted d-flex justify-content-between border-bottom border-secondary" style="font-size: 0.9rem;">
            <div>
                <?php if ($post['like_count'] > 0): ?>
                <i class="fa-solid fa-thumbs-up text-primary bg-white rounded-circle p-1" style="font-size: 0.6rem;"></i> <?= $post['like_count'] ?>
                <?php endif; ?>
            </div>
            <div>
                <?php if ($post['comment_count'] > 0): ?>
                <span class="ms-3 cursor-pointer" onclick="document.getElementById('comments-<?= $post['id'] ?>').classList.remove('d-none')">
                    <?= $post['comment_count'] ?> comments
                </span>
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="border-bottom border-secondary mx-3"></div>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div class="d-flex px-3 py-1">
            <form method="POST" action="like.php" class="w-50 me-1">
                <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                <button class="action-btn <?= $post['user_liked'] ? 'active-like' : '' ?>">
                    <i class="<?= $post['user_liked'] ? 'fa-solid' : 'fa-regular' ?> fa-thumbs-up me-1"></i> Like
                </button>
            </form>

            <button class="action-btn w-50" onclick="document.getElementById('comments-<?= $post['id'] ?>').classList.toggle('d-none')">
                <i class="fa-regular fa-message me-1"></i> Comment
            </button>
        </div>

        <div class="border-bottom border-secondary mx-3 mb-2"></div>

        <!-- Comments Section -->
        <div id="comments-<?= $post['id'] ?>" class="px-3 d-none">
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
                <div class="d-flex mb-3 align-items-start">
                    <a href="user_profile.php?id=<?= $comment['comment_user_id'] ?>">
                        <div class="avatar-placeholder me-2" style="width: 32px; height: 32px; font-size: 0.9rem;">
                            <?= strtoupper(substr($comment['name'], 0, 1)) ?>
                        </div>
                    </a>
                    <div style="background-color: var(--bg-hover); border-radius: 18px; padding: 8px 12px; display: inline-block; max-width: calc(100% - 40px);">
                        <a href="user_profile.php?id=<?= $comment['comment_user_id'] ?>" class="text-white fw-bold text-decoration-none d-block" style="font-size: 0.85rem;">
                            <?= htmlspecialchars($comment['name']) ?>
                        </a>
                        <span style="font-size: 0.9rem; word-break: break-word;"><?= htmlspecialchars($comment['content']) ?></span>
                    </div>
                </div>
            <?php endwhile; ?>

            <!-- Add Comment Form -->
            <div class="d-flex gap-2 mt-3 mb-2 align-items-start">
                <div class="avatar-placeholder" style="width: 32px; height: 32px; font-size: 0.9rem; flex-shrink: 0;">
                    <?= strtoupper(substr($current_user_name, 0, 1)) ?>
                </div>
                <form method="POST" action="comment.php" class="w-100 position-relative">
                    <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                    <input type="text" name="content" class="form-control" placeholder="Write a comment..." required style="padding-right: 40px; border-radius: 20px;">
                    <button type="submit" class="btn btn-link position-absolute end-0 top-50 translate-middle-y text-primary text-decoration-none" style="color: var(--accent-color) !important;">
                        <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php endwhile; ?>
</div>

<?php include 'partials/footer.php'; ?>