<?php
require_once 'db.php';
include 'partials/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch user's groups
$my_groups_stmt = $conn->prepare("
    SELECT g.*, m.role
    FROM user_groups g
    JOIN group_members m ON g.id = m.group_id
    WHERE m.user_id = ?
");
$my_groups_stmt->bind_param("i", $user_id);
$my_groups_stmt->execute();
$my_groups = $my_groups_stmt->get_result();

// Fetch suggested groups
$suggested_stmt = $conn->prepare("
    SELECT g.*,
    (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) as member_count
    FROM user_groups g
    WHERE g.id NOT IN (SELECT group_id FROM group_members WHERE user_id = ?)
    LIMIT 10
");
$suggested_stmt->bind_param("i", $user_id);
$suggested_stmt->execute();
$suggested_groups = $suggested_stmt->get_result();
?>

<div class="row">
    <!-- Left Sidebar -->
    <div class="col-md-3 d-none d-md-block">
        <h4 class="fw-bold mb-3">Groups</h4>
        <div class="list-group list-group-flush mb-4" style="border-radius: 12px; overflow: hidden;">
            <a href="groups.php" class="list-group-item list-group-item-action bg-dark text-white border-secondary fw-bold active" style="background-color: var(--bg-hover) !important; color: var(--accent-color) !important; border-left: 4px solid var(--accent-color);">
                <i class="fa-solid fa-compass rounded-circle bg-secondary p-2 me-2"></i> Discover
            </a>
            <a href="create_group.php" class="list-group-item list-group-item-action bg-dark text-white border-secondary">
                <i class="fa-solid fa-plus rounded-circle bg-secondary p-2 me-2 text-primary"></i> Create New Group
            </a>
        </div>

        <hr class="border-secondary mb-3">
        <h6 class="text-muted fw-bold mb-3 px-2">Groups You've Joined</h6>
        <div class="list-group list-group-flush bg-transparent">
            <?php while ($g = $my_groups->fetch_assoc()): ?>
                <a href="view_group.php?id=<?= $g['id'] ?>" class="list-group-item list-group-item-action bg-transparent text-white border-0 py-2 px-2 d-flex align-items-center gap-3" style="border-radius: 8px;">
                    <div class="rounded bg-secondary d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                        <i class="fa-solid fa-users text-muted"></i>
                    </div>
                    <div>
                        <div class="fw-bold" style="font-size: 0.9rem;"><?= htmlspecialchars($g['name']) ?></div>
                    </div>
                </a>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- Main Feed -->
    <div class="col-md-9 feed-container pt-0">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-bold mb-0">Suggested for you</h4>
            <a href="create_group.php" class="btn btn-outline-light d-md-none btn-sm"><i class="fa-solid fa-plus me-1"></i> Create Group</a>
        </div>

        <div class="row row-cols-1 row-cols-md-2 g-4">
            <?php if ($suggested_groups->num_rows == 0): ?>
                <div class="col-12 text-center text-muted p-5">
                    <p>No new groups to discover right now.</p>
                </div>
            <?php endif; ?>

            <?php while ($sg = $suggested_groups->fetch_assoc()): ?>
                <div class="col">
                    <div class="card h-100" style="overflow: hidden;">
                        <div style="height: 120px; background: linear-gradient(135deg, var(--bg-hover), #334155);" class="d-flex align-items-center justify-content-center">
                            <i class="fa-solid fa-users fs-1 text-muted opacity-50"></i>
                        </div>
                        <div class="card-body">
                            <h5 class="card-title fw-bold text-truncate"><a href="view_group.php?id=<?= $sg['id'] ?>" class="text-white text-decoration-none hover-underline"><?= htmlspecialchars($sg['name']) ?></a></h5>
                            <p class="card-text text-muted small mb-3"><?= $sg['member_count'] ?> members</p>

                            <form action="group_action.php" method="POST">
                                <input type="hidden" name="action" value="join">
                                <input type="hidden" name="group_id" value="<?= $sg['id'] ?>">
                                <button type="submit" class="btn bg-hover w-100 text-white fw-bold py-2"><i class="fa-solid fa-user-plus me-2"></i>Join Group</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</div>

<?php include 'partials/footer.php'; ?>