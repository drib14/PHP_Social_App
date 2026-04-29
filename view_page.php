<?php
require_once 'db.php';
include 'partials/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$page_id = $_GET['id'] ?? 0;
$current_user_id = $_SESSION['user_id'];

// Fetch page info
$stmt = $conn->prepare("
    SELECT p.*,
    (SELECT COUNT(*) FROM page_followers WHERE page_id = p.id) as follower_count
    FROM pages p WHERE p.id = ?
");
$stmt->bind_param("i", $page_id);
$stmt->execute();
$page_result = $stmt->get_result();

if ($page_result->num_rows == 0) {
    echo "<div class='container mt-5 text-center text-muted'><h4>Page not found.</h4></div>";
    include 'partials/footer.php';
    exit;
}

$page = $page_result->fetch_assoc();
$is_admin = ($page['creator_id'] == $current_user_id);

// Check if user is following
$follow_stmt = $conn->prepare("SELECT id FROM page_followers WHERE page_id = ? AND user_id = ?");
$follow_stmt->bind_param("ii", $page_id, $current_user_id);
$follow_stmt->execute();
$is_following = $follow_stmt->get_result()->num_rows > 0;

// Fetch Posts for this page
$posts_sql = "
    SELECT posts.*, users.name, users.id as post_user_id, users.profile_pic,
    (SELECT COUNT(*) FROM likes WHERE post_id = posts.id) as like_count,
    (SELECT COUNT(*) FROM comments WHERE post_id = posts.id) as comment_count,
    (SELECT COUNT(*) FROM likes WHERE post_id = posts.id AND user_id = ?) as user_liked
    FROM posts
    JOIN users ON users.id = posts.user_id
    WHERE posts.page_id = ?
    ORDER BY posts.created_at DESC
";
$posts_stmt = $conn->prepare($posts_sql);
$posts_stmt->bind_param("ii", $current_user_id, $page_id);
$posts_stmt->execute();
$result = $posts_stmt->get_result();
?>

<!-- Page Header -->
<div class="card mb-4" style="overflow: hidden;">
    <div style="height: 250px; background: linear-gradient(135deg, #1e293b, var(--bg-hover)); <?= $page['cover_photo'] ? 'background-image: url('.htmlspecialchars($page['cover_photo']).'); background-size: cover; background-position: center;' : '' ?> position: relative;">
        <!-- Placeholder for Cover Photo -->
    </div>

    <div class="px-4 pb-4 position-relative" style="margin-top: -60px;">
        <div class="d-flex flex-column flex-md-row align-items-md-end gap-3">
            <div class="position-relative">
                <div class="rounded-circle border border-4 border-dark overflow-hidden bg-secondary d-flex align-items-center justify-content-center" style="width: 140px; height: 140px; border-color: var(--bg-secondary) !important;">
                    <?php if ($page['profile_pic']): ?>
                        <img src="<?= htmlspecialchars($page['profile_pic']) ?>" class="w-100 h-100 object-fit-cover">
                    <?php else: ?>
                        <i class="fa-solid fa-flag text-muted fs-1"></i>
                    <?php endif; ?>
                </div>
            </div>

            <div class="flex-grow-1 pb-2 text-center text-md-start mt-3 mt-md-0">
                <h1 class="fw-bold mb-0"><?= htmlspecialchars($page['name']) ?></h1>
                <div class="text-muted fw-semibold">
                    <?= $page['follower_count'] ?> followers
                </div>
            </div>

            <div class="pb-2 d-flex gap-2 justify-content-center justify-content-md-end w-100 w-md-auto mt-3 mt-md-0">
                <?php if ($is_admin): ?>
                    <button class="btn btn-secondary"><i class="fa-solid fa-pen me-1"></i> Manage Page</button>
                <?php else: ?>
                    <form action="page_action.php" method="POST" class="m-0">
                        <input type="hidden" name="page_id" value="<?= $page['id'] ?>">
                        <?php if ($is_following): ?>
                            <input type="hidden" name="action" value="unfollow">
                            <button class="btn btn-secondary px-4 fw-bold"><i class="fa-solid fa-check me-2"></i>Following</button>
                        <?php else: ?>
                            <input type="hidden" name="action" value="follow">
                            <button class="btn btn-primary px-4 fw-bold"><i class="fa-solid fa-plus me-2"></i>Follow</button>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <hr class="border-secondary mt-4 mb-0">

        <div class="d-flex gap-4 mt-2 px-2 fw-semibold text-muted">
            <div class="py-3 text-primary border-bottom border-3 border-primary" style="color: var(--accent-color) !important; border-color: var(--accent-color) !important;">Posts</div>
            <div class="py-3 cursor-pointer hover-bg-light rounded px-3">About</div>
            <div class="py-3 cursor-pointer hover-bg-light rounded px-3">Mentions</div>
            <div class="py-3 cursor-pointer hover-bg-light rounded px-3">Reviews</div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-5 d-none d-md-block">
        <div class="card p-3 mb-3">
            <h5 class="fw-bold mb-3">Intro</h5>
            <p class="text-white mb-3" style="font-size: 0.9rem;">
                <?= $page['description'] ? nl2br(htmlspecialchars($page['description'])) : '<em>No description provided.</em>' ?>
            </p>
            <div class="d-flex align-items-center gap-2 text-muted mb-2" style="font-size: 0.9rem;">
                <i class="fa-solid fa-file-lines"></i> Page
            </div>
        </div>
    </div>

    <div class="col-md-7 feed-container pt-0 m-0 w-100" style="max-width: 100%;">
        <?php if ($is_admin): ?>
            <!-- Post Creation Box (Only admins can post to the page feed) -->
            <?php
                $u_stmt = $conn->prepare("SELECT name, profile_pic FROM users WHERE id = ?");
                $u_stmt->bind_param("i", $current_user_id);
                $u_stmt->execute();
                $u_row = $u_stmt->get_result()->fetch_assoc();
            ?>
            <div class="card p-3 mb-4">
                <div class="d-flex align-items-center mb-3">
                    <?php if ($u_row['profile_pic']): ?>
                        <img src="<?= htmlspecialchars($u_row['profile_pic']) ?>" class="rounded-circle me-2 object-fit-cover bg-dark" style="width: 40px; height: 40px;">
                    <?php else: ?>
                        <div class="avatar-placeholder me-2" style="width: 40px; height: 40px; font-size: 1rem;">
                            <?= strtoupper(substr($u_row['name'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    <div class="create-post-trigger text-muted w-100 bg-hover rounded-pill px-3 py-2 cursor-pointer" data-bs-toggle="modal" data-bs-target="#createPagePostModal">
                        Write something on the page...
                    </div>
                </div>
            </div>

            <!-- Create Post Modal -->
            <div class="modal fade" id="createPagePostModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                        <div class="modal-header border-bottom border-secondary">
                            <h5 class="modal-title w-100 text-center fw-bold">Create post</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <form action="create-post.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="page_id" value="<?= $page['id'] ?>">
                                <!-- Audience is forced public for pages -->
                                <input type="hidden" name="audience" value="public">

                                <textarea name="content" class="form-control border-0 bg-transparent fs-5 px-0 text-white" rows="4" placeholder="Write something..." style="resize: none; box-shadow: none;"></textarea>

                                <div class="border border-secondary rounded p-3 mb-3 d-flex align-items-center justify-content-between">
                                    <span class="fw-bold text-white">Add to your post</span>
                                    <div class="d-flex gap-2">
                                        <label for="mediaUploadPage" class="cursor-pointer mb-0">
                                            <i class="fa-regular fa-image text-success fs-4"></i>
                                        </label>
                                        <input type="file" id="mediaUploadPage" name="media" class="d-none" accept="image/*,video/*">
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary w-100 py-2">Post</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Feed -->
        <?php if ($result->num_rows == 0): ?>
            <div class="text-center text-muted p-5 bg-secondary rounded mt-3">
                <i class="fa-solid fa-flag fs-1 mb-3"></i>
                <p class="mb-0 text-white">No posts on this page yet.</p>
            </div>
        <?php else: ?>
            <?php while ($post = $result->fetch_assoc()): ?>
                <div class="card mb-4 pb-2 position-relative">
                    <div class="p-3 pb-2 d-flex justify-content-between align-items-start">
                        <div class="d-flex align-items-center">
                            <!-- We show the author's identity (the admin who posted it) -->
                            <a href="user_profile.php?id=<?= $post['post_user_id'] ?>">
                                <?php if ($post['profile_pic']): ?>
                                    <img src="<?= htmlspecialchars($post['profile_pic']) ?>" class="rounded-circle me-2 object-fit-cover bg-dark" style="width: 40px; height: 40px;">
                                <?php else: ?>
                                    <div class="avatar-placeholder me-2" style="width: 40px; height: 40px; font-size: 1rem;">
                                        <?= strtoupper(substr($post['name'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                            </a>
                            <div>
                                <a href="user_profile.php?id=<?= $post['post_user_id'] ?>" class="text-white fw-bold text-decoration-none">
                                    <?= htmlspecialchars($post['name']) ?>
                                </a>
                                <div class="text-muted" style="font-size: 0.8rem;">
                                    <?= date('M j \a\t g:i a', strtotime($post['created_at'])) ?> · <i class="fa-solid fa-earth-americas"></i>
                                    <?= isset($post['is_edited']) && $post['is_edited'] ? ' · Edited' : '' ?>
                                </div>
                            </div>
                        </div>

                        <?php if ($post['post_user_id'] == $current_user_id): ?>
                        <div class="dropdown">
                            <button class="btn btn-link text-muted p-0 text-decoration-none" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fa-solid fa-ellipsis"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark p-1" style="min-width: 150px;">
                                <li>
                                    <form action="crud_actions.php" method="POST" class="m-0" onsubmit="return confirm('Are you sure you want to delete this post?');">
                                        <input type="hidden" name="action" value="delete_post">
                                        <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                                        <button type="submit" class="dropdown-item text-danger d-flex align-items-center gap-2"><i class="fa-solid fa-trash"></i> Move to trash</button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                        <?php endif; ?>
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
                        <button class="action-btn flex-fill" data-bs-toggle="modal" data-bs-target="#shareModal<?= $post['id'] ?>">
                            <i class="fa-solid fa-share me-1"></i> Share
                        </button>
                    </div>

                    <!-- Share Modal -->
                    <div class="modal fade" id="shareModal<?= $post['id'] ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                                <div class="modal-header border-bottom border-secondary">
                                    <h5 class="modal-title w-100 text-center fw-bold">Share Post</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body text-start">
                                    <form action="share-post.php" method="POST">
                                        <input type="hidden" name="shared_post_id" value="<?= $post['shared_post_id'] ? $post['shared_post_id'] : $post['id'] ?>">
                                        <textarea name="content" class="form-control border-0 bg-transparent fs-5 px-0 text-white mb-3" rows="3" placeholder="Say something about this..."></textarea>
                                        <button type="submit" class="btn btn-primary w-100 py-2">Share Now</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>
</div>

<?php include 'partials/footer.php'; ?>