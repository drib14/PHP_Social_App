<?php
// profile.php
require_once 'includes/header.php';
require_once 'includes/cloudinary.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$conn = getDbConnection();
$current_user_id = $_SESSION['user_id'];
$profile_id = isset($_GET['id']) ? (int)$_GET['id'] : $current_user_id;

// Fetch Profile User Info
$stmt = $conn->prepare("SELECT id, username, bio, avatar_url, cover_url FROM users WHERE id = ?");
$stmt->bind_param("i", $profile_id);
$stmt->execute();
$profile_user = $stmt->get_result()->fetch_assoc();

if (!$profile_user) {
    echo "<div class='alert alert-danger'>User not found.</div>";
    require_once 'includes/footer.php';
    exit;
}

$is_own_profile = ($current_user_id === $profile_id);
$msg = "";

// Handle Profile Updates
if ($is_own_profile && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $bio = $_POST['bio'] ?? $profile_user['bio'];

    $new_avatar_url = $profile_user['avatar_url'];
    $new_cover_url = $profile_user['cover_url'];

    // Check avatar upload
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $uploaded_url = uploadToCloudinary($_FILES['avatar']['tmp_name']);
        if ($uploaded_url) $new_avatar_url = $uploaded_url;
    }

    // Check cover upload
    if (isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
        $uploaded_url = uploadToCloudinary($_FILES['cover']['tmp_name']);
        if ($uploaded_url) $new_cover_url = $uploaded_url;
    }

    $updateStmt = $conn->prepare("UPDATE users SET bio = ?, avatar_url = ?, cover_url = ? WHERE id = ?");
    $updateStmt->bind_param("sssi", $bio, $new_avatar_url, $new_cover_url, $current_user_id);
    if ($updateStmt->execute()) {
        $msg = "<div class='alert alert-success'>Profile updated successfully!</div>";
        // Update session avatar
        $_SESSION['avatar_url'] = $new_avatar_url;
        // Refresh data
        $profile_user['bio'] = $bio;
        $profile_user['avatar_url'] = $new_avatar_url;
        $profile_user['cover_url'] = $new_cover_url;
    } else {
        $msg = "<div class='alert alert-danger'>Failed to update profile.</div>";
    }
}

$avatar = $profile_user['avatar_url'] ? $profile_user['avatar_url'] : 'https://ui-avatars.com/api/?name='.urlencode($profile_user['username']).'&background=10b981&color=fff';
$cover = $profile_user['cover_url'] ? $profile_user['cover_url'] : 'https://via.placeholder.com/800x200/1e1e1e/333333?text=Cover+Photo';

// Check connection status if not own profile
$connection_status = null;
if (!$is_own_profile) {
    $cStmt = $conn->prepare("SELECT status, requester_id FROM connections WHERE (requester_id = ? AND receiver_id = ?) OR (requester_id = ? AND receiver_id = ?)");
    $cStmt->bind_param("iiii", $current_user_id, $profile_id, $profile_id, $current_user_id);
    $cStmt->execute();
    $cRes = $cStmt->get_result();
    if ($cRow = $cRes->fetch_assoc()) {
        $connection_status = $cRow;
    }
}
?>

<div class="row">
    <div class="col-12">
        <?php echo $msg; ?>
        <div class="card mb-4 position-relative">
            <div style="height: 250px; background-image: url('<?php echo $cover; ?>'); background-size: cover; background-position: center; border-radius: 4px 4px 0 0;"></div>

            <div class="card-body position-relative text-center pb-4" style="margin-top: -60px;">
                <img src="<?php echo $avatar; ?>" class="rounded-circle border border-4 border-dark mb-3" style="width: 120px; height: 120px; object-fit: cover;">
                <h2 class="h4"><?php echo htmlspecialchars($profile_user['username']); ?></h2>
                <p class="text-secondary"><?php echo htmlspecialchars($profile_user['bio'] ?? 'No bio yet.'); ?></p>

                <?php if ($is_own_profile): ?>
                    <button class="btn btn-outline-emerald mt-2" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                        <i class="fa-solid fa-pen"></i> Edit Profile
                    </button>
                <?php else: ?>
                    <div class="mt-3" id="connection-container">
                        <?php if (!$connection_status): ?>
                            <button class="btn btn-emerald" onclick="handleConnection(<?php echo $profile_id; ?>, 'request')"><i class="fa-solid fa-user-plus"></i> Connect</button>
                        <?php elseif ($connection_status['status'] === 'pending'): ?>
                            <?php if ($connection_status['requester_id'] === $current_user_id): ?>
                                <button class="btn btn-secondary" disabled>Pending Request</button>
                            <?php else: ?>
                                <button class="btn btn-emerald" onclick="handleConnection(<?php echo $profile_id; ?>, 'accept')">Accept</button>
                                <button class="btn btn-danger" onclick="handleConnection(<?php echo $profile_id; ?>, 'reject')">Reject</button>
                            <?php endif; ?>
                        <?php elseif ($connection_status['status'] === 'accepted'): ?>
                            <button class="btn btn-outline-emerald" disabled><i class="fa-solid fa-user-check"></i> Connected</button>
                            <a href="chat.php?user=<?php echo $profile_id; ?>" class="btn btn-emerald ms-2"><i class="fa-solid fa-message"></i> Message</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Edit Profile Modal -->
<?php if ($is_own_profile): ?>
<div class="modal fade text-dark" id="editProfileModal" tabindex="-1" aria-hidden="true" data-bs-theme="dark">
  <div class="modal-dialog">
    <div class="modal-content" style="background-color: var(--card-bg); color: var(--text-primary);">
      <div class="modal-header border-secondary">
        <h5 class="modal-title">Edit Profile</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" enctype="multipart/form-data">
          <div class="modal-body">
            <div class="mb-3">
                <label class="form-label">Bio</label>
                <textarea name="bio" class="form-control" rows="3"><?php echo htmlspecialchars($profile_user['bio'] ?? ''); ?></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">Profile Picture</label>
                <input type="file" name="avatar" class="form-control" accept="image/*">
            </div>
            <div class="mb-3">
                <label class="form-label">Cover Photo</label>
                <input type="file" name="cover" class="form-control" accept="image/*">
            </div>
          </div>
          <div class="modal-footer border-secondary">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-emerald">Save Changes</button>
          </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- User Posts Feed Section -->
<div class="row justify-content-center mt-4">
    <div class="col-md-8">
        <h4 class="mb-3">Posts</h4>
        <div id="profile-feed">
            <!-- Skeletal Loaders -->
            <div class="card mb-3 skeleton-container">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="skeleton skeleton-avatar me-2"></div>
                        <div class="skeleton skeleton-text short"></div>
                    </div>
                    <div class="skeleton skeleton-text"></div>
                    <div class="skeleton skeleton-text"></div>
                    <div class="skeleton skeleton-text short"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Connection handling logic placeholder
    function handleConnection(userId, action) {
        $.post('api/connection.php', { user_id: userId, action: action }, function(response) {
            location.reload(); // simple reload to update state
        });
    }

    // Load User Posts
    $(document).ready(function() {
        $.get('api/get_posts.php?user_id=<?php echo $profile_id; ?>', function(res) {
            $('#profile-feed').html(res);
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>