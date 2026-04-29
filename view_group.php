<?php
require_once 'db.php';
include 'partials/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$group_id = $_GET['id'] ?? 0;
$current_user_id = $_SESSION['user_id'];

// Fetch group info
$stmt = $conn->prepare("
    SELECT g.*,
    (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) as member_count
    FROM user_groups g WHERE g.id = ?
");
$stmt->bind_param("i", $group_id);
$stmt->execute();
$group_result = $stmt->get_result();

if ($group_result->num_rows == 0) {
    echo "<div class='container mt-5 text-center text-muted'><h4>Group not found.</h4></div>";
    include 'partials/footer.php';
    exit;
}

$group = $group_result->fetch_assoc();

// Check if user is a member
$member_stmt = $conn->prepare("SELECT role FROM group_members WHERE group_id = ? AND user_id = ?");
$member_stmt->bind_param("ii", $group_id, $current_user_id);
$member_stmt->execute();
$member_result = $member_stmt->get_result();
$is_member = $member_result->num_rows > 0;
$member_role = $is_member ? $member_result->fetch_assoc()['role'] : null;

// Fetch Posts for this group
$posts_sql = "
    SELECT posts.*, users.name, users.id as post_user_id,
    (SELECT COUNT(*) FROM likes WHERE post_id = posts.id) as like_count,
    (SELECT COUNT(*) FROM comments WHERE post_id = posts.id) as comment_count,
    (SELECT COUNT(*) FROM likes WHERE post_id = posts.id AND user_id = ?) as user_liked
    FROM posts
    JOIN users ON users.id = posts.user_id
    WHERE posts.group_id = ?
    ORDER BY posts.created_at DESC
";
$posts_stmt = $conn->prepare($posts_sql);
$posts_stmt->bind_param("ii", $current_user_id, $group_id);
$posts_stmt->execute();
$result = $posts_stmt->get_result();

// Get current user name for placeholder
$u_stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
$u_stmt->bind_param("i", $current_user_id);
$u_stmt->execute();
$current_user_name = $u_stmt->get_result()->fetch_assoc()['name'];
?>

<!-- Group Header -->
<div class="card mb-4" style="overflow: hidden;">
    <div style="height: 200px; background: linear-gradient(135deg, #334155, var(--bg-primary)); position: relative;" class="d-flex align-items-center justify-content-center">
        <i class="fa-solid fa-users fs-1 text-muted opacity-50"></i>
    </div>

    <div class="px-4 pb-4 position-relative">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 pt-3">
            <div>
                <h2 class="fw-bold mb-1"><?= htmlspecialchars($group['name']) ?></h2>
                <div class="text-muted fw-semibold d-flex align-items-center gap-2">
                    <i class="fa-solid fa-lock" style="font-size: 0.8rem;"></i> Private group · <?= $group['member_count'] ?> members
                </div>
            </div>

            <div class="d-flex gap-2">
                <?php if ($is_member): ?>
                    <button class="btn btn-secondary px-4 fw-bold"><i class="fa-solid fa-users me-2"></i>Joined <i class="fa-solid fa-chevron-down ms-1"></i></button>
                    <form action="group_action.php" method="POST" class="m-0" onsubmit="return confirm('Leave this group?');">
                        <input type="hidden" name="action" value="leave">
                        <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                        <button class="btn btn-outline-danger px-3"><i class="fa-solid fa-right-from-bracket"></i></button>
                    </form>
                <?php else: ?>
                    <form action="group_action.php" method="POST" class="m-0">
                        <input type="hidden" name="action" value="join">
                        <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                        <button class="btn btn-primary px-4 fw-bold"><i class="fa-solid fa-user-plus me-2"></i>Join Group</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8 feed-container pt-0 m-0 w-100" style="max-width: 100%;">
        <?php if ($is_member): ?>
            <!-- Post Creation Box -->
            <div class="card p-3 mb-4">
                <div class="d-flex align-items-center mb-3">
                    <div class="avatar-placeholder me-2" style="width: 40px; height: 40px; font-size: 1rem;">
                        <?= strtoupper(substr($current_user_name, 0, 1)) ?>
                    </div>
                    <div class="create-post-trigger text-muted w-100 bg-hover rounded-pill px-3 py-2 cursor-pointer" data-bs-toggle="modal" data-bs-target="#createGroupPostModal">
                        Write something...
                    </div>
                </div>
            </div>

            <!-- Create Post Modal -->
            <div class="modal fade" id="createGroupPostModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                        <div class="modal-header border-bottom border-secondary">
                            <h5 class="modal-title w-100 text-center fw-bold">Create post</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <form action="create-post.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="group_id" value="<?= $group['id'] ?>">
                                <!-- Audience is forced for groups -->
                                <input type="hidden" name="audience" value="public">

                                <textarea name="content" class="form-control border-0 bg-transparent fs-5 px-0 text-white" rows="4" placeholder="Write something..." style="resize: none; box-shadow: none;"></textarea>

                                <div class="border border-secondary rounded p-3 mb-3 d-flex align-items-center justify-content-between">
                                    <span class="fw-bold">Add to your post</span>
                                    <div class="d-flex gap-2">
                                        <label for="mediaUploadGrp" class="cursor-pointer mb-0">
                                            <i class="fa-regular fa-image text-success fs-4"></i>
                                        </label>
                                        <input type="file" id="mediaUploadGrp" name="media" class="d-none" accept="image/*,video/*">
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary w-100 py-2">Post</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Feed -->
            <?php if ($result->num_rows == 0): ?>
                <div class="text-center text-muted p-5 bg-secondary rounded mt-3">
                    <i class="fa-solid fa-users fs-1 mb-3"></i>
                    <p class="mb-0">No posts in this group yet. Be the first!</p>
                </div>
            <?php else: ?>
                <?php while ($post = $result->fetch_assoc()): ?>
                    <div class="card mb-4 pb-2 position-relative">
                        <div class="p-3 pb-2 d-flex justify-content-between align-items-start">
                            <div class="d-flex align-items-center">
                                <a href="user_profile.php?id=<?= $post['post_user_id'] ?>">
                                    <div class="avatar-placeholder me-2" style="width: 40px; height: 40px; font-size: 1rem;">
                                        <?= strtoupper(substr($post['name'], 0, 1)) ?>
                                    </div>
                                </a>
                                <div>
                                    <a href="user_profile.php?id=<?= $post['post_user_id'] ?>" class="text-white fw-bold text-decoration-none">
                                        <?= htmlspecialchars($post['name']) ?>
                                    </a>
                                    <div class="text-muted" style="font-size: 0.8rem;">
                                        <?= date('M j \a\t g:i a', strtotime($post['created_at'])) ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="px-3 pb-2 fs-6">
                            <?= nl2br(htmlspecialchars($post['content'])) ?>
                        </div>

                        <?php if (!empty($post['media_url'])): ?>
                            <div class="w-100 bg-dark text-center my-2" style="max-height: 500px; overflow: hidden; display: flex; align-items: center; justify-content: center;">
                                <?php if (str_starts_with($post['media_type'], 'image/')): ?>
                                    <img src="<?= htmlspecialchars($post['media_url']) ?>" class="img-fluid" style="max-height: 500px; object-fit: contain;">
                                <?php elseif (str_starts_with($post['media_type'], 'video/')): ?>
                                    <video controls class="w-100" style="max-height: 500px;">
                                        <source src="<?= htmlspecialchars($post['media_url']) ?>" type="<?= htmlspecialchars($post['media_type']) ?>">
                                    </video>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="d-flex px-3 py-1 text-center border-top border-secondary mt-2 pt-2">
                            <form method="POST" action="like.php" class="flex-fill">
                                <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                                <button class="action-btn <?= $post['user_liked'] ? 'active-like' : '' ?>">
                                    <i class="<?= $post['user_liked'] ? 'fa-solid' : 'fa-regular' ?> fa-thumbs-up me-1"></i> <?= $post['like_count'] > 0 ? $post['like_count'] : 'Like' ?>
                                </button>
                            </form>
                            <button class="action-btn flex-fill mx-1">
                                <i class="fa-regular fa-message me-1"></i> <?= $post['comment_count'] > 0 ? $post['comment_count'] : 'Comment' ?>
                            </button>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>

        <?php else: ?>
            <div class="card p-5 text-center text-muted">
                <i class="fa-solid fa-lock fs-1 mb-3 text-secondary"></i>
                <h5 class="text-white fw-bold">This group is private</h5>
                <p>Join this group to view or participate in discussions.</p>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-md-4 d-none d-md-block">
        <div class="card p-3">
            <h5 class="fw-bold mb-3">About</h5>
            <p class="text-white mb-3" style="font-size: 0.9rem;">
                <?= $group['description'] ? nl2br(htmlspecialchars($group['description'])) : '<em>No description provided.</em>' ?>
            </p>
            <div class="d-flex align-items-center gap-2 text-muted mb-2" style="font-size: 0.9rem;">
                <i class="fa-solid fa-earth-americas"></i> Private
            </div>
            <div class="d-flex align-items-center gap-2 text-muted" style="font-size: 0.9rem;">
                <i class="fa-solid fa-clock"></i> History
            </div>
        </div>
    </div>
</div>

<?php include 'partials/footer.php'; ?>