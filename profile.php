<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/cloudinary.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_GET['id'] ?? $_SESSION['user_id'];
$is_owner = ($user_id == $_SESSION['user_id']);

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$profile_user = $stmt->fetch();

if (!$profile_user) {
    echo "User not found.";
    exit();
}

$message = '';

if ($is_owner && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $bio = trim($_POST['bio'] ?? '');
    $birthday = $_POST['birthday'] ?? null;
    if (empty($birthday)) $birthday = null;
    $relationship_status = $_POST['relationship_status'] ?? '';
    $favorites = trim($_POST['favorites'] ?? '');
    $profile_audience = $_POST['profile_audience'] ?? 'public';

    // Handle File Uploads (Avatar & Cover)
    $avatar_url = $profile_user['avatar'];
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $uploaded = uploadToCloudinary($_FILES['avatar']['tmp_name'], 'image');
        if ($uploaded) $avatar_url = $uploaded;
    }

    $cover_url = $profile_user['cover_photo'];
    if (isset($_FILES['cover_photo']) && $_FILES['cover_photo']['error'] === UPLOAD_ERR_OK) {
        $uploaded = uploadToCloudinary($_FILES['cover_photo']['tmp_name'], 'image');
        if ($uploaded) $cover_url = $uploaded;
    }

    $updateStmt = $pdo->prepare("UPDATE users SET bio = ?, birthday = ?, relationship_status = ?, favorites = ?, profile_audience = ?, avatar = ?, cover_photo = ? WHERE id = ?");
    if ($updateStmt->execute([$bio, $birthday, $relationship_status, $favorites, $profile_audience, $avatar_url, $cover_url, $user_id])) {
        $message = "Profile updated successfully!";
        // Refresh data
        $stmt->execute([$user_id]);
        $profile_user = $stmt->fetch();
    } else {
        $message = "Failed to update profile.";
    }
}

// Check Connection Status (for non-owner view)
$connection_status = 'none'; // none, pending_sent, pending_received, accepted
if (!$is_owner) {
    $connStmt = $pdo->prepare("SELECT * FROM connections WHERE (requester_id = ? AND receiver_id = ?) OR (requester_id = ? AND receiver_id = ?)");
    $connStmt->execute([$_SESSION['user_id'], $user_id, $user_id, $_SESSION['user_id']]);
    $connection = $connStmt->fetch();

    if ($connection) {
        if ($connection['status'] === 'accepted') {
            $connection_status = 'accepted';
        } elseif ($connection['status'] === 'pending') {
            if ($connection['requester_id'] == $_SESSION['user_id']) {
                $connection_status = 'pending_sent';
            } else {
                $connection_status = 'pending_received';
            }
        }
    }
}

// Determine if we can view full profile based on audience rules
$can_view_details = false;
if ($is_owner) {
    $can_view_details = true;
} else if ($profile_user['profile_audience'] === 'public') {
    $can_view_details = true;
} else if ($profile_user['profile_audience'] === 'connections' && $connection_status === 'accepted') {
    $can_view_details = true;
} else if ($profile_user['profile_audience'] === 'only_me') {
    $can_view_details = true; // Based on rules: "Only Me / Profile Only means anyone visiting profile can see it"
}

?>

<?php include 'includes/header.php'; ?>

