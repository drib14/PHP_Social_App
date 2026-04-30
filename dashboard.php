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
        AND (posts.audience = 'public' OR posts.audience = 'followers' OR posts.user_id = ?)
        ORDER BY posts.created_at DESC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $current_user_id, $current_user_id, $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // Global Feed: Public posts + Own posts + Posts from people you follow (if audience is followers) + Group Posts you belong to
    $sql = "
        SELECT posts.*, users.name, users.id as post_user_id,
        (SELECT COUNT(*) FROM likes WHERE post_id = posts.id) as like_count,
        (SELECT COUNT(*) FROM comments WHERE post_id = posts.id) as comment_count,
        (SELECT COUNT(*) FROM likes WHERE post_id = posts.id AND user_id = ?) as user_liked,
        user_groups.name as group_name
        FROM posts
        JOIN users ON users.id = posts.user_id
        LEFT JOIN user_groups ON user_groups.id = posts.group_id
        WHERE (
            posts.group_id IS NULL AND (
                (posts.audience = 'public')
                OR (posts.user_id = ?)
                OR (posts.audience = 'followers' AND EXISTS (SELECT 1 FROM follows WHERE follower_id = ? AND following_id = posts.user_id))
            )
        )
        OR (
            posts.group_id IS NOT NULL AND EXISTS (SELECT 1 FROM group_members WHERE user_id = ? AND group_id = posts.group_id)
        )
        ORDER BY posts.created_at DESC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiii", $current_user_id, $current_user_id, $current_user_id, $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
}
?>

<?php
// Get current user name and profile pic
$user_stmt = $conn->prepare("SELECT name, profile_pic FROM users WHERE id = ?");
$user_stmt->bind_param("i", $current_user_id);
$user_stmt->execute();
$current_user_row = $user_stmt->get_result()->fetch_assoc();
$current_user_name = $current_user_row['name'];
$current_profile_pic = $current_user_row['profile_pic'];
?>

