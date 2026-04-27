<?php
require_once 'db.php';
include 'partials/header.php';

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT name, email FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
?>

<div class="card p-3 mb-3">
    <h4><?= htmlspecialchars($user['name']) ?></h4>
    <p><?= htmlspecialchars($user['email']) ?></p>

    <a href="edit-profile.php" class="btn btn-primary">Edit Profile</a>
</div>

<?php include 'partials/footer.php'; ?>