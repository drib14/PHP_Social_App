<?php
require_once 'db.php';
require_once 'cloudinary_helper.php';
include 'partials/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$success = "";

// Fetch current info
$stmt = $conn->prepare("SELECT name, bio, profile_pic_type, cover_photo_type FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name'] ?? '');
    $bio = trim($_POST['bio'] ?? '');

    if (!empty($name)) {
        $update = $conn->prepare("UPDATE users SET name=?, bio=? WHERE id=?");
        $update->bind_param("ssi", $name, $bio, $user_id);
        $update->execute();

        $user['name'] = $name;
        $user['bio'] = $bio;
        $success = "Profile details updated! ";
    }

    // Handle Profile Pic
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $tmp_name = $_FILES['profile_pic']['tmp_name'];
        $type = mime_content_type($tmp_name);

        if (str_starts_with($type, 'image/')) {
            $url = uploadToCloudinary($tmp_name, 'image');
            if ($url) {
                $upd_pic = $conn->prepare("UPDATE users SET profile_pic=?, profile_pic_type=? WHERE id=?");
                $upd_pic->bind_param("ssi", $url, $type, $user_id);
                $upd_pic->execute();
                $success .= "Profile picture updated. ";
            }
        }
    }

    // Handle Cover Photo
    if (isset($_FILES['cover_photo']) && $_FILES['cover_photo']['error'] === UPLOAD_ERR_OK) {
        $tmp_name = $_FILES['cover_photo']['tmp_name'];
        $type = mime_content_type($tmp_name);

        if (str_starts_with($type, 'image/')) {
            $url = uploadToCloudinary($tmp_name, 'image');
            if ($url) {
                $upd_cover = $conn->prepare("UPDATE users SET cover_photo=?, cover_photo_type=? WHERE id=?");
                $upd_cover->bind_param("ssi", $url, $type, $user_id);
                $upd_cover->execute();
                $success .= "Cover photo updated. ";
            }
        }
    }

    if ($success) {
        // Refresh session data if needed
        $_SESSION['user_name'] = $name;
    }
}
?>

<div class="card p-4" style="max-width:600px; margin:auto; background-color: var(--bg-secondary);">
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom border-secondary pb-3">
        <h3 class="fw-bold mb-0">Edit Profile</h3>
        <a href="user_profile.php" class="btn btn-outline-light rounded-pill btn-sm px-3">View Profile</a>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success" style="background: rgba(16, 185, 129, 0.1); border-color: var(--accent-color); color: #6ee7b7; border-radius: 12px;">
            <i class="fa-solid fa-check-circle me-2"></i><?= $success ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">

        <!-- Profile Picture Section -->
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="fw-bold mb-0">Profile Picture</h5>
            <label for="profileUpload" class="btn btn-link text-decoration-none p-0" style="color: var(--accent-color);">Edit</label>
            <input type="file" name="profile_pic" id="profileUpload" class="d-none" accept="image/*">
        </div>
        <div class="text-center mb-4">
            <?php if ($user['profile_pic_type']): ?>
                <img src="media.php?type=profile&id=<?= $user_id ?>" class="rounded-circle border border-4 border-dark object-fit-cover" style="width: 150px; height: 150px; border-color: var(--bg-secondary) !important;">
            <?php else: ?>
                <div class="avatar-placeholder rounded-circle mx-auto border border-4 border-dark" style="width: 150px; height: 150px; font-size: 4rem; background-color: var(--accent-color); border-color: var(--bg-secondary) !important;">
                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                </div>
            <?php endif; ?>
            <div id="profileFileText" class="text-muted small mt-2 d-none">New file selected</div>
        </div>

        <!-- Cover Photo Section -->
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="fw-bold mb-0">Cover Photo</h5>
            <label for="coverUpload" class="btn btn-link text-decoration-none p-0" style="color: var(--accent-color);">Edit</label>
            <input type="file" name="cover_photo" id="coverUpload" class="d-none" accept="image/*">
        </div>
        <div class="mb-4 text-center">
            <?php if ($user['cover_photo_type']): ?>
                <img src="media.php?type=cover&id=<?= $user_id ?>" class="w-100 rounded object-fit-cover" style="height: 180px;">
            <?php else: ?>
                <div class="w-100 rounded d-flex align-items-center justify-content-center text-muted" style="height: 180px; background: linear-gradient(135deg, #1e293b, var(--bg-hover));">
                    <i class="fa-regular fa-image fs-1 opacity-50"></i>
                </div>
            <?php endif; ?>
            <div id="coverFileText" class="text-muted small mt-2 d-none">New cover photo selected</div>
        </div>

        <hr class="border-secondary mb-4">

        <!-- Details Section -->
        <h5 class="fw-bold mb-3">Details</h5>
        <div class="mb-3">
            <label class="form-label text-muted small fw-bold">Name</label>
            <input type="text" name="name" class="form-control bg-dark border-secondary text-white" value="<?= htmlspecialchars($user['name']) ?>" required>
        </div>

        <div class="mb-4">
            <label class="form-label text-muted small fw-bold">Bio</label>
            <textarea name="bio" class="form-control bg-dark border-secondary text-white text-center" rows="3" placeholder="Describe yourself..."><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2 fs-5 rounded-pill">Save Changes</button>
    </form>
</div>

<script>
    document.getElementById('profileUpload').addEventListener('change', function() {
        if(this.files.length) {
            document.getElementById('profileFileText').classList.remove('d-none');
        }
    });
    document.getElementById('coverUpload').addEventListener('change', function() {
        if(this.files.length) {
            document.getElementById('coverFileText').classList.remove('d-none');
        }
    });
</script>

<?php include 'partials/footer.php'; ?>