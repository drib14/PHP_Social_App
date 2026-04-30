<?php
// includes/post_card.php
// Expected variables: $post (array with post data)

// Determine if it's a repost
$is_repost = !empty($post['original_post_id']);

// Fetch comments
$cmtStmt = $pdo->prepare("
    SELECT c.*, u.username, u.first_name, u.last_name, u.avatar
    FROM comments c
    JOIN users u ON c.user_id = u.id
    WHERE c.post_id = ?
    ORDER BY c.created_at ASC
");
$cmtStmt->execute([$post['id']]);
$comments = $cmtStmt->fetchAll();

// Determine sharing rules (Only "public" or "connections" with accepted status can be shared)
$can_share = false;
if ($post['audience'] === 'public') {
    $can_share = true;
} else if ($post['audience'] === 'connections') {
    // Check if connected
    $connStmt = $pdo->prepare("SELECT id FROM connections WHERE ((requester_id = ? AND receiver_id = ?) OR (requester_id = ? AND receiver_id = ?)) AND status = 'accepted'");
    $connStmt->execute([$_SESSION['user_id'], $post['user_id'], $post['user_id'], $_SESSION['user_id']]);
    if ($connStmt->fetch() || $post['user_id'] == $_SESSION['user_id']) {
        $can_share = true;
    }
}
?>

<div class="card mb-4 border-secondary shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center">
            <a href="profile.php?id=<?php echo $post['user_id']; ?>" class="text-decoration-none">
                <img src="<?php echo htmlspecialchars($post['avatar'] ?: '/assets/img/default-avatar.png'); ?>" class="rounded-circle me-2" width="45" height="45" style="object-fit:cover;">
            </a>
            <div>
                <a href="profile.php?id=<?php echo $post['user_id']; ?>" class="text-light text-decoration-none fw-bold">
                    <?php echo htmlspecialchars($post['first_name'] . ' ' . $post['last_name']); ?>
                </a>
                <span class="d-block text-muted small" style="font-size: 0.8rem;">
                    <?php echo date('M j \a\t g:i A', strtotime($post['created_at'])); ?>
                    •
                    <?php if($post['audience'] == 'public'): ?><i class="fa-solid fa-earth-americas" title="Public"></i>
                    <?php elseif($post['audience'] == 'connections'): ?><i class="fa-solid fa-user-group" title="Connections Only"></i>
                    <?php else: ?><i class="fa-solid fa-lock" title="Only Me/Profile Only"></i><?php endif; ?>
                </span>
            </div>
        </div>
    </div>

    <div class="card-body">
        <?php if($post['content']): ?>
            <p class="card-text text-light" style="white-space: pre-wrap;"><?php echo htmlspecialchars($post['content']); ?></p>
        <?php endif; ?>

        <?php if($post['media_url']): ?>
            <div class="mt-2 text-center bg-dark rounded">
                <?php if($post['media_type'] === 'image'): ?>
                    <img src="<?php echo htmlspecialchars($post['media_url']); ?>" class="img-fluid rounded" style="max-height: 500px; object-fit: contain;">
                <?php elseif($post['media_type'] === 'video'): ?>
                    <video src="<?php echo htmlspecialchars($post['media_url']); ?>" controls class="w-100 rounded" style="max-height: 500px;"></video>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if($is_repost): ?>
            <div class="card mt-3 bg-dark border-secondary">
                <div class="card-body">
                    <p class="text-muted small"><i class="fa-solid fa-retweet"></i> Reposted content...</p>
                    <a href="post.php?id=<?php echo $post['original_post_id']; ?>" class="btn btn-sm btn-outline-success">View Original Post</a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="card-footer border-secondary">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <?php $target_type = 'post'; $target_id = $post['id']; include 'includes/reaction_ui.php'; ?>

            <div class="d-flex align-items-center">
                <button class="btn btn-sm btn-secondary text-light me-2" type="button" data-bs-toggle="collapse" data-bs-target="#comments-<?php echo $post['id']; ?>" aria-expanded="false">
                    <i class="fa-regular fa-comment"></i> <?php echo count($comments); ?> Comments
                </button>

                <?php if($can_share): ?>
                    <form action="create_post.php" method="POST" class="d-inline">
                        <input type="hidden" name="original_post_id" value="<?php echo $post['id']; ?>">
                        <!-- Repost inherits the source's restrictiveness or public -->
                        <input type="hidden" name="audience" value="<?php echo $post['audience']; ?>">
                        <button type="submit" class="btn btn-sm btn-secondary text-light">
                            <i class="fa-solid fa-share"></i> Repost
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Comments Section -->
        <div class="collapse mt-2" id="comments-<?php echo $post['id']; ?>">
            <div class="comments-list mb-3">
                <?php foreach($comments as $comment): ?>
                    <div class="d-flex mb-2">
                        <img src="<?php echo htmlspecialchars($comment['avatar'] ?: '/assets/img/default-avatar.png'); ?>" class="rounded-circle me-2 mt-1" width="30" height="30" style="object-fit:cover;">
                        <div class="bg-dark p-2 rounded w-100 border border-secondary">
                            <div class="d-flex justify-content-between">
                                <a href="profile.php?id=<?php echo $comment['user_id']; ?>" class="fw-bold text-light text-decoration-none small"><?php echo htmlspecialchars($comment['first_name'] . ' ' . $comment['last_name']); ?></a>
                                <small class="text-muted" style="font-size:0.7rem;"><?php echo date('M j g:i A', strtotime($comment['created_at'])); ?></small>
                            </div>
                            <p class="mb-1 small"><?php echo htmlspecialchars($comment['content']); ?></p>

                            <div class="d-flex align-items-center mt-1">
                                <?php $target_type = 'comment'; $target_id = $comment['id']; include 'includes/reaction_ui.php'; ?>
                                <button class="btn btn-link btn-sm text-muted text-decoration-none p-0 ms-3" type="button" data-bs-toggle="collapse" data-bs-target="#reply-<?php echo $comment['id']; ?>">Reply</button>
                            </div>

                            <!-- Reply Form -->
                            <div class="collapse mt-2" id="reply-<?php echo $comment['id']; ?>">
                                <form action="add_comment.php" method="POST" class="d-flex">
                                    <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                    <input type="hidden" name="parent_comment_id" value="<?php echo $comment['id']; ?>">
                                    <input type="text" name="content" class="form-control form-control-sm rounded-pill bg-secondary text-light border-0 me-2" placeholder="Write a reply..." required autocomplete="off">
                                    <button type="submit" class="btn btn-sm btn-primary rounded-pill"><i class="fa-solid fa-paper-plane"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if(empty($comments)): ?>
                    <p class="text-muted small text-center">No comments yet.</p>
                <?php endif; ?>
            </div>

            <!-- Add Comment Form -->
            <form action="add_comment.php" method="POST" class="d-flex">
                <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                <?php
                if (!isset($current_user_avatar)) {
                    $avStmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
                    $avStmt->execute([$_SESSION['user_id']]);
                    $av = $avStmt->fetch();
                    $current_user_avatar = $av['avatar'] ?? '/assets/img/default-avatar.png';
                }
                ?>
                <img src="<?php echo htmlspecialchars($current_user_avatar ?: '/assets/img/default-avatar.png'); ?>" class="rounded-circle me-2" width="35" height="35" style="object-fit:cover;">
                <input type="text" name="content" class="form-control rounded-pill bg-dark text-light border-secondary me-2" placeholder="Write a comment..." required autocomplete="off">
                <button type="submit" class="btn btn-primary rounded-pill"><i class="fa-solid fa-paper-plane"></i></button>
            </form>
        </div>
    </div>
</div>