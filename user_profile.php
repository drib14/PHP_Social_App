<?php
require_once 'db.php';
include 'partials/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$current_user_id = $_SESSION['user_id'];
$profile_user_id = $_GET['id'] ?? $current_user_id;

// Fetch user info
$stmt = $conn->prepare("SELECT id, name, email FROM users WHERE id=?");
$stmt->bind_param("i", $profile_user_id);
$stmt->execute();
$user_result = $stmt->get_result();

if ($user_result->num_rows == 0) {
    echo "<div class='alert alert-danger'>User not found.</div>";
    include 'partials/footer.php';
    exit;
}

$user = $user_result->fetch_assoc();

// Check follow status if it's not the current user
$is_following = false;
if ($current_user_id != $profile_user_id) {
    $follow_stmt = $conn->prepare("SELECT id FROM follows WHERE follower_id = ? AND following_id = ?");
    $follow_stmt->bind_param("ii", $current_user_id, $profile_user_id);
    $follow_stmt->execute();
    if ($follow_stmt->get_result()->num_rows > 0) {
        $is_following = true;
    }
}

// Fetch followers/following counts
$followers_stmt = $conn->prepare("SELECT COUNT(*) as count FROM follows WHERE following_id = ?");
$followers_stmt->bind_param("i", $profile_user_id);
$followers_stmt->execute();
$followers_count = $followers_stmt->get_result()->fetch_assoc()['count'];

$following_stmt = $conn->prepare("SELECT COUNT(*) as count FROM follows WHERE follower_id = ?");
$following_stmt->bind_param("i", $profile_user_id);
$following_stmt->execute();
$following_count = $following_stmt->get_result()->fetch_assoc()['count'];

// Fetch user's posts
$posts_stmt = $conn->prepare("
    SELECT posts.*,
    (SELECT COUNT(*) FROM likes WHERE post_id = posts.id) as like_count,
    (SELECT COUNT(*) FROM comments WHERE post_id = posts.id) as comment_count
    FROM posts
    WHERE user_id = ?
    ORDER BY created_at DESC
");
$posts_stmt->bind_param("i", $profile_user_id);
$posts_stmt->execute();
$posts_result = $posts_stmt->get_result();
?>

<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card p-4 text-center">
            <div class="avatar-placeholder mx-auto mb-3" style="width: 80px; height: 80px; font-size: 2rem;">
                <?= strtoupper(substr($user['name'], 0, 1)) ?>
            </div>
            <h4 class="mb-1"><?= htmlspecialchars($user['name']) ?></h4>
            <p class="text-muted small mb-3"><?= htmlspecialchars($user['email']) ?></p>

            <div class="d-flex justify-content-center gap-3 mb-4">
                <div>
                    <strong class="d-block"><?= $followers_count ?></strong>
                    <small class="text-muted">Followers</small>
                </div>
                <div>
                    <strong class="d-block"><?= $following_count ?></strong>
                    <small class="text-muted">Following</small>
                </div>
            </div>

            <?php if ($current_user_id == $profile_user_id): ?>
                <a href="edit-profile.php" class="btn btn-outline-light w-100 rounded-pill"><i class="fa-solid fa-pen me-2"></i>Edit Profile</a>
            <?php else: ?>
                <form method="POST" action="follow.php">
                    <input type="hidden" name="following_id" value="<?= $user['id'] ?>">
                    <?php if ($is_following): ?>
                        <button class="btn btn-secondary w-100 rounded-pill">Unfollow</button>
                    <?php else: ?>
                        <button class="btn btn-primary w-100 rounded-pill"><i class="fa-solid fa-user-plus me-2"></i>Follow</button>
                    <?php endif; ?>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-md-8">
        <h5 class="mb-3">Posts by <?= htmlspecialchars($user['name']) ?></h5>
        <?php if ($posts_result->num_rows == 0): ?>
            <div class="card p-4 text-center text-muted">
                <i class="fa-solid fa-ghost fs-1 mb-3"></i>
                <p>No posts yet.</p>
            </div>
        <?php else: ?>
            <?php while ($post = $posts_result->fetch_assoc()): ?>
                <div class="card p-3 mb-3">
                    <div class="d-flex align-items-center mb-2">
                        <div class="avatar-placeholder me-2" style="width: 32px; height: 32px; font-size: 1rem;">
                            <?= strtoupper(substr($user['name'], 0, 1)) ?>
                        </div>
                        <strong><?= htmlspecialchars($user['name']) ?></strong>
                        <small class="text-muted ms-auto"><?= date('M j, Y, g:i a', strtotime($post['created_at'])) ?></small>
                    </div>

                    <p class="mt-2 mb-3"><?= nl2br(htmlspecialchars($post['content'])) ?></p>

                    <div class="d-flex gap-2 border-top border-secondary pt-3 mt-3">
                        <form method="POST" action="like.php" class="d-inline">
                            <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger rounded-pill">
                                <i class="fa-heart <?= ($post['like_count'] > 0) ? 'fa-solid' : 'fa-regular' ?>"></i> <?= $post['like_count'] ?>
                            </button>
                        </form>
                        <button class="btn btn-sm btn-outline-primary rounded-pill" onclick="document.getElementById('comments-<?= $post['id'] ?>').classList.toggle('d-none')">
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
        <?php endif; ?>
    </div>
</div>

<?php include 'partials/footer.php'; ?>