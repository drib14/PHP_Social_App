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
$stmt = $conn->prepare("SELECT id, name, email, bio, profile_pic, cover_photo FROM users WHERE id=?");
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

// Enforce Audience rules for user profile
// If viewing your own profile: see everything (public, followers, only_me)
// If viewing someone else AND following them: see public, followers (strictly NO only_me)
// If viewing someone else AND NOT following them: see public (strictly NO only_me)
if ($current_user_id == $profile_user_id) {
    $posts_sql = "
        SELECT posts.*,
        (SELECT COUNT(*) FROM likes WHERE post_id = posts.id) as like_count,
        (SELECT COUNT(*) FROM comments WHERE post_id = posts.id) as comment_count
        FROM posts
        WHERE user_id = ? AND group_id IS NULL
        ORDER BY created_at DESC
    ";
} else if ($is_following) {
    $posts_sql = "
        SELECT posts.*,
        (SELECT COUNT(*) FROM likes WHERE post_id = posts.id) as like_count,
        (SELECT COUNT(*) FROM comments WHERE post_id = posts.id) as comment_count
        FROM posts
        WHERE user_id = ? AND group_id IS NULL AND (audience = 'public' OR audience = 'followers')
        ORDER BY created_at DESC
    ";
} else {
    $posts_sql = "
        SELECT posts.*,
        (SELECT COUNT(*) FROM likes WHERE post_id = posts.id) as like_count,
        (SELECT COUNT(*) FROM comments WHERE post_id = posts.id) as comment_count
        FROM posts
        WHERE user_id = ? AND group_id IS NULL AND audience = 'public'
        ORDER BY created_at DESC
    ";
}

$posts_stmt = $conn->prepare($posts_sql);
$posts_stmt->bind_param("i", $profile_user_id);
$posts_stmt->execute();
$posts_result = $posts_stmt->get_result();
?>

