<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Assemble News Feed based on Audience Rules:
// 1. User's own posts
// 2. Public posts
// 3. 'Connections Only' posts from accepted connections
// 4. Exclude 'Only Me' posts (Profile Only) from others
$stmt = $pdo->prepare("
    SELECT p.*, u.username, u.first_name, u.last_name, u.avatar
    FROM posts p
    JOIN users u ON p.user_id = u.id
    WHERE
        p.user_id = :uid
        OR p.audience = 'public'
        OR (p.audience = 'connections' AND EXISTS (
            SELECT id FROM connections c
            WHERE ((c.requester_id = :uid_conn1 AND c.receiver_id = p.user_id) OR (c.requester_id = p.user_id AND c.receiver_id = :uid_conn2))
            AND c.status = 'accepted'
        ))
    ORDER BY p.created_at DESC
    LIMIT 30
");
$stmt->bindValue(':uid', $user_id);
$stmt->bindValue(':uid_conn1', $user_id);
$stmt->bindValue(':uid_conn2', $user_id);
$stmt->execute();
$posts = $stmt->fetchAll();

// Get Connection Requests for the right sidebar
$reqStmt = $pdo->prepare("
    SELECT c.requester_id, u.first_name, u.last_name, u.avatar
    FROM connections c
    JOIN users u ON c.requester_id = u.id
    WHERE c.receiver_id = ? AND c.status = 'pending'
");
$reqStmt->execute([$user_id]);
$requests = $reqStmt->fetchAll();
?>

<?php include 'includes/header.php'; ?>

<div class="row">
    <!-- Left Sidebar: Quick Links -->
    <div class="col-md-3 d-none d-md-block">
        <div class="list-group list-group-flush rounded bg-dark border border-secondary shadow-sm">
            <a href="profile.php?id=<?php echo $user_id; ?>" class="list-group-item list-group-item-action bg-dark text-light border-secondary">
                <i class="fa-solid fa-user text-success me-2"></i> My Profile
            </a>
            <a href="messages.php" class="list-group-item list-group-item-action bg-dark text-light border-secondary">
                <i class="fa-solid fa-message text-success me-2"></i> Messages
            </a>
            <a href="search.php?q=" class="list-group-item list-group-item-action bg-dark text-light border-secondary">
                <i class="fa-solid fa-magnifying-glass text-success me-2"></i> Explore
            </a>
        </div>
    </div>

    <!-- Center Feed -->
    <div class="col-md-6">
        <?php include 'includes/post_form.php'; ?>

        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div id="newsFeed">
            <?php
            if(count($posts) > 0) {
                foreach($posts as $post) {
                    include 'includes/post_card.php';
                }
            } else {
                echo '<div class="text-center text-muted my-5"><i class="fa-solid fa-leaf fa-3x mb-3 text-success opacity-50"></i><p>Your feed is empty. Find some people to connect with!</p></div>';
            }
            ?>
        </div>
    </div>

    <!-- Right Sidebar: Connection Requests -->
    <div class="col-md-3 d-none d-lg-block">
        <div class="card bg-dark border-secondary shadow-sm">
            <div class="card-header border-secondary fw-bold text-success">
                Connection Requests
            </div>
            <div class="card-body p-2">
                <?php if(count($requests) > 0): ?>
                    <?php foreach($requests as $req): ?>
                        <div class="d-flex align-items-center mb-2 p-2 rounded" style="background-color: #3a3b3c;">
                            <a href="profile.php?id=<?php echo $req['requester_id']; ?>" class="text-decoration-none">
                                <img src="<?php echo htmlspecialchars($req['avatar'] ?: '/assets/img/default-avatar.png'); ?>" class="rounded-circle me-2" width="40" height="40" style="object-fit:cover;">
                            </a>
                            <div class="flex-grow-1">
                                <a href="profile.php?id=<?php echo $req['requester_id']; ?>" class="text-light text-decoration-none fw-bold small d-block mb-1">
                                    <?php echo htmlspecialchars($req['first_name'] . ' ' . $req['last_name']); ?>
                                </a>
                                <div class="d-flex">
                                    <form method="POST" action="connect.php" class="d-inline me-1">
                                        <input type="hidden" name="action" value="accept">
                                        <input type="hidden" name="user_id" value="<?php echo $req['requester_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-primary py-0"><i class="fa-solid fa-check"></i></button>
                                    </form>
                                    <form method="POST" action="connect.php" class="d-inline">
                                        <input type="hidden" name="action" value="decline">
                                        <input type="hidden" name="user_id" value="<?php echo $req['requester_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-secondary text-danger py-0"><i class="fa-solid fa-xmark"></i></button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted small text-center mb-0 mt-2">No new requests.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>