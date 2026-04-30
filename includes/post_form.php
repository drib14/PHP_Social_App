<!-- includes/post_form.php -->
<div class="card mb-4">
    <div class="card-body">
        <form id="postForm" action="create_post.php" method="POST" enctype="multipart/form-data">
            <div class="d-flex mb-2">
                <?php
                // Fetch current user avatar
                if (!isset($current_user_avatar)) {
                    $avStmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
                    $avStmt->execute([$_SESSION['user_id']]);
                    $av = $avStmt->fetch();
                    $current_user_avatar = $av['avatar'] ?? '/assets/img/default-avatar.png';
                }
                ?>
                <img src="<?php echo htmlspecialchars($current_user_avatar ?: '/assets/img/default-avatar.png'); ?>" class="rounded-circle me-2" width="40" height="40" alt="Avatar">
                <textarea class="form-control rounded-3 bg-dark text-light border-0" name="content" id="postContent" placeholder="What's on your mind? Mention using @username..." rows="2"></textarea>
            </div>

            <div id="mediaPreviewContainer" class="position-relative mb-2" style="display: none;">
                <button type="button" id="removeMediaBtn" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-2 z-3 rounded-circle"><i class="fa-solid fa-xmark"></i></button>
                <img id="imagePreview" src="" class="img-fluid rounded" style="display: none; max-height: 300px; width: 100%; object-fit: cover;">
                <video id="videoPreview" controls class="w-100 rounded" style="display: none; max-height: 300px;"></video>
            </div>

            <hr class="text-secondary">

            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <label for="mediaUpload" class="btn btn-sm btn-secondary text-success me-2" style="cursor:pointer;">
                        <i class="fa-solid fa-image"></i> Photo/Video
                    </label>
                    <input type="file" id="mediaUpload" name="media" accept="image/*,video/*" class="d-none">

                    <select name="audience" class="form-select form-select-sm d-inline-block w-auto bg-dark text-light border-secondary">
                        <option value="public">Public</option>
                        <option value="connections">Connections Only</option>
                        <option value="only_me">Only Me</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary px-4">Post</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const mediaUpload = document.getElementById('mediaUpload');
    const mediaPreviewContainer = document.getElementById('mediaPreviewContainer');
    const imagePreview = document.getElementById('imagePreview');
    const videoPreview = document.getElementById('videoPreview');
    const removeMediaBtn = document.getElementById('removeMediaBtn');

    mediaUpload.addEventListener('change', function(event) {
        const file = event.target.files[0];
        if (file) {
            const fileURL = URL.createObjectURL(file);
            mediaPreviewContainer.style.display = 'block';

            if (file.type.startsWith('image/')) {
                imagePreview.src = fileURL;
                imagePreview.style.display = 'block';
                videoPreview.style.display = 'none';
            } else if (file.type.startsWith('video/')) {
                videoPreview.src = fileURL;
                videoPreview.style.display = 'block';
                imagePreview.style.display = 'none';
            }
        }
    });

    removeMediaBtn.addEventListener('click', function() {
        mediaUpload.value = '';
        mediaPreviewContainer.style.display = 'none';
        imagePreview.src = '';
        videoPreview.src = '';
    });
});
</script>