<!-- Profile Header (Facebook Style) -->
<div class="card mb-4" style="overflow: hidden;">
    <!-- Cover Photo Area -->
    <?php
        // We use the new user fields directly
        $cover_url = !empty($user['cover_photo']) ? $user['cover_photo'] : '';
        $profile_url = !empty($user['profile_pic']) ? $user['profile_pic'] : '';
    ?>
    <div style="height: 250px; background: linear-gradient(135deg, #1e293b, var(--bg-hover)); border-bottom: 1px solid var(--border-color); position: relative; <?= $cover_url ? 'background-image: url('.htmlspecialchars($cover_url).'); background-size: cover; background-position: center;' : '' ?>">
        <div class="position-absolute bottom-0 end-0 p-3">
            <?php if ($current_user_id == $profile_user_id): ?>
                <a href="edit-profile.php" class="btn btn-sm btn-secondary"><i class="fa-solid fa-camera me-1"></i> Edit Cover Photo</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Profile Info Area -->
    <div class="px-4 pb-4 position-relative" style="margin-top: -60px;">
        <div class="d-flex flex-column flex-md-row align-items-md-end gap-3">
            <!-- Profile Picture -->
            <div class="position-relative">
                <?php if ($profile_url): ?>
                    <img src="<?= htmlspecialchars($profile_url) ?>" class="rounded-circle border border-4 border-dark object-fit-cover bg-dark" style="width: 140px; height: 140px; border-color: var(--bg-secondary) !important;">
                <?php else: ?>
                    <div class="avatar-placeholder rounded-circle border border-4 border-dark" style="width: 140px; height: 140px; font-size: 3rem; background-color: var(--accent-color); border-color: var(--bg-secondary) !important;">
                        <?= strtoupper(substr($user['name'], 0, 1)) ?>
                    </div>
                <?php endif; ?>
                <?php if ($current_user_id == $profile_user_id): ?>
                <a href="edit-profile.php" class="position-absolute bottom-0 end-0 bg-secondary rounded-circle d-flex align-items-center justify-content-center cursor-pointer text-white text-decoration-none" style="width: 36px; height: 36px; right: 8px !important; bottom: 8px !important;">
                    <i class="fa-solid fa-camera"></i>
                </a>
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
                    <!-- "Add to story" omitted from MVP to avoid feature confusion -->
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
                    <button class="btn btn-secondary" onclick="document.getElementById('main-chat-trigger').click(); setTimeout(() => openConversation(<?= $user['id'] ?>, '<?= addslashes($user['name']) ?>', '<?= strtoupper(substr($user['name'], 0, 1)) ?>', 'user'), 300);"><i class="fa-brands fa-facebook-messenger me-1"></i> Message</button>
                <?php endif; ?>
            </div>
        </div>

        <hr class="border-secondary mt-4 mb-0">

        <!-- Tabs -->
        <div class="d-flex gap-4 mt-2 px-2 fw-semibold text-muted">
            <div class="py-3 text-primary border-bottom border-3 border-primary" style="color: var(--accent-color) !important; border-color: var(--accent-color) !important;">Posts</div>
            <div class="py-3 text-muted px-3">About</div>
            <div class="py-3 text-muted px-3">Friends</div>
            <div class="py-3 text-muted px-3">Photos</div>
        </div>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-md-5 d-none d-md-block">
        <!-- Intro Card -->
        <div class="card p-3 mb-3">
            <h5 class="fw-bold mb-3">Intro</h5>
            <p class="text-center text-muted mb-3"><?= htmlspecialchars($user['email']) ?></p>
            <?php if (!empty($user['bio'])): ?>
                <p class="text-center text-white fst-italic mb-3">"<?= htmlspecialchars($user['bio']) ?>"</p>
            <?php endif; ?>

            <?php if ($current_user_id == $profile_user_id): ?>
                <a href="edit-profile.php" class="btn btn-secondary w-100 mb-3 fw-bold">Edit bio</a>
            <?php endif; ?>

            <div class="d-flex align-items-center mb-3 text-muted">
                <i class="fa-solid fa-clock me-2 fs-5"></i> Joined <?= date('F Y') ?>
            </div>

            <?php if ($current_user_id == $profile_user_id): ?>
                <a href="edit-profile.php" class="btn btn-secondary w-100 fw-bold">Edit details</a>
            <?php endif; ?>
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
                    <div class="d-flex px-3 py-1 text-center">
                        <form method="POST" action="like.php" class="flex-fill">
                            <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                            <button class="action-btn <?= $post['like_count'] > 0 ? 'active-like' : '' ?>">
                                <i class="<?= $post['like_count'] > 0 ? 'fa-solid' : 'fa-regular' ?> fa-thumbs-up me-1"></i> Like
                            </button>
                        </form>

                        <button class="action-btn flex-fill mx-1" onclick="document.getElementById('comments-<?= $post['id'] ?>').classList.toggle('d-none')">
                            <i class="fa-regular fa-message me-1"></i> Comment
                        </button>

                        <!-- Share Button triggers Modal -->
                        <button class="action-btn flex-fill" data-bs-toggle="modal" data-bs-target="#shareModal<?= $post['id'] ?>">
                            <i class="fa-solid fa-share me-1"></i> Share
                        </button>
                    </div>

                    <!-- Share Modal -->
                    <div class="modal fade" id="shareModal<?= $post['id'] ?>" tabindex="-1" aria-labelledby="shareModalLabel<?= $post['id'] ?>" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                                <div class="modal-header border-bottom border-secondary">
                                    <h5 class="modal-title w-100 text-center fw-bold" id="shareModalLabel<?= $post['id'] ?>">Share Post</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <form action="share-post.php" method="POST">
                                        <input type="hidden" name="shared_post_id" value="<?= $post['shared_post_id'] ? $post['shared_post_id'] : $post['id'] ?>">

                                        <div class="d-flex align-items-center mb-3">
                                            <div class="avatar-placeholder me-2">
                                                <?= strtoupper(substr($current_user_name ?? 'U', 0, 1)) ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold mb-1">Share this post</div>
                                                <select name="audience" class="form-select form-select-sm bg-dark text-white border-secondary" style="width: auto; font-size: 0.8rem; border-radius: 6px; padding: 2px 24px 2px 8px;">
                                                    <option value="public" selected>&#xf0ac; Public</option>
                                                    <option value="followers">&#xf0c0; Followers</option>
                                                    <option value="only_me">&#xf023; Only me</option>
                                                </select>
                                            </div>
                                        </div>

                                        <textarea name="content" class="form-control border-0 bg-transparent fs-5 px-0 text-white mb-3" rows="3" placeholder="Say something about this..." style="resize: none; box-shadow: none;"></textarea>

                                        <div class="p-3 border border-secondary rounded text-muted bg-dark mb-3 text-center">
                                            <i class="fa-solid fa-retweet fs-3 mb-2"></i>
                                            <div>You are sharing a post by <strong><?= htmlspecialchars($user['name']) ?></strong></div>
                                        </div>

                                        <button type="submit" class="btn btn-primary w-100 py-2">Share Now</button>
                                    </form>
                                </div>
                            </div>
                        </div>
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
                            <div class="d-flex mb-3 align-items-start position-relative group-hover-trigger">
                                <a href="user_profile.php?id=<?= $comment['comment_user_id'] ?>">
                                    <div class="avatar-placeholder me-2" style="width: 32px; height: 32px; font-size: 0.9rem;">
                                        <?= strtoupper(substr($comment['name'], 0, 1)) ?>
                                    </div>
                                </a>
                                <div>
                                    <div style="background-color: var(--bg-hover); border-radius: 18px; padding: 8px 12px; display: inline-block; max-width: calc(100% - 40px);">
                                        <a href="user_profile.php?id=<?= $comment['comment_user_id'] ?>" class="text-white fw-bold text-decoration-none d-block" style="font-size: 0.85rem;">
                                            <?= htmlspecialchars($comment['name']) ?>
                                        </a>
                                        <span style="font-size: 0.9rem; word-break: break-word;"><?= htmlspecialchars($comment['content']) ?></span>
                                    </div>
                                    <div class="ms-3 mt-1 d-flex gap-3 text-muted" style="font-size: 0.75rem; font-weight: bold;">
                                        <span class="cursor-pointer hover-underline">Like</span>
                                        <span class="cursor-pointer hover-underline">Reply</span>
                                        <span><?= date('g:i a', strtotime($comment['created_at'])) ?></span>
                                        <?= $comment['is_edited'] ? '<span>Edited</span>' : '' ?>
                                    </div>
                                </div>

                                <?php if ($comment['comment_user_id'] == $current_user_id): ?>
                                <!-- Comment Options Dropdown -->
                                <div class="dropdown position-absolute" style="right: 0; top: 10px;">
                                    <button class="btn btn-sm btn-link text-muted p-0 text-decoration-none opacity-50 hover-opacity-100" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fa-solid fa-ellipsis"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark p-1" style="min-width: 150px; font-size: 0.9rem;">
                                        <li><button class="dropdown-item d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#editCommentModal<?= $comment['id'] ?>"><i class="fa-solid fa-pen"></i> Edit</button></li>
                                        <li>
                                            <form action="crud_actions.php" method="POST" class="m-0" onsubmit="return confirm('Delete this comment?');">
                                                <input type="hidden" name="action" value="delete_comment">
                                                <input type="hidden" name="comment_id" value="<?= $comment['id'] ?>">
                                                <button type="submit" class="dropdown-item text-danger d-flex align-items-center gap-2"><i class="fa-solid fa-trash"></i> Delete</button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>

                                <!-- Edit Comment Modal -->
                                <div class="modal fade" id="editCommentModal<?= $comment['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                                            <div class="modal-header border-bottom border-secondary">
                                                <h5 class="modal-title fw-bold">Edit comment</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form action="crud_actions.php" method="POST">
                                                <input type="hidden" name="action" value="edit_comment">
                                                <input type="hidden" name="comment_id" value="<?= $comment['id'] ?>">
                                                <div class="modal-body">
                                                    <input type="text" name="content" class="form-control bg-dark border-secondary text-white" value="<?= htmlspecialchars($comment['content']) ?>" required>
                                                </div>
                                                <div class="modal-footer border-top border-secondary">
                                                    <button type="submit" class="btn btn-primary btn-sm">Update</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
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