<div class="row">
    <div class="col-12">
        <div class="card mb-4 position-relative">
            <img src="<?php echo $profile_user['cover_photo'] ? htmlspecialchars($profile_user['cover_photo']) : '/assets/img/default-cover.jpg'; ?>" class="cover-photo" alt="Cover Photo">

            <div class="card-body text-center">
                <img src="<?php echo $profile_user['avatar'] ? htmlspecialchars($profile_user['avatar']) : '/assets/img/default-avatar.png'; ?>" class="profile-avatar bg-dark" alt="Avatar">

                <h3 class="mt-2 mb-1"><?php echo htmlspecialchars($profile_user['first_name'] . ' ' . $profile_user['last_name']); ?></h3>
                <p class="text-muted">@<?php echo htmlspecialchars($profile_user['username']); ?></p>

                <?php if (!$is_owner): ?>
                    <div class="mt-3">
                        <?php if ($connection_status === 'none'): ?>
                            <form method="POST" action="connect.php" class="d-inline">
                                <input type="hidden" name="action" value="send">
                                <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-user-plus"></i> Connect</button>
                            </form>
                        <?php elseif ($connection_status === 'pending_sent'): ?>
                            <button class="btn btn-secondary disabled"><i class="fa-solid fa-clock"></i> Request Sent</button>
                        <?php elseif ($connection_status === 'pending_received'): ?>
                            <form method="POST" action="connect.php" class="d-inline">
                                <input type="hidden" name="action" value="accept">
                                <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Accept</button>
                            </form>
                            <form method="POST" action="connect.php" class="d-inline">
                                <input type="hidden" name="action" value="decline">
                                <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                                <button type="submit" class="btn btn-complementary"><i class="fa-solid fa-xmark"></i> Decline</button>
                            </form>
                        <?php elseif ($connection_status === 'accepted'): ?>
                            <button class="btn btn-success disabled"><i class="fa-solid fa-user-check"></i> Connected</button>
                        <?php endif; ?>
                        <a href="messages.php?user=<?php echo $user_id; ?>" class="btn btn-secondary ms-2"><i class="fa-solid fa-message"></i> Message</a>
                    </div>
                <?php else: ?>
                    <button class="btn btn-secondary mt-2" data-bs-toggle="modal" data-bs-target="#editProfileModal"><i class="fa-solid fa-pen"></i> Edit Profile</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- About Section -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header fw-bold">About</div>
            <div class="card-body">
                <?php if ($can_view_details): ?>
                    <?php if ($profile_user['bio']): ?>
                        <p><?php echo nl2br(htmlspecialchars($profile_user['bio'])); ?></p>
                        <hr>
                    <?php endif; ?>
                    <ul class="list-unstyled mb-0">
                        <?php if ($profile_user['birthday']): ?>
                            <li class="mb-2"><i class="fa-solid fa-cake-candles text-muted me-2"></i> Born <?php echo date('F j, Y', strtotime($profile_user['birthday'])); ?></li>
                        <?php endif; ?>
                        <?php if ($profile_user['relationship_status']): ?>
                            <li class="mb-2"><i class="fa-solid fa-heart text-muted me-2"></i> <?php echo htmlspecialchars($profile_user['relationship_status']); ?></li>
                        <?php endif; ?>
                        <?php if ($profile_user['favorites']): ?>
                            <li class="mb-2"><i class="fa-solid fa-star text-muted me-2"></i> Favorites: <?php echo htmlspecialchars($profile_user['favorites']); ?></li>
                        <?php endif; ?>
                    </ul>
                    <?php if (!$profile_user['bio'] && !$profile_user['birthday'] && !$profile_user['relationship_status'] && !$profile_user['favorites']): ?>
                        <p class="text-muted mb-0">No details provided yet.</p>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-muted mb-0"><i class="fa-solid fa-lock me-2"></i>This user's profile is private.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Connections List -->
        <div class="card">
            <div class="card-header fw-bold">Connections</div>
            <div class="card-body">
                <?php
                $connListStmt = $pdo->prepare("
                    SELECT u.id, u.username, u.first_name, u.last_name, u.avatar
                    FROM connections c
                    JOIN users u ON (u.id = c.requester_id OR u.id = c.receiver_id) AND u.id != ?
                    WHERE (c.requester_id = ? OR c.receiver_id = ?) AND c.status = 'accepted'
                    LIMIT 6
                ");
                $connListStmt->execute([$user_id, $user_id, $user_id]);
                $connections = $connListStmt->fetchAll();

                if (count($connections) > 0): ?>
                    <div class="row g-2">
                        <?php foreach($connections as $conn): ?>
                            <div class="col-4 text-center">
                                <a href="profile.php?id=<?php echo $conn['id']; ?>" class="text-decoration-none text-light">
                                    <img src="<?php echo $conn['avatar'] ? htmlspecialchars($conn['avatar']) : '/assets/img/default-avatar.png'; ?>" class="img-fluid rounded" alt="Avatar">
                                    <small class="d-block mt-1 text-truncate"><?php echo htmlspecialchars($conn['first_name']); ?></small>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">No connections yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- User Posts Section -->
    <div class="col-md-8">
        <!-- Post Create Form (Only for owner) -->
        <?php if($is_owner): include 'includes/post_form.php'; endif; ?>

        <!-- Posts Feed -->
        <div id="profileFeed">
            <?php
            // Fetch posts for this user based on viewing rules
            $pStmt = $pdo->prepare("
                SELECT p.*, u.username, u.first_name, u.last_name, u.avatar
                FROM posts p
                JOIN users u ON p.user_id = u.id
                WHERE p.user_id = :uid AND (
                    :is_owner = 1
                    OR p.audience = 'public'
                    OR p.audience = 'only_me'
                    OR (p.audience = 'connections' AND :is_conn = 'accepted')
                )
                ORDER BY p.created_at DESC
            ");
            $pStmt->bindValue(':uid', $user_id);
            $pStmt->bindValue(':is_owner', $is_owner ? 1 : 0);
            $pStmt->bindValue(':is_conn', $connection_status);
            $pStmt->execute();
            $posts = $pStmt->fetchAll();

            if (count($posts) > 0) {
                foreach($posts as $post) {
                    include 'includes/post_card.php';
                }
            } else {
                echo '<div class="card bg-dark border-secondary"><div class="card-body"><p class="text-center text-muted my-3">No posts to display.</p></div></div>';
            }
            ?>
        </div>
    </div>
</div>

<?php if ($is_owner): ?>
<!-- Edit Profile Modal -->
<div class="modal fade" id="editProfileModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark text-light border-secondary">
      <div class="modal-header border-secondary">
        <h5 class="modal-title">Edit Profile</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" enctype="multipart/form-data">
        <div class="modal-body">
            <?php if($message): ?>
                <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <input type="hidden" name="update_profile" value="1">
            <div class="mb-3">
                <label class="form-label">Avatar</label>
                <input type="file" class="form-control" name="avatar" accept="image/*">
            </div>
            <div class="mb-3">
                <label class="form-label">Cover Photo</label>
                <input type="file" class="form-control" name="cover_photo" accept="image/*">
            </div>
            <div class="mb-3">
                <label class="form-label">Bio</label>
                <textarea class="form-control" name="bio" rows="3"><?php echo htmlspecialchars($profile_user['bio'] ?? ''); ?></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">Birthday</label>
                <input type="date" class="form-control" name="birthday" value="<?php echo htmlspecialchars($profile_user['birthday'] ?? ''); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Relationship Status</label>
                <select class="form-control" name="relationship_status">
                    <option value="" <?php if(empty($profile_user['relationship_status'])) echo 'selected'; ?>>Unspecified</option>
                    <option value="Single" <?php if($profile_user['relationship_status']=='Single') echo 'selected'; ?>>Single</option>
                    <option value="In a relationship" <?php if($profile_user['relationship_status']=='In a relationship') echo 'selected'; ?>>In a relationship</option>
                    <option value="Married" <?php if($profile_user['relationship_status']=='Married') echo 'selected'; ?>>Married</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Favorites (Hobbies, Music, etc.)</label>
                <input type="text" class="form-control" name="favorites" value="<?php echo htmlspecialchars($profile_user['favorites'] ?? ''); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Profile Audience Details</label>
                <select class="form-control" name="profile_audience">
                    <option value="public" <?php if($profile_user['profile_audience']=='public') echo 'selected'; ?>>Public</option>
                    <option value="connections" <?php if($profile_user['profile_audience']=='connections') echo 'selected'; ?>>Connections Only</option>
                    <option value="only_me" <?php if($profile_user['profile_audience']=='only_me') echo 'selected'; ?>>Profile Only</option>
                </select>
                <small class="text-muted">Controls who sees your detailed info.</small>
            </div>
        </div>
        <div class="modal-footer border-secondary">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary">Save changes</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php if($message): ?>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        var editModal = new bootstrap.Modal(document.getElementById('editProfileModal'));
        editModal.show();
    });
</script>
<?php endif; ?>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>