<div class="row">
    <!-- Left Sidebar (Navigation/Groups) -->
    <div class="col-lg-3 d-none d-lg-block">
        <div class="position-sticky" style="top: 80px;">
            <div class="d-flex align-items-center mb-3 p-2 rounded hover-bg-dark cursor-pointer" onclick="window.location.href='user_profile.php'">
                <?php if (!empty($current_profile_pic)): ?>
                    <img src="<?= htmlspecialchars($current_profile_pic) ?>" class="rounded-circle me-3 object-fit-cover bg-dark" style="width: 36px; height: 36px;">
                <?php else: ?>
                    <div class="avatar-placeholder me-3" style="width: 36px; height: 36px; font-size: 1rem;">
                        <?= strtoupper(substr($current_user_name, 0, 1)) ?>
                    </div>
                <?php endif; ?>
                <span class="fw-bold"><?= htmlspecialchars($current_user_name) ?></span>
            </div>

            <a href="dashboard.php?feed=following" class="d-flex align-items-center mb-3 p-2 rounded text-decoration-none text-white hover-bg-dark cursor-pointer">
                <i class="fa-solid fa-user-group fs-4 text-primary me-3 w-30px text-center"></i>
                <span class="fw-bold">Friends</span>
            </a>

            <a href="groups.php" class="d-flex align-items-center mb-3 p-2 rounded text-decoration-none text-white hover-bg-dark cursor-pointer">
                <i class="fa-solid fa-users fs-4 text-success me-3 w-30px text-center"></i>
                <span class="fw-bold">Groups</span>
            </a>

            <a href="pages.php" class="d-flex align-items-center mb-3 p-2 rounded text-decoration-none text-white hover-bg-dark cursor-pointer">
                <i class="fa-solid fa-flag fs-4 text-warning me-3 w-30px text-center"></i>
                <span class="fw-bold">Pages</span>
            </a>

            <hr class="border-secondary my-2">
            <h6 class="text-muted fw-bold px-2 mt-3">Your Shortcuts</h6>

            <?php
            // Fetch groups user joined
            $my_groups_stmt = $conn->prepare("SELECT user_groups.id, user_groups.name, user_groups.cover_photo FROM user_groups JOIN group_members ON user_groups.id = group_members.group_id WHERE group_members.user_id = ? LIMIT 5");
            $my_groups_stmt->bind_param("i", $current_user_id);
            $my_groups_stmt->execute();
            $my_groups_result = $my_groups_stmt->get_result();
            while($grp = $my_groups_result->fetch_assoc()):
            ?>
                <a href="view_group.php?id=<?= $grp['id'] ?>" class="d-flex align-items-center mb-2 p-2 rounded text-decoration-none text-white hover-bg-dark cursor-pointer">
                    <?php if($grp['cover_photo']): ?>
                        <img src="<?= htmlspecialchars($grp['cover_photo']) ?>" class="rounded me-3 object-fit-cover" style="width: 36px; height: 36px;">
                    <?php else: ?>
                        <div class="rounded bg-secondary d-flex justify-content-center align-items-center me-3" style="width: 36px; height: 36px;">
                            <i class="fa-solid fa-users text-muted"></i>
                        </div>
                    <?php endif; ?>
                    <span class="text-truncate"><?= htmlspecialchars($grp['name']) ?></span>
                </a>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- Center Feed -->
    <div class="col-lg-6 col-md-8 mx-auto feed-container pt-0">
    <!-- Story / Group Carousel -->
    <div class="mb-4 position-relative">
        <div class="d-flex gap-2 overflow-auto hide-scrollbar pb-2" style="scroll-snap-type: x mandatory;">

            <!-- Create Story Card -->
            <div class="card overflow-hidden flex-shrink-0 cursor-pointer border-0" style="width: 120px; height: 200px; scroll-snap-align: start;">
                <div class="h-100 position-relative bg-dark" style="background-image: url('<?= $current_profile_pic ? htmlspecialchars($current_profile_pic) : "" ?>'); background-size: cover; background-position: center;">
                    <div class="position-absolute bottom-0 w-100 bg-secondary text-center pt-3 pb-2" style="border-top: 1px solid var(--border-color);">
                        <div class="position-absolute top-0 start-50 translate-middle bg-primary rounded-circle d-flex justify-content-center align-items-center" style="width: 32px; height: 32px; border: 4px solid var(--bg-secondary);">
                            <i class="fa-solid fa-plus text-white"></i>
                        </div>
                        <span class="fw-bold" style="font-size: 0.8rem;">Create Story</span>
                    </div>
                </div>
            </div>

            <?php
            // Fetch public groups for carousel
            $carousel_groups = $conn->query("SELECT id, name, cover_photo FROM user_groups LIMIT 6");
            while($c_grp = $carousel_groups->fetch_assoc()):
            ?>
            <div class="card overflow-hidden flex-shrink-0 cursor-pointer border-0" style="width: 120px; height: 200px; scroll-snap-align: start;" onclick="window.location.href='view_group.php?id=<?= $c_grp['id'] ?>'">
                <div class="h-100 position-relative" style="background: linear-gradient(rgba(0,0,0,0.2), rgba(0,0,0,0.7)), url('<?= $c_grp['cover_photo'] ? htmlspecialchars($c_grp['cover_photo']) : "https://via.placeholder.com/120x200/2c3e50/ffffff?text=Group" ?>'); background-size: cover; background-position: center;">
                    <div class="position-absolute top-0 start-0 m-2">
                        <div class="bg-primary rounded-circle d-flex justify-content-center align-items-center" style="width: 32px; height: 32px; border: 2px solid var(--accent-color);">
                            <i class="fa-solid fa-users text-white" style="font-size: 0.8rem;"></i>
                        </div>
                    </div>
                    <div class="position-absolute bottom-0 start-0 w-100 p-2">
                        <span class="fw-bold text-white text-truncate d-block" style="font-size: 0.8rem;"><?= htmlspecialchars($c_grp['name']) ?></span>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- Post Creation Box (Facebook Style) -->
    <div class="card p-3 mb-4">
        <div class="d-flex align-items-center mb-3">
            <?php if (!empty($current_profile_pic)): ?>
                <img src="<?= htmlspecialchars($current_profile_pic) ?>" class="rounded-circle me-2 object-fit-cover bg-dark" style="width: 40px; height: 40px;">
            <?php else: ?>
                <div class="avatar-placeholder me-2">
                    <?= strtoupper(substr($current_user_name, 0, 1)) ?>
                </div>
            <?php endif; ?>
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
                    <form action="create-post.php" method="POST" enctype="multipart/form-data">
                        <div class="d-flex align-items-center mb-3">
                            <div class="avatar-placeholder me-2">
                                <?= strtoupper(substr($current_user_name, 0, 1)) ?>
                            </div>
                            <div>
                                <div class="fw-bold mb-1"><?= htmlspecialchars($current_user_name) ?></div>
                                <select name="audience" class="form-select form-select-sm bg-dark text-white border-secondary" style="width: auto; font-size: 0.8rem; border-radius: 6px; padding: 2px 24px 2px 8px; font-family: 'Font Awesome 6 Free', 'Poppins', sans-serif; font-weight: 900;">
                                    <option value="public" selected>&#xf0ac; Public</option>
                                    <option value="followers">&#xf0c0; Followers</option>
                                    <option value="only_me">&#xf023; Only me</option>
                                </select>
                            </div>
                        </div>
                        <textarea name="content" class="form-control border-0 bg-transparent fs-5 px-0 text-white" rows="4" placeholder="What's on your mind, <?= explode(' ', htmlspecialchars($current_user_name))[0] ?>?" style="resize: none; box-shadow: none;"></textarea>

                        <!-- Media Upload Preview / Input -->
                        <div class="border border-secondary rounded p-3 mb-3 d-flex align-items-center justify-content-between">
                            <span class="fw-bold">Add to your post</span>
                            <div class="d-flex gap-2">
                                <label for="mediaUpload" class="cursor-pointer mb-0">
                                    <i class="fa-regular fa-image text-success fs-4"></i>
                                </label>
                                <input type="file" id="mediaUpload" name="media" class="d-none" accept="image/*,video/*">
                            </div>
                        </div>
                        <!-- Media Preview Container (JS handled) -->
                        <div id="mediaPreviewContainer" class="position-relative mb-3 d-none">
                            <div class="position-absolute top-0 end-0 m-2 z-1">
                                <button type="button" class="btn btn-dark btn-sm rounded-circle opacity-75 hover-opacity-100" onclick="clearMediaUpload()">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </div>
                            <img id="imagePreview" src="" class="img-fluid rounded border border-secondary d-none" style="max-height: 200px; width: 100%; object-fit: cover;">
                            <video id="videoPreview" src="" class="img-fluid rounded border border-secondary d-none" style="max-height: 200px; width: 100%;" controls></video>
                            <div id="filePreview" class="p-3 border border-secondary rounded bg-dark d-none align-items-center gap-2">
                                <i class="fa-solid fa-file text-muted fs-4"></i>
                                <span id="fileNameText" class="text-truncate flex-grow-1"></span>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2">Post</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Simple JS to show selected file name in the modal
        document.getElementById('mediaUpload').addEventListener('change', function(e) {
            const container = document.getElementById('mediaPreviewContainer');
            const imgPreview = document.getElementById('imagePreview');
            const vidPreview = document.getElementById('videoPreview');
            const filePreview = document.getElementById('filePreview');
            const fileNameText = document.getElementById('fileNameText');

            imgPreview.classList.add('d-none');
            vidPreview.classList.add('d-none');
            filePreview.classList.add('d-none');
            container.classList.add('d-none');

            if (this.files && this.files[0]) {
                const file = this.files[0];
                container.classList.remove('d-none');

                if (file.type.startsWith('image/')) {
                    imgPreview.src = URL.createObjectURL(file);
                    imgPreview.classList.remove('d-none');
                } else if (file.type.startsWith('video/')) {
                    vidPreview.src = URL.createObjectURL(file);
                    vidPreview.classList.remove('d-none');
                } else {
                    fileNameText.textContent = file.name;
                    filePreview.classList.remove('d-none');
                    filePreview.classList.add('d-flex');
                }
            }
        });

        function clearMediaUpload() {
            const input = document.getElementById('mediaUpload');
            input.value = '';
            document.getElementById('mediaPreviewContainer').classList.add('d-none');
        }
    </script>

    <?php if ($result->num_rows == 0): ?>
        <div class="text-center text-muted my-5">
            <i class="fa-solid fa-wind fs-1 mb-3"></i>
            <p>No posts to show.</p>
        </div>
    <?php endif; ?>

    <?php
        // Fetch posts again properly to join user info including profile_pic
    ?>
    <?php while ($post = $result->fetch_assoc()): ?>
    <div class="card mb-4 pb-2 position-relative">
        <div class="p-3 pb-2 d-flex justify-content-between align-items-start">
            <div class="d-flex align-items-center">
                <a href="user_profile.php?id=<?= $post['post_user_id'] ?>">
                    <?php
                        $pic_stmt = $conn->prepare("SELECT profile_pic FROM users WHERE id = ?");
                        $pic_stmt->bind_param("i", $post['post_user_id']);
                        $pic_stmt->execute();
                        $pic_res = $pic_stmt->get_result()->fetch_assoc();
                    ?>
                    <?php if (!empty($pic_res['profile_pic'])): ?>
                        <img src="<?= htmlspecialchars($pic_res['profile_pic']) ?>" class="rounded-circle me-2 object-fit-cover bg-dark" style="width: 40px; height: 40px;">
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
                    <?php if (isset($post['group_id']) && $post['group_id']): ?>
                        <span class="text-muted mx-1">▶</span>
                        <a href="view_group.php?id=<?= $post['group_id'] ?>" class="text-white fw-bold text-decoration-none">
                            <?= htmlspecialchars($post['group_name']) ?>
                        </a>
                    <?php endif; ?>

                    <?php
                        $audience_icon = 'fa-earth-americas';
                        if ($post['audience'] === 'followers') $audience_icon = 'fa-user-group';
                        if ($post['audience'] === 'only_me') $audience_icon = 'fa-lock';
                        if (isset($post['group_id']) && $post['group_id']) $audience_icon = 'fa-users';
                    ?>
                    <div class="text-muted" style="font-size: 0.8rem;">
                        <?= date('M j \a\t g:i a', strtotime($post['created_at'])) ?> ·
                        <i class="fa-solid <?= $audience_icon ?>"></i>
                        <?= isset($post['is_edited']) && $post['is_edited'] ? ' · Edited' : '' ?>
                    </div>
                </div>
            </div>

            <?php if ($post['post_user_id'] == $current_user_id): ?>
            <!-- Post Options Dropdown -->
            <div class="dropdown">
                <button class="btn btn-link text-muted p-0 text-decoration-none" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fa-solid fa-ellipsis"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark p-1" style="min-width: 150px;">
                    <li><button class="dropdown-item d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#editPostModal<?= $post['id'] ?>"><i class="fa-solid fa-pen"></i> Edit post</button></li>
                    <li><hr class="dropdown-divider border-secondary my-1"></li>
                    <li>
                        <form action="crud_actions.php" method="POST" class="m-0" onsubmit="return confirm('Are you sure you want to delete this post?');">
                            <input type="hidden" name="action" value="delete_post">
                            <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                            <button type="submit" class="dropdown-item text-danger d-flex align-items-center gap-2"><i class="fa-solid fa-trash"></i> Move to trash</button>
                        </form>
                    </li>
                </ul>
            </div>

            <!-- Edit Post Modal -->
            <div class="modal fade" id="editPostModal<?= $post['id'] ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content" style="background-color: var(--bg-secondary); border: 1px solid var(--border-color);">
                        <div class="modal-header border-bottom border-secondary">
                            <h5 class="modal-title fw-bold">Edit post</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <form action="crud_actions.php" method="POST">
                            <input type="hidden" name="action" value="edit_post">
                            <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                            <div class="modal-body">
                                <textarea name="content" class="form-control border-secondary bg-dark text-white" rows="4" required><?= htmlspecialchars($post['content']) ?></textarea>
                            </div>
                            <div class="modal-footer border-top border-secondary">
                                <button type="submit" class="btn btn-primary w-100">Save changes</button>
                            </div>
                        </form>
                    </div>
                </div>
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
                        Your browser does not support the video tag.
                    </video>
                <?php else: ?>
                    <div class="p-4 border border-secondary rounded m-3 bg-secondary d-flex align-items-center gap-3">
                        <i class="fa-solid fa-file fs-1 text-muted"></i>
                        <div class="text-start">
                            <div class="fw-bold"><?= htmlspecialchars($post['media_name']) ?></div>
                            <a href="<?= htmlspecialchars($post['media_url']) ?>" class="btn btn-sm btn-primary mt-2" target="_blank" download>Download File</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

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
                <button class="action-btn <?= $post['user_liked'] ? 'active-like' : '' ?>">
                    <i class="<?= $post['user_liked'] ? 'fa-solid' : 'fa-regular' ?> fa-thumbs-up me-1"></i> Like
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
                                    <?= strtoupper(substr($current_user_name, 0, 1)) ?>
                                </div>
                                <div>
                                    <div class="fw-bold mb-1"><?= htmlspecialchars($current_user_name) ?></div>
                                    <select name="audience" class="form-select form-select-sm bg-dark text-white border-secondary" style="width: auto; font-size: 0.8rem; border-radius: 6px; padding: 2px 24px 2px 8px; font-family: 'Font Awesome 6 Free', 'Poppins', sans-serif; font-weight: 900;">
                                        <option value="public" selected>&#xf0ac; Public</option>
                                        <option value="followers">&#xf0c0; Followers</option>
                                        <option value="only_me">&#xf023; Only me</option>
                                    </select>
                                </div>
                            </div>

                            <textarea name="content" class="form-control border-0 bg-transparent fs-5 px-0 text-white mb-3" rows="3" placeholder="Say something about this..." style="resize: none; box-shadow: none;"></textarea>

                            <div class="p-3 border border-secondary rounded text-muted bg-dark mb-3 text-center">
                                <i class="fa-solid fa-retweet fs-3 mb-2"></i>
                                <div>You are sharing a post by <strong><?= htmlspecialchars($post['name']) ?></strong></div>
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


    </div>

    <!-- Right Sidebar (Contacts) -->
    <div class="col-lg-3 d-none d-lg-block">
        <div class="position-sticky" style="top: 80px;">
            <div class="d-flex justify-content-between align-items-center mb-3 px-2">
                <h6 class="text-muted fw-bold mb-0">Contacts</h6>
                <div class="d-flex gap-3 text-muted">
                    <i class="fa-solid fa-video cursor-pointer"></i>
                    <i class="fa-solid fa-search cursor-pointer"></i>
                    <i class="fa-solid fa-ellipsis cursor-pointer"></i>
                </div>
            </div>

            <?php
            // Fetch users you follow for contacts
            $contacts_stmt = $conn->prepare("
                SELECT users.id, users.name, users.profile_pic
                FROM users
                JOIN follows ON follows.following_id = users.id
                WHERE follows.follower_id = ?
                LIMIT 15
            ");
            $contacts_stmt->bind_param("i", $current_user_id);
            $contacts_stmt->execute();
            $contacts_result = $contacts_stmt->get_result();
            while($contact = $contacts_result->fetch_assoc()):
            ?>
                <div class="d-flex align-items-center mb-2 p-2 rounded hover-bg-dark cursor-pointer" onclick="document.getElementById('main-chat-trigger').click(); setTimeout(() => openConversation(<?= $contact['id'] ?>, '<?= addslashes($contact['name']) ?>', '<?= strtoupper(substr($contact['name'], 0, 1)) ?>', 'user'), 300);">
                    <div class="position-relative me-3">
                        <?php if (!empty($contact['profile_pic'])): ?>
                            <img src="<?= htmlspecialchars($contact['profile_pic']) ?>" class="rounded-circle object-fit-cover bg-dark" style="width: 36px; height: 36px;">
                        <?php else: ?>
                            <div class="avatar-placeholder" style="width: 36px; height: 36px; font-size: 1rem;">
                                <?= strtoupper(substr($contact['name'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        <!-- Green active dot (fake status) -->
                        <div class="position-absolute bg-success rounded-circle border border-2 border-dark" style="width: 12px; height: 12px; bottom: 0; right: 0;"></div>
                    </div>
                    <span class="fw-semibold text-truncate"><?= htmlspecialchars($contact['name']) ?></span>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</div>

<?php include 'partials/footer.php'; ?>