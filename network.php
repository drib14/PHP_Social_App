<?php
// network.php
require_once 'includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$conn = getDbConnection();
$current_user_id = $_SESSION['user_id'];

// Get pending connection requests
$reqStmt = $conn->prepare("SELECT c.id, c.requester_id, u.username, u.avatar_url
                           FROM connections c
                           JOIN users u ON c.requester_id = u.id
                           WHERE c.receiver_id = ? AND c.status = 'pending'");
$reqStmt->bind_param("i", $current_user_id);
$reqStmt->execute();
$requests = $reqStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get suggested users (not connected, not self)
$sugStmt = $conn->prepare("SELECT id, username, avatar_url FROM users
                           WHERE id != ?
                           AND id NOT IN (SELECT receiver_id FROM connections WHERE requester_id = ?)
                           AND id NOT IN (SELECT requester_id FROM connections WHERE receiver_id = ?)
                           LIMIT 10");
$sugStmt->bind_param("iii", $current_user_id, $current_user_id, $current_user_id);
$sugStmt->execute();
$suggestions = $sugStmt->get_result()->fetch_all(MYSQLI_ASSOC);

function getAvatar($url, $username) {
    return $url ? htmlspecialchars($url) : 'https://ui-avatars.com/api/?name='.urlencode($username).'&background=10b981&color=fff';
}
?>

<div class="row">
    <div class="col-md-8">
        <h3 class="mb-4">Your Network</h3>

        <?php if (count($requests) > 0): ?>
            <h5 class="text-emerald mb-3">Pending Requests</h5>
            <div class="row mb-4">
                <?php foreach ($requests as $req): ?>
                    <div class="col-md-6 mb-3">
                        <div class="card p-3 d-flex flex-row align-items-center">
                            <img src="<?php echo getAvatar($req['avatar_url'], $req['username']); ?>" class="rounded-circle me-3" style="width: 60px; height: 60px; object-fit: cover;">
                            <div class="flex-grow-1">
                                <a href="profile.php?id=<?php echo $req['requester_id']; ?>" class="h6 mb-1 text-white text-decoration-none"><?php echo htmlspecialchars($req['username']); ?></a>
                                <div class="mt-2">
                                    <button class="btn btn-sm btn-emerald me-1" onclick="handleNetworkConnection(<?php echo $req['requester_id']; ?>, 'accept')">Accept</button>
                                    <button class="btn btn-sm btn-outline-secondary" onclick="handleNetworkConnection(<?php echo $req['requester_id']; ?>, 'reject')">Reject</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <h5 class="text-emerald mb-3">Suggested Connections</h5>
        <div class="row">
            <?php foreach ($suggestions as $sug): ?>
                <div class="col-md-6 col-lg-4 mb-3">
                    <div class="card text-center p-3">
                        <img src="<?php echo getAvatar($sug['avatar_url'], $sug['username']); ?>" class="rounded-circle mx-auto mb-2" style="width: 80px; height: 80px; object-fit: cover;">
                        <a href="profile.php?id=<?php echo $sug['id']; ?>" class="h6 text-white text-decoration-none"><?php echo htmlspecialchars($sug['username']); ?></a>
                        <button class="btn btn-sm btn-emerald mt-2" onclick="handleNetworkConnection(<?php echo $sug['id']; ?>, 'request')">Connect</button>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (count($suggestions) === 0): ?>
                <p class="text-secondary">No suggestions at the moment.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function handleNetworkConnection(userId, action) {
    $.post('api/connection.php', { user_id: userId, action: action }, function(response) {
        if(response.success) {
            location.reload();
        } else {
            alert(response.message || "An error occurred.");
        }
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>