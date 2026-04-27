<?php
require_once 'db.php';
include 'partials/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$query = $_GET['q'] ?? '';
$results = [];

if (!empty($query)) {
    $search_term = "%" . $query . "%";
    $stmt = $conn->prepare("SELECT id, name, email FROM users WHERE name LIKE ?");
    $stmt->bind_param("s", $search_term);
    $stmt->execute();
    $results = $stmt->get_result();
}
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <h3 class="mb-4"><i class="fa-solid fa-magnifying-glass me-2"></i> Search Users</h3>

        <form method="GET" action="search.php" class="mb-5 d-flex gap-2">
            <input type="text" name="q" class="form-control form-control-lg rounded-pill" placeholder="Search by name..." value="<?= htmlspecialchars($query) ?>" required>
            <button type="submit" class="btn btn-primary btn-lg rounded-pill px-4">Search</button>
        </form>

        <?php if (!empty($query)): ?>
            <h5 class="mb-3">Results for "<?= htmlspecialchars($query) ?>"</h5>
            <?php if ($results->num_rows > 0): ?>
                <?php while ($user = $results->fetch_assoc()): ?>
                    <div class="card p-3 mb-3 d-flex flex-row align-items-center">
                        <div class="avatar-placeholder me-3" style="width: 48px; height: 48px;">
                            <?= strtoupper(substr($user['name'], 0, 1)) ?>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0 fw-bold"><?= htmlspecialchars($user['name']) ?></h6>
                            <small class="text-muted"><?= htmlspecialchars($user['email']) ?></small>
                        </div>
                        <a href="user_profile.php?id=<?= $user['id'] ?>" class="btn btn-outline-primary rounded-pill btn-sm">View Profile</a>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="card p-4 text-center text-muted">
                    <p class="mb-0">No users found matching your search.</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include 'partials/footer.php'; ?>