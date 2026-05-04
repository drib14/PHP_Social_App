<?php
// index.php
require_once 'includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$current_user_id = $_SESSION['user_id'];
$avatar = isset($_SESSION['avatar_url']) && $_SESSION['avatar_url'] ? $_SESSION['avatar_url'] : 'https://ui-avatars.com/api/?name='.urlencode($_SESSION['username']).'&background=10b981&color=fff';
?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">

        <!-- Create Post Section -->
        <div class="card mb-4">
            <div class="card-body">
                <form id="create-post-form" enctype="multipart/form-data">
                    <div class="d-flex mb-3">
                        <img src="<?php echo htmlspecialchars($avatar); ?>" class="rounded-circle me-3" style="width: 40px; height: 40px; object-fit: cover;">
                        <textarea name="content" class="form-control" rows="2" placeholder="What's on your mind, <?php echo htmlspecialchars($_SESSION['username']); ?>?" required></textarea>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="position-relative">
                            <input type="file" name="media" id="media-upload" class="d-none" accept="image/*,video/*">
                            <label for="media-upload" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                                <i class="fa-solid fa-image text-emerald"></i> Photo/Video
                            </label>
                            <span id="media-filename" class="ms-2 text-secondary small"></span>
                        </div>
                        <button type="button" class="btn btn-emerald rounded-pill px-4" id="post-btn">Post</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Feed Container -->
        <div id="news-feed">
            <!-- Skeletal Loader -->
            <div class="card mb-4 skeleton-container">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="skeleton skeleton-avatar me-3"></div>
                        <div class="w-100">
                            <div class="skeleton skeleton-text w-50"></div>
                            <div class="skeleton skeleton-text w-25 mb-0"></div>
                        </div>
                    </div>
                    <div class="skeleton skeleton-text"></div>
                    <div class="skeleton skeleton-text"></div>
                    <div class="skeleton skeleton-img mt-3"></div>
                </div>
            </div>
            <div class="card mb-4 skeleton-container">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="skeleton skeleton-avatar me-3"></div>
                        <div class="w-100">
                            <div class="skeleton skeleton-text w-50"></div>
                            <div class="skeleton skeleton-text w-25 mb-0"></div>
                        </div>
                    </div>
                    <div class="skeleton skeleton-text"></div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
$(document).ready(function() {

    // Display selected filename
    $('#media-upload').change(function() {
        let file = this.files[0];
        if (file) {
            $('#media-filename').text(file.name);
        } else {
            $('#media-filename').text('');
        }
    });

    // Handle post creation
    $('#post-btn').on('click', function(e) {
        e.preventDefault();

        let form = $('#create-post-form')[0];
        if(!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        let formData = new FormData(form);
        let btn = $('#post-btn');
        btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Posting...');

        $.ajax({
            url: '<?php echo BASE_URL; ?>/api/create_post.php',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function(response) {
                if(response.success) {
                    $('#create-post-form')[0].reset();
                    $('#media-filename').text('');
                    loadFeed(); // Reload feed
                } else {
                    alert(response.message || 'Error creating post.');
                }
            },
            complete: function() {
                btn.prop('disabled', false).text('Post');
            }
        });
    });

    // Load feed function
    function loadFeed() {
        $.get('<?php echo BASE_URL; ?>/api/get_posts.php', function(data) {
            $('#news-feed').html(data);
        }).fail(function() {
            $('#news-feed').html('<div class="alert alert-danger">Error loading feed.</div>');
        });
    }

    // Initial load
    setTimeout(loadFeed, 1000); // Slight delay to show skeletal loading for demo purposes
});

// Reaction logic
$(document).on('mouseenter', '.reaction-container', function() {
    $(this).find('.reaction-popup').fadeIn(200);
}).on('mouseleave', '.reaction-container', function() {
    $(this).find('.reaction-popup').fadeOut(200);
});

function toggleReaction(postId, reactionType) {
    event.stopPropagation();
    $.post('<?php echo BASE_URL; ?>/api/reaction.php', { post_id: postId, reaction: reactionType }, function(res) {
        if(res.success) {
            // Simple reload feed for immediate sync, can be optimized later
            $.get('<?php echo BASE_URL; ?>/api/get_posts.php', function(data) {
                $('#news-feed').html(data);
            });
        }
    });
}

function promptCustomReaction(postId) {
    event.stopPropagation();
    let emoji = prompt("Enter an emoji for your custom reaction:");
    if (emoji) {
        // basic check if it looks like an emoji or short text
        if(emoji.length > 0 && emoji.length <= 4) {
            toggleReaction(postId, emoji);
        } else {
            alert('Please enter a valid single emoji.');
        }
    }
}

// Comment submission
function submitComment(postId) {
    let input = $('#comment-input-' + postId);
    let content = input.val().trim();
    if (content === '') return;

    $.post('<?php echo BASE_URL; ?>/api/create_comment.php', { post_id: postId, content: content }, function(res) {
        if (res.success) {
            input.val('');
            // Simple reload of the feed or append comment logic here
            $.get('<?php echo BASE_URL; ?>/api/get_posts.php', function(data) {
                $('#news-feed').html(data);
            });
        }
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>