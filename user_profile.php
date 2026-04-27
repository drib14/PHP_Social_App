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

<!-- Profile Header (Facebook Style) -->
<div class="card mb-4" style="overflow: hidden;">
    <!-- Cover Photo Area -->
    <div style="height: 250px; background: linear-gradient(135deg, #1e293b, var(--bg-hover)); border-bottom: 1px solid var(--border-color); position: relative;">
        <!-- Placeholder for Cover Photo -->
        <div class="position-absolute bottom-0 end-0 p-3">
            <?php if ($current_user_id == $profile_user_id): ?>
                <button class="btn btn-sm btn-secondary"><i class="fa-solid fa-camera me-1"></i> Edit Cover Photo</button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Profile Info Area -->
    <div class="px-4 pb-4 position-relative" style="margin-top: -60px;">
        <div class="d-flex flex-column flex-md-row align-items-md-end gap-3">
            <!-- Profile Picture -->
            <div class="position-relative">
                <div class="avatar-placeholder rounded-circle border border-4 border-dark" style="width: 140px; height: 140px; font-size: 3rem; background-color: var(--accent-color); border-color: var(--bg-secondary) !important;">
                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                </div>
                <?php if ($current_user_id == $profile_user_id): ?>
                <div class="position-absolute bottom-0 end-0 bg-secondary rounded-circle d-flex align-items-center justify-content-center cursor-pointer" style="width: 36px; height: 36px; right: 8px !important; bottom: 8px !important;">
                    <i class="fa-solid fa-camera"></i>
                </div>
                <?php endif; ?>
            </div>

            <!-- Name & Meta -->
            <div class="flex-grow-1 pb-2 text-center text-md-start mt-3 mt-md-0">
                <h1 class="fw-bold mb-0"><?= htmlspecialchars($user['name']) ?></h1>
                <div class="text-muted fw-semibold">
                    <?= $followers_count ?> followers · <?= $following_count ?> following
                </div>
            </div>

            <!-- Actions -->
            <div class="pb-2 d-flex gap-2 justify-content-center justify-content-md-end w-100 w-md-auto mt-3 mt-md-0">
                <?php if ($current_user_id == $profile_user_id): ?>
                    <a href="create-post.php" class="btn btn-primary"><i class="fa-solid fa-plus me-1"></i> Add to story</a>
                    <a href="edit-profile.php" class="btn btn-secondary"><i class="fa-solid fa-pen me-1"></i> Edit profile</a>
                <?php else: ?>
                    <form method="POST" action="follow.php" class="m-0">
                        <input type="hidden" name="following_id" value="<?= $user['id'] ?>">
                        <?php if ($is_following): ?>
                            <button class="btn btn-secondary"><i class="fa-solid fa-user-check me-1"></i> Following</button>
                        <?php else: ?>
                            <button class="btn btn-primary"><i class="fa-solid fa-user-plus me-1"></i> Follow</button>
                        <?php endif; ?>
                    </form>
                    <button class="btn btn-secondary"><i class="fa-brands fa-facebook-messenger me-1"></i> Message</button>
                <?php endif; ?>
            </div>
        </div>

        <hr class="border-secondary mt-4 mb-0">

        <!-- Tabs -->
        <div class="d-flex gap-4 mt-2 px-2 fw-semibold text-muted">
            <div class="py-3 text-primary border-bottom border-3 border-primary" style="color: var(--accent-color) !important; border-color: var(--accent-color) !important;">Posts</div>
            <div class="py-3 cursor-pointer hover-bg-light rounded px-3">About</div>
            <div class="py-3 cursor-pointer hover-bg-light rounded px-3">Friends</div>
            <div class="py-3 cursor-pointer hover-bg-light rounded px-3">Photos</div>
        </div>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-md-5 d-none d-md-block">
        <!-- Intro Card -->
        <div class="card p-3 mb-3">
            <h5 class="fw-bold mb-3">Intro</h5>
            <p class="text-center text-muted mb-3"><?= htmlspecialchars($user['email']) ?></p>
            <button class="btn btn-secondary w-100 mb-3 fw-bold">Edit bio</button>
            <div class="d-flex align-items-center mb-3 text-muted">
                <i class="fa-solid fa-clock me-2 fs-5"></i> Joined <?= date('F Y') ?>
            </div>
            <button class="btn btn-secondary w-100 fw-bold">Edit details</button>
        </div>
    </div>

    <div class="col-md-7 feed-container pt-0">
        <?php if ($posts_result->num_rows == 0): ?>
            <div class="card p-4 text-center text-muted">
                <i class="fa-solid fa-ghost fs-1 mb-3"></i>
                <p class="fw-bold fs-5 text-white">No posts available</p>
            </div>
        <?php else: ?>
            <?php while ($post = $posts_result->fetch_assoc()): ?>
                <div class="card mb-4 pb-2">
                    <div class="p-3 pb-2 d-flex align-items-center">
                        <div class="avatar-placeholder me-2">
                            <?= strtoupper(substr($user['name'], 0, 1)) ?>
                        </div>
                        <div>
                            <div class="text-white fw-bold text-decoration-none">
                                <?= htmlspecialchars($user['name']) ?>
                            </div>
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
                            <!-- Need to check if user liked this specifically, missing from original profile SQL but we fallback -->
                            <button class="action-btn">
                                <i class="fa-regular fa-thumbs-up me-1"></i> Like
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
                        <?php
                        // Get current user name for comment input placeholder
                        $user_stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
                        $user_stmt->bind_param("i", $current_user_id);
                        $user_stmt->execute();
                        $current_user_name = $user_stmt->get_result()->fetch_assoc()['name'];
                        ?>
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
        <?php endif; ?>
    </div>
</div>

<?php include 'partials/footer.php'; ?>