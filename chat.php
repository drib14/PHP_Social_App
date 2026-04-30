<?php
// chat.php
require_once 'includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$conn = getDbConnection();
$current_user_id = $_SESSION['user_id'];
$active_user_id = isset($_GET['user']) ? (int)$_GET['user'] : null;

// Fetch connected users to list in sidebar
$contacts_query = "
    SELECT u.id, u.username, u.avatar_url
    FROM users u
    JOIN connections c ON (c.requester_id = u.id OR c.receiver_id = u.id)
    WHERE u.id != ?
      AND (c.requester_id = ? OR c.receiver_id = ?)
      AND c.status = 'accepted'
";
$stmt = $conn->prepare($contacts_query);
$stmt->bind_param("iii", $current_user_id, $current_user_id, $current_user_id);
$stmt->execute();
$contacts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

function getAvatar($url, $username) {
    return $url ? htmlspecialchars($url) : 'https://ui-avatars.com/api/?name='.urlencode($username).'&background=10b981&color=fff';
}
?>

<div class="row" style="height: 75vh;">
    <!-- Contacts Sidebar -->
    <div class="col-md-4 h-100">
        <div class="card h-100">
            <div class="card-header bg-dark text-emerald fw-bold">Contacts</div>
            <div class="card-body overflow-auto p-0">
                <div class="list-group list-group-flush">
                    <?php foreach ($contacts as $contact): ?>
                        <a href="chat.php?user=<?php echo $contact['id']; ?>" class="list-group-item list-group-item-action bg-transparent text-white border-secondary <?php echo ($active_user_id === $contact['id']) ? 'bg-secondary' : ''; ?>">
                            <div class="d-flex align-items-center">
                                <img src="<?php echo getAvatar($contact['avatar_url'], $contact['username']); ?>" class="rounded-circle me-3" style="width: 40px; height: 40px; object-fit: cover;">
                                <?php echo htmlspecialchars($contact['username']); ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                    <?php if (count($contacts) === 0): ?>
                        <div class="p-3 text-secondary text-center">No connections yet. Add friends to start chatting!</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Chat Area -->
    <div class="col-md-8 h-100 mt-3 mt-md-0">
        <?php if ($active_user_id):
            // Get active user details
            $uStmt = $conn->prepare("SELECT username, avatar_url FROM users WHERE id = ?");
            $uStmt->bind_param("i", $active_user_id);
            $uStmt->execute();
            $active_user = $uStmt->get_result()->fetch_assoc();

            if (!$active_user) {
                echo "<div class='alert alert-danger'>User not found.</div>";
            } else {
        ?>
            <div class="card h-100 d-flex flex-column">
                <div class="card-header bg-dark border-secondary d-flex align-items-center">
                    <img src="<?php echo getAvatar($active_user['avatar_url'], $active_user['username']); ?>" class="rounded-circle me-2" style="width: 35px; height: 35px; object-fit: cover;">
                    <span class="fw-bold text-white"><?php echo htmlspecialchars($active_user['username']); ?></span>
                </div>

                <div class="card-body overflow-auto d-flex flex-column" id="chat-messages" style="flex-grow: 1;">
                    <!-- Messages loaded via AJAX -->
                    <div class="text-center text-secondary"><i class="fa-solid fa-spinner fa-spin"></i> Loading...</div>
                </div>

                <div class="card-footer bg-dark border-secondary">
                    <form id="chat-form" class="d-flex">
                        <input type="hidden" id="receiver_id" value="<?php echo $active_user_id; ?>">
                        <input type="text" id="chat-input" class="form-control me-2 bg-transparent text-white border-secondary" placeholder="Type a message..." autocomplete="off" required>
                        <button type="submit" class="btn btn-emerald"><i class="fa-solid fa-paper-plane"></i></button>
                    </form>
                </div>
            </div>
        <?php
            }
        else: ?>
            <div class="card h-100 d-flex justify-content-center align-items-center text-secondary">
                <div class="text-center">
                    <i class="fa-regular fa-comments fs-1 mb-3"></i>
                    <h4>Select a contact to start chatting</h4>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($active_user_id): ?>
<script>
$(document).ready(function() {
    let receiverId = $('#receiver_id').val();
    let chatBox = $('#chat-messages');
    let lastMessageId = 0;

    function fetchMessages() {
        $.ajax({
            url: 'api/get_messages.php',
            type: 'GET',
            data: { user_id: receiverId, last_id: lastMessageId },
            dataType: 'json',
            success: function(res) {
                if(res.success && res.messages.length > 0) {
                    if (lastMessageId === 0) chatBox.empty(); // clear loader on first load

                    let atBottom = (chatBox[0].scrollHeight - chatBox[0].scrollTop) <= (chatBox[0].clientHeight + 50);

                    res.messages.forEach(function(msg) {
                        let isMe = (msg.sender_id == <?php echo $current_user_id; ?>);
                        let alignClass = isMe ? 'text-end' : 'text-start';
                        let bgClass = isMe ? 'bg-emerald text-white' : 'bg-secondary text-white';

                        let msgHtml = `
                            <div class="mb-2 ${alignClass}">
                                <div class="d-inline-block px-3 py-2 rounded ${bgClass}" style="max-width: 75%;">
                                    ${escapeHtml(msg.content)}
                                </div>
                            </div>
                        `;
                        chatBox.append(msgHtml);
                        lastMessageId = msg.id;
                    });

                    if (atBottom || lastMessageId === 0) {
                        chatBox.scrollTop(chatBox[0].scrollHeight);
                    }
                } else if (lastMessageId === 0 && chatBox.children().length === 1 && chatBox.find('.fa-spinner').length > 0) {
                    chatBox.html('<div class="text-center text-secondary my-auto">No messages yet. Say hi!</div>');
                }
            },
            complete: function() {
                // Long polling: wait 1 second then fetch again
                setTimeout(fetchMessages, 2000);
            }
        });
    }

    // Initial fetch
    fetchMessages();

    $('#chat-form').on('submit', function(e) {
        e.preventDefault();
        let content = $('#chat-input').val().trim();
        if (content === '') return;

        $('#chat-input').val('');

        $.post('api/send_message.php', { receiver_id: receiverId, content: content }, function(res) {
            if(!res.success) {
                alert('Failed to send message.');
            }
            // The fetchMessages poller will grab it and update UI
        }, 'json');
    });

    function escapeHtml(unsafe) {
        return unsafe
             .replace(/&/g, "&amp;")
             .replace(/</g, "&lt;")
             .replace(/>/g, "&gt;")
             .replace(/"/g, "&quot;")
             .replace(/'/g, "&#039;");
    }
});
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>