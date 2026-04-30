<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/cloudinary.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$chat_user_id = $_GET['user'] ?? null;

// Search query for connections sidebar
$search_query = $_GET['q'] ?? '';

// Fetch users we have chatted with or are connected to
$sql = "
    SELECT u.id, u.username, u.first_name, u.last_name, u.avatar
    FROM users u
    WHERE u.id != :uid1 AND (
        u.id IN (SELECT sender_id FROM messages WHERE receiver_id = :uid2)
        OR u.id IN (SELECT receiver_id FROM messages WHERE sender_id = :uid3)
        OR u.id IN (SELECT requester_id FROM connections WHERE receiver_id = :uid4 AND status = 'accepted')
        OR u.id IN (SELECT receiver_id FROM connections WHERE requester_id = :uid5 AND status = 'accepted')
    )
";
if ($search_query) {
    $sql .= " AND (u.first_name LIKE :q1 OR u.last_name LIKE :q2 OR u.username LIKE :q3)";
}
$sql .= " GROUP BY u.id";

$sidebarStmt = $pdo->prepare($sql);
$sidebarStmt->bindValue(':uid1', $user_id);
$sidebarStmt->bindValue(':uid2', $user_id);
$sidebarStmt->bindValue(':uid3', $user_id);
$sidebarStmt->bindValue(':uid4', $user_id);
$sidebarStmt->bindValue(':uid5', $user_id);
if ($search_query) {
    $sidebarStmt->bindValue(':q1', "%$search_query%");
    $sidebarStmt->bindValue(':q2', "%$search_query%");
    $sidebarStmt->bindValue(':q3', "%$search_query%");
}
$sidebarStmt->execute();
$sidebar_users = $sidebarStmt->fetchAll();

$chat_user = null;
if ($chat_user_id) {
    $uStmt = $pdo->prepare("SELECT id, username, first_name, last_name, avatar FROM users WHERE id = ?");
    $uStmt->execute([$chat_user_id]);
    $chat_user = $uStmt->fetch();

    // Mark messages as read
    $pdo->prepare("UPDATE messages SET is_read = TRUE WHERE sender_id = ? AND receiver_id = ?")->execute([$chat_user_id, $user_id]);
}

// Handle sending a message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $chat_user_id) {
    $content = trim($_POST['content'] ?? '');

    $media_url = null;
    $media_type = 'none';

    if (isset($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['media']['tmp_name'];
        $file_type = mime_content_type($file_tmp);
        if (strpos($file_type, 'image') === 0) $media_type = 'image';
        elseif (strpos($file_type, 'video') === 0) $media_type = 'video';

        if ($media_type !== 'none') {
            $resource_type = ($media_type === 'video') ? 'video' : 'image';
            $uploaded = uploadToCloudinary($file_tmp, $resource_type);
            if ($uploaded) $media_url = $uploaded;
        }
    }

    if (!empty($content) || $media_url) {
        $mStmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, content, media_url, media_type) VALUES (?, ?, ?, ?, ?)");
        $mStmt->execute([$user_id, $chat_user_id, $content, $media_url, $media_type]);
    }

    // Check if it's an AJAX request
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo json_encode(['success' => true]);
        exit();
    }

    header("Location: messages.php?user=$chat_user_id");
    exit();
}

?>

<?php include 'includes/header.php'; ?>

