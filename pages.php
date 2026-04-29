<?php
require_once 'db.php';
include 'partials/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch pages you manage
$my_pages_stmt = $conn->prepare("SELECT * FROM pages WHERE creator_id = ?");
$my_pages_stmt->bind_param("i", $user_id);
$my_pages_stmt->execute();
$my_pages = $my_pages_stmt->get_result();

// Fetch pages you follow
$following_stmt = $conn->prepare("
    SELECT p.*
    FROM pages p
    JOIN page_followers pf ON p.id = pf.page_id
    WHERE pf.user_id = ? AND p.creator_id != ?
");
$following_stmt->bind_param("ii", $user_id, $user_id);
$following_stmt->execute();
$following_pages = $following_stmt->get_result();

// Fetch discover pages
$discover_stmt = $conn->prepare("
    SELECT p.*,
    (SELECT COUNT(*) FROM page_followers WHERE page_id = p.id) as follower_count
    FROM pages p
    WHERE p.creator_id != ? AND p.id NOT IN (SELECT page_id FROM page_followers WHERE user_id = ?)
    LIMIT 10
");
$discover_stmt->bind_param("ii", $user_id, $user_id);
$discover_stmt->execute();
$discover_pages = $discover_stmt->get_result();
?>

<div class="row">
    <!-- Left Sidebar -->
    <div class="col-md-3 d-none d-md-block">
        <h4 class="fw-bold mb-3">Pages</h4>
        <div class="list-group list-group-flush mb-4" style="border-radius: 12px; overflow: hidden;">
            <a href="pages.php" class="list-group-item list-group-item-action bg-dark text-white border-secondary fw-bold active" style="background-color: var(--bg-hover) !important; color: var(--accent-color) !important; border-left: 4px solid var(--accent-color);">
                <i class="fa-solid fa-compass rounded-circle bg-secondary p-2 me-2"></i> Discover
            </a>
            <a href="create_page.php" class="list-group-item list-group-item-action bg-dark text-white border-secondary">
                <i class="fa-solid fa-plus rounded-circle bg-secondary p-2 me-2 text-primary"></i> Create New Page
            </a>
        </div>

        <?php if ($my_pages->num_rows > 0): ?>
        <hr class="border-secondary mb-3">
        <h6 class="text-muted fw-bold mb-3 px-2">Pages You Manage</h6>
        <div class="list-group list-group-flush bg-transparent mb-4">
            <?php while ($p = $my_pages->fetch_assoc()): ?>
                <a href="view_page.php?id=<?= $p['id'] ?>" class="list-group-item list-group-item-action bg-transparent text-white border-0 py-2 px-2 d-flex align-items-center gap-3" style="border-radius: 8px;">
                    <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; overflow: hidden;">
                        <?php if ($p['profile_pic']): ?>
                            <img src="<?= htmlspecialchars($p['profile_pic']) ?>" class="w-100 h-100 object-fit-cover">
                        <?php else: ?>
                            <i class="fa-solid fa-flag text-muted"></i>
                        <?php endif; ?>
                    </div>
                    <div class="fw-bold" style="font-size: 0.9rem;"><?= htmlspecialchars($p['name']) ?></div>
                </a>
            <?php endwhile; ?>
        </div>
        <?php endif; ?>

        <?php if ($following_pages->num_rows > 0): ?>
        <hr class="border-secondary mb-3">
        <h6 class="text-muted fw-bold mb-3 px-2">Pages You Follow</h6>
        <div class="list-group list-group-flush bg-transparent">
            <?php while ($p = $following_pages->fetch_assoc()): ?>
                <a href="view_page.php?id=<?= $p['id'] ?>" class="list-group-item list-group-item-action bg-transparent text-white border-0 py-2 px-2 d-flex align-items-center gap-3" style="border-radius: 8px;">
                    <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; overflow: hidden;">
                        <?php if ($p['profile_pic']): ?>
                            <img src="<?= htmlspecialchars($p['profile_pic']) ?>" class="w-100 h-100 object-fit-cover">
                        <?php else: ?>
                            <i class="fa-solid fa-flag text-muted"></i>
                        <?php endif; ?>
                    </div>
                    <div class="fw-bold" style="font-size: 0.9rem;"><?= htmlspecialchars($p['name']) ?></div>
                </a>
            <?php endwhile; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Main Feed -->
    <div class="col-md-9 feed-container pt-0">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-bold mb-0">Discover Pages</h4>
            <a href="create_page.php" class="btn btn-outline-light d-md-none btn-sm"><i class="fa-solid fa-plus me-1"></i> Create Page</a>
        </div>

        <div class="row row-cols-1 row-cols-md-2 g-4">
            <?php if ($discover_pages->num_rows == 0): ?>
                <div class="col-12 text-center text-muted p-5">
                    <p>No new pages to discover right now.</p>
                </div>
            <?php endif; ?>

            <?php while ($dp = $discover_pages->fetch_assoc()): ?>
                <div class="col">
                    <div class="card h-100" style="overflow: hidden;">
                        <div style="height: 120px; background: linear-gradient(135deg, var(--bg-hover), #334155); <?= $dp['cover_photo'] ? 'background-image: url('.htmlspecialchars($dp['cover_photo']).'); background-size: cover;' : '' ?>" class="d-flex align-items-center justify-content-center">
                            <?php if (!$dp['cover_photo']): ?><i class="fa-solid fa-flag fs-1 text-muted opacity-50"></i><?php endif; ?>
                        </div>
                        <div class="card-body position-relative">
                            <div class="position-absolute rounded-circle border border-4 border-dark overflow-hidden bg-secondary d-flex align-items-center justify-content-center" style="width: 64px; height: 64px; top: -32px; left: 16px;">
                                <?php if ($dp['profile_pic']): ?>
                                    <img src="<?= htmlspecialchars($dp['profile_pic']) ?>" class="w-100 h-100 object-fit-cover">
                                <?php else: ?>
                                    <i class="fa-solid fa-flag text-muted"></i>
                                <?php endif; ?>
                            </div>

                            <div style="margin-top: 32px;">
                                <h5 class="card-title fw-bold text-truncate"><a href="view_page.php?id=<?= $dp['id'] ?>" class="text-white text-decoration-none hover-underline"><?= htmlspecialchars($dp['name']) ?></a></h5>
                                <p class="card-text text-muted small mb-3"><?= $dp['follower_count'] ?> followers</p>

                                <form action="page_action.php" method="POST">
                                    <input type="hidden" name="action" value="follow">
                                    <input type="hidden" name="page_id" value="<?= $dp['id'] ?>">
                                    <button type="submit" class="btn bg-hover w-100 text-white fw-bold py-2"><i class="fa-solid fa-plus me-2"></i>Follow Page</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</div>

<?php include 'partials/footer.php'; ?>