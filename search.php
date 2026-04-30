<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$query = trim($_GET['q'] ?? '');

$users = [];
$posts = [];

if ($query) {
    // Search Users
    $uStmt = $pdo->prepare("
        SELECT id, username, first_name, last_name, avatar
        FROM users
        WHERE (username LIKE :q1 OR first_name LIKE :q2 OR last_name LIKE :q3)
        AND id != :uid
    ");
    $uStmt->bindValue(':q1', "%$query%");
    $uStmt->bindValue(':q2', "%$query%");
    $uStmt->bindValue(':q3', "%$query%");
    $uStmt->bindValue(':uid', $user_id);
    $uStmt->execute();
    $users = $uStmt->fetchAll();

    // Search Posts (Taking audience into account)
    // - Public posts
    // - Connections Only posts if connected
    // - User's own posts
    // We don't show 'only_me' posts in search unless it's the owner
    $pStmt = $pdo->prepare("
        SELECT p.*, u.username, u.first_name, u.last_name, u.avatar
        FROM posts p
        JOIN users u ON p.user_id = u.id
        WHERE p.content LIKE :q AND (
            p.audience = 'public'
            OR p.user_id = :uid1
            OR (p.audience = 'connections' AND EXISTS (
                SELECT id FROM connections c
                WHERE ((c.requester_id = :uid2 AND c.receiver_id = p.user_id) OR (c.requester_id = p.user_id AND c.receiver_id = :uid3))
                AND c.status = 'accepted'
            ))
        )
        ORDER BY p.created_at DESC
        LIMIT 20
    ");
    $pStmt->bindValue(':q', "%$query%");
    $pStmt->bindValue(':uid1', $user_id);
    $pStmt->bindValue(':uid2', $user_id);
    $pStmt->bindValue(':uid3', $user_id);
    $pStmt->execute();
    $posts = $pStmt->fetchAll();
}

?>

<?php include 'includes/header.php'; ?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <h3 class="mb-4">Search Results for "<?php echo htmlspecialchars($query); ?>"</h3>

        <!-- Users Results -->
        <?php if (!empty($users)): ?>
            <h5 class="text-success border-bottom border-secondary pb-2 mb-3">People</h5>
            <div class="row mb-4">
                <?php foreach ($users as $u): ?>
                    <div class="col-md-6 mb-3">
                        <div class="card bg-dark border-secondary h-100">
                            <div class="card-body d-flex align-items-center">
                                <a href="profile.php?id=<?php echo $u['id']; ?>" class="text-decoration-none d-flex align-items-center">
                                    <img src="<?php echo htmlspecialchars($u['avatar'] ?: '/assets/img/default-avatar.png'); ?>" class="rounded-circle me-3" width="50" height="50" style="object-fit:cover;">
                                    <div>
                                        <h6 class="mb-0 text-light fw-bold"><?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?></h6>
                                        <small class="text-muted">@<?php echo htmlspecialchars($u['username']); ?></small>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-muted mb-4">No users found.</p>
        <?php endif; ?>

        <!-- Posts Results -->
        <?php if (!empty($posts)): ?>
            <h5 class="text-success border-bottom border-secondary pb-2 mb-3">Posts</h5>
            <?php foreach ($posts as $post) { include 'includes/post_card.php'; } ?>
        <?php else: ?>
            <p class="text-muted mb-4">No posts found.</p>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>