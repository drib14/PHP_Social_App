<?php
// api/get_posts.php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    echo "Unauthorized";
    exit;
}

$conn = getDbConnection();
$current_user_id = $_SESSION['user_id'];
$profile_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;

function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}

$query = "SELECT p.*, u.username, u.avatar_url
          FROM posts p
          JOIN users u ON p.user_id = u.id ";

if ($profile_user_id) {
    // Show only specific user's posts
    $query .= "WHERE p.user_id = $profile_user_id ";
} else {
    // Home feed: own posts + connected users' posts
    $query .= "WHERE p.user_id = $current_user_id OR p.user_id IN (
                   SELECT receiver_id FROM connections WHERE requester_id = $current_user_id AND status = 'accepted'
                   UNION
                   SELECT requester_id FROM connections WHERE receiver_id = $current_user_id AND status = 'accepted'
               ) ";
}

$query .= "ORDER BY p.created_at DESC LIMIT 50";

$result = $conn->query($query);

if ($result->num_rows === 0) {
    echo "<div class='text-center text-secondary py-3'>No posts to show.</div>";
    exit;
}

while ($post = $result->fetch_assoc()) {
    $avatar = $post['avatar_url'] ? htmlspecialchars($post['avatar_url']) : 'https://ui-avatars.com/api/?name='.urlencode($post['username']).'&background=10b981&color=fff';
    $post_id = $post['id'];

    // Fetch comments
    $cStmt = $conn->prepare("SELECT c.*, u.username, u.avatar_url FROM comments c JOIN users u ON c.user_id = u.id WHERE c.post_id = ? ORDER BY c.created_at ASC");
    $cStmt->bind_param("i", $post_id);
    $cStmt->execute();
    $comments = $cStmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Group comments into parent/replies
    $parent_comments = [];
    $replies = [];
    foreach($comments as $c) {
        if ($c['parent_id']) {
            $replies[$c['parent_id']][] = $c;
        } else {
            $parent_comments[] = $c;
        }
    }

    // Fetch reactions
    $rStmt = $conn->prepare("SELECT reaction_type, COUNT(*) as count FROM reactions WHERE post_id = ? GROUP BY reaction_type");
    $rStmt->bind_param("i", $post_id);
    $rStmt->execute();
    $reaction_data = $rStmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $reactions_html = "";
    $total_reactions = 0;
    foreach($reaction_data as $r) {
        $total_reactions += $r['count'];
        $icon = '';
        if($r['reaction_type'] == 'like') $icon = '👍';
        elseif($r['reaction_type'] == 'love') $icon = '❤️';
        elseif($r['reaction_type'] == 'haha') $icon = '😆';
        elseif($r['reaction_type'] == 'wow') $icon = '😮';
        else $icon = htmlspecialchars($r['reaction_type']); // Custom emoji

        $reactions_html .= "<span class='me-1'>$icon {$r['count']}</span>";
    }

    // Check user's reaction
    $myRStmt = $conn->prepare("SELECT reaction_type FROM reactions WHERE post_id = ? AND user_id = ?");
    $myRStmt->bind_param("ii", $post_id, $current_user_id);
    $myRStmt->execute();
    $myRRes = $myRStmt->get_result();
    $my_reaction = $myRRes->num_rows > 0 ? $myRRes->fetch_assoc()['reaction_type'] : null;

    ?>
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex align-items-center mb-3">
                <img src="<?php echo $avatar; ?>" class="rounded-circle me-3" style="width: 45px; height: 45px; object-fit: cover;">
                <div>
                    <a href="profile.php?id=<?php echo $post['user_id']; ?>" class="h6 mb-0 text-white text-decoration-none fw-bold"><?php echo htmlspecialchars($post['username']); ?></a>
                    <div class="text-secondary small"><?php echo time_elapsed_string($post['created_at']); ?></div>
                </div>
            </div>

            <?php if (!empty($post['content'])): ?>
                <p class="card-text" style="white-space: pre-wrap;"><?php echo htmlspecialchars($post['content']); ?></p>
            <?php endif; ?>

            <?php if ($post['media_url']): ?>
                <?php
                $ext = pathinfo(parse_url($post['media_url'], PHP_URL_PATH), PATHINFO_EXTENSION);
                if (in_array(strtolower($ext), ['mp4', 'webm', 'ogg'])):
                ?>
                    <video src="<?php echo htmlspecialchars($post['media_url']); ?>" controls class="img-fluid rounded w-100 mb-3" style="max-height: 500px; background: #000;"></video>
                <?php else: ?>
                    <img src="<?php echo htmlspecialchars($post['media_url']); ?>" class="img-fluid rounded w-100 mb-3" style="max-height: 500px; object-fit: cover;">
                <?php endif; ?>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center border-bottom border-secondary pb-2 mb-2">
                <div class="text-secondary small">
                    <?php echo $total_reactions > 0 ? $reactions_html : 'No reactions'; ?>
                </div>
                <div class="text-secondary small">
                    <?php echo count($comments); ?> Comments
                </div>
            </div>

            <div class="d-flex mb-3 action-buttons position-relative">
                <div class="flex-fill text-center reaction-container" data-post="<?php echo $post_id; ?>">
                    <button class="btn btn-sm text-secondary w-100 reaction-btn" onclick="toggleReaction(<?php echo $post_id; ?>, 'like')">
                        <?php if($my_reaction == 'like'): ?>
                            <i class="fa-solid fa-thumbs-up text-emerald"></i> <span class="text-emerald">Like</span>
                        <?php elseif($my_reaction == 'love'): ?>
                            ❤️ <span style="color: #e0245e;">Love</span>
                        <?php elseif($my_reaction == 'haha'): ?>
                            😆 <span style="color: #f5c33b;">Haha</span>
                        <?php elseif($my_reaction == 'wow'): ?>
                            😮 <span style="color: #f5c33b;">Wow</span>
                        <?php elseif($my_reaction): ?>
                            <?php echo htmlspecialchars($my_reaction); ?> <span class="text-emerald">Reacted</span>
                        <?php else: ?>
                            <i class="fa-regular fa-thumbs-up"></i> Like
                        <?php endif; ?>
                    </button>
                    <!-- Reaction Popup -->
                    <div class="reaction-popup bg-dark rounded-pill shadow px-2 py-1 position-absolute" style="display:none; bottom: 100%; left: 0; z-index: 10;">
                        <span class="cursor-pointer fs-4 mx-1" onclick="toggleReaction(<?php echo $post_id; ?>, 'like')">👍</span>
                        <span class="cursor-pointer fs-4 mx-1" onclick="toggleReaction(<?php echo $post_id; ?>, 'love')">❤️</span>
                        <span class="cursor-pointer fs-4 mx-1" onclick="toggleReaction(<?php echo $post_id; ?>, 'haha')">😆</span>
                        <span class="cursor-pointer fs-4 mx-1" onclick="toggleReaction(<?php echo $post_id; ?>, 'wow')">😮</span>
                        <span class="cursor-pointer fs-4 mx-1 custom-react-btn" onclick="promptCustomReaction(<?php echo $post_id; ?>)"><i class="fa-solid fa-circle-plus text-emerald"></i></span>
                    </div>
                </div>
                <button class="btn btn-sm text-secondary flex-fill text-center" onclick="$('#comment-input-<?php echo $post_id; ?>').focus()">
                    <i class="fa-regular fa-comment"></i> Comment
                </button>
            </div>

            <!-- Comments Section -->
            <div class="comments-section">
                <?php foreach($parent_comments as $c):
                    $cAvatar = $c['avatar_url'] ? htmlspecialchars($c['avatar_url']) : 'https://ui-avatars.com/api/?name='.urlencode($c['username']).'&background=10b981&color=fff';
                ?>
                    <div class="d-flex mb-2">
                        <img src="<?php echo $cAvatar; ?>" class="rounded-circle me-2" style="width: 32px; height: 32px; object-fit: cover;">
                        <div class="flex-grow-1">
                            <div class="bg-dark rounded p-2">
                                <a href="profile.php?id=<?php echo $c['user_id']; ?>" class="fw-bold text-white text-decoration-none small"><?php echo htmlspecialchars($c['username']); ?></a>
                                <?php if (!empty($c['content'])): ?>
                                    <p class="mb-0 small text-light"><?php echo htmlspecialchars($c['content']); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($c['media_url'])): ?>
                                    <img src="<?php echo htmlspecialchars($c['media_url']); ?>" class="img-fluid rounded mt-2" style="max-height: 200px;">
                                <?php endif; ?>
                            </div>
                            <div class="text-secondary small ms-2 mt-1">
                                <a href="javascript:void(0)" class="text-secondary text-decoration-none" onclick="toggleReplyBox(<?php echo $c['id']; ?>)">Reply</a>
                            </div>

                            <!-- Replies -->
                            <?php if(isset($replies[$c['id']])): ?>
                                <div class="mt-2 ms-4 border-start border-secondary ps-3">
                                    <?php foreach($replies[$c['id']] as $reply):
                                        $rAvatar = $reply['avatar_url'] ? htmlspecialchars($reply['avatar_url']) : 'https://ui-avatars.com/api/?name='.urlencode($reply['username']).'&background=10b981&color=fff';
                                    ?>
                                        <div class="d-flex mb-2">
                                            <img src="<?php echo $rAvatar; ?>" class="rounded-circle me-2" style="width: 24px; height: 24px; object-fit: cover;">
                                            <div class="bg-dark rounded p-2 flex-grow-1">
                                                <a href="profile.php?id=<?php echo $reply['user_id']; ?>" class="fw-bold text-white text-decoration-none small" style="font-size: 0.8rem;"><?php echo htmlspecialchars($reply['username']); ?></a>
                                                <?php if (!empty($reply['content'])): ?>
                                                    <p class="mb-0 small text-light"><?php echo htmlspecialchars($reply['content']); ?></p>
                                                <?php endif; ?>
                                                <?php if (!empty($reply['media_url'])): ?>
                                                    <img src="<?php echo htmlspecialchars($reply['media_url']); ?>" class="img-fluid rounded mt-2" style="max-height: 150px;">
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Reply Box -->
                            <div id="reply-box-<?php echo $c['id']; ?>" class="d-none mt-2 ms-4">
                                <form onsubmit="submitComment(<?php echo $post_id; ?>, <?php echo $c['id']; ?>); return false;" id="form-comment-<?php echo $c['id']; ?>" class="d-flex align-items-center">
                                    <input type="text" name="content" class="form-control form-control-sm rounded-pill bg-dark text-white border-secondary me-2" placeholder="Write a reply...">
                                    <input type="file" name="media" id="reply-media-<?php echo $c['id']; ?>" class="d-none" accept="image/*,video/*">
                                    <label for="reply-media-<?php echo $c['id']; ?>" class="text-emerald cursor-pointer me-2 mb-0"><i class="fa-solid fa-camera"></i></label>
                                    <button type="submit" class="btn btn-sm btn-emerald rounded-pill"><i class="fa-solid fa-paper-plane"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="d-flex mt-3 border-top border-secondary pt-3">
                    <img src="<?php echo htmlspecialchars($_SESSION['avatar_url'] ?? 'https://ui-avatars.com/api/?name='.urlencode($_SESSION['username']).'&background=10b981&color=fff'); ?>" class="rounded-circle me-2" style="width: 32px; height: 32px; object-fit: cover;">
                    <form onsubmit="submitComment(<?php echo $post_id; ?>); return false;" id="form-comment-post-<?php echo $post_id; ?>" class="d-flex align-items-center flex-grow-1">
                        <input type="text" name="content" id="comment-input-<?php echo $post_id; ?>" class="form-control form-control-sm rounded-pill bg-dark text-white border-secondary me-2" placeholder="Write a comment...">
                        <input type="file" name="media" id="comment-media-<?php echo $post_id; ?>" class="d-none" accept="image/*,video/*">
                        <label for="comment-media-<?php echo $post_id; ?>" class="text-emerald cursor-pointer me-2 mb-0 fs-5"><i class="fa-solid fa-camera"></i></label>
                        <button type="submit" class="btn btn-sm btn-emerald rounded-pill"><i class="fa-solid fa-paper-plane"></i></button>
                    </form>
                </div>
            </div>

        </div>
    </div>
    <?php
}
?>