<div class="row" style="height: 75vh;">
    <!-- Sidebar -->
    <div class="col-md-4 h-100 overflow-auto border-end border-secondary">
        <form class="mb-3" method="GET" action="messages.php">
            <?php if($chat_user_id): ?><input type="hidden" name="user" value="<?php echo $chat_user_id; ?>"><?php endif; ?>
            <div class="input-group">
                <input type="text" class="form-control bg-dark text-light border-secondary" name="q" placeholder="Search connections..." value="<?php echo htmlspecialchars($search_query); ?>">
                <button class="btn btn-secondary" type="submit"><i class="fa-solid fa-search"></i></button>
            </div>
        </form>

        <ul class="list-group list-group-flush rounded bg-dark">
            <?php foreach($sidebar_users as $su): ?>
                <a href="messages.php?user=<?php echo $su['id']; ?>" class="list-group-item list-group-item-action bg-dark text-light border-secondary <?php echo ($chat_user_id == $su['id']) ? 'active bg-primary border-primary' : ''; ?>">
                    <img src="<?php echo htmlspecialchars($su['avatar'] ?: '/assets/img/default-avatar.png'); ?>" class="rounded-circle me-2" width="40" height="40" style="object-fit:cover;">
                    <?php echo htmlspecialchars($su['first_name'] . ' ' . $su['last_name']); ?>
                </a>
            <?php endforeach; ?>
            <?php if(empty($sidebar_users)): ?>
                <li class="list-group-item bg-dark text-muted text-center border-secondary">No conversations yet.</li>
            <?php endif; ?>
        </ul>
    </div>

    <!-- Chat Area -->
    <div class="col-md-8 h-100 d-flex flex-column position-relative">
        <?php if ($chat_user): ?>
            <!-- Chat Header -->
            <div class="p-3 border-bottom border-secondary d-flex align-items-center bg-dark rounded-top">
                <img src="<?php echo htmlspecialchars($chat_user['avatar'] ?: '/assets/img/default-avatar.png'); ?>" class="rounded-circle me-2" width="40" height="40" style="object-fit:cover;">
                <h5 class="mb-0"><a href="profile.php?id=<?php echo $chat_user['id']; ?>" class="text-light text-decoration-none"><?php echo htmlspecialchars($chat_user['first_name'] . ' ' . $chat_user['last_name']); ?></a></h5>
            </div>

            <!-- Messages Container -->
            <div class="flex-grow-1 p-3 overflow-auto bg-dark" id="chatBox" data-user-id="<?php echo $chat_user['id']; ?>">
                <!-- Messages injected via JS -->
                <div class="text-center text-muted"><i class="fa-solid fa-spinner fa-spin"></i> Loading...</div>
            </div>

            <!-- Input Area -->
            <div class="p-3 border-top border-secondary bg-dark rounded-bottom">
                <form id="messageForm" action="messages.php?user=<?php echo $chat_user['id']; ?>" method="POST" enctype="multipart/form-data" class="d-flex align-items-center">
                    <label for="chatMedia" class="btn btn-secondary me-2 text-success" style="cursor:pointer;"><i class="fa-solid fa-image"></i></label>
                    <input type="file" id="chatMedia" name="media" accept="image/*,video/*" class="d-none">

                    <input type="text" class="form-control rounded-pill bg-secondary text-light border-0 me-2" name="content" id="chatInput" placeholder="Type a message..." autocomplete="off">
                    <button type="submit" class="btn btn-primary rounded-circle"><i class="fa-solid fa-paper-plane"></i></button>
                </form>
                <div id="chatMediaPreview" class="mt-2 text-success small" style="display:none;"><i class="fa-solid fa-paperclip"></i> Media attached</div>
            </div>
        <?php else: ?>
            <div class="d-flex justify-content-center align-items-center h-100 bg-dark rounded">
                <h4 class="text-muted"><i class="fa-regular fa-comments fa-2xl mb-3 d-block"></i> Select a conversation to start chatting</h4>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const chatMedia = document.getElementById('chatMedia');
    const chatMediaPreview = document.getElementById('chatMediaPreview');
    if(chatMedia) {
        chatMedia.addEventListener('change', function() {
            if(this.files && this.files.length > 0) {
                chatMediaPreview.style.display = 'block';
            } else {
                chatMediaPreview.style.display = 'none';
            }
        });
    }

    const chatBox = document.getElementById('chatBox');
    const messageForm = document.getElementById('messageForm');

    if (chatBox) {
        const chatUserId = chatBox.getAttribute('data-user-id');
        let lastMsgId = 0;

        function fetchMessages() {
            fetch(`fetch_messages.php?user=${chatUserId}&last_id=${lastMsgId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.messages && data.messages.length > 0) {
                        if (lastMsgId === 0) chatBox.innerHTML = ''; // Clear loading

                        data.messages.forEach(msg => {
                            const isMe = msg.sender_id == <?php echo $_SESSION['user_id']; ?>;
                            const alignClass = isMe ? 'justify-content-end' : 'justify-content-start';
                            const bgClass = isMe ? 'bg-primary text-dark' : 'bg-secondary text-light';

                            let mediaHtml = '';
                            if (msg.media_type === 'image') {
                                mediaHtml = `<img src="${msg.media_url}" class="img-fluid rounded mb-1" style="max-width: 200px;"><br>`;
                            } else if (msg.media_type === 'video') {
                                mediaHtml = `<video src="${msg.media_url}" controls class="img-fluid rounded mb-1" style="max-width: 200px;"></video><br>`;
                            }

                            const textHtml = msg.content ? `<span>${msg.content.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")}</span>` : '';

                            chatBox.innerHTML += `
                                <div class="d-flex ${alignClass} mb-3">
                                    <div class="p-2 rounded ${bgClass}" style="max-width: 75%;">
                                        ${mediaHtml}
                                        ${textHtml}
                                        <div class="small text-end mt-1 opacity-75" style="font-size: 0.7rem;">${new Date(msg.created_at).toLocaleTimeString()}</div>
                                    </div>
                                </div>
                            `;
                            lastMsgId = Math.max(lastMsgId, msg.id);
                        });
                        chatBox.scrollTop = chatBox.scrollHeight;
                    } else if (lastMsgId === 0) {
                        chatBox.innerHTML = '<div class="text-center text-muted mt-5">No messages yet. Say hi!</div>';
                    }
                });
        }

        fetchMessages();
        setInterval(fetchMessages, 3000); // Polling every 3s

        if(messageForm) {
            messageForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);

                fetch(this.action, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                }).then(res => res.json()).then(data => {
                    if(data.success) {
                        document.getElementById('chatInput').value = '';
                        chatMedia.value = '';
                        chatMediaPreview.style.display = 'none';
                        fetchMessages(); // fetch immediately
                    }
                });
            });
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?>