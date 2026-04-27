<?php
require_once 'db.php';
include 'partials/header.php';

$user_id = $_SESSION['user_id'];

if ($_POST) {
    $name = $_POST['name'];

    $stmt = $conn->prepare("UPDATE users SET name=? WHERE id=?");
    $stmt->bind_param("si", $name, $user_id);
    $stmt->execute();

    $_SESSION['user_name'] = $name;

    header("Location: profile.php");
    exit;
}

$stmt = $conn->prepare("SELECT name FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
?>

<div class="card p-3">
    <form method="POST">
        <input name="name" value="<?= htmlspecialchars($user['name']) ?>" class="form-control mb-2">
        <button class="btn btn-primary w-100">Save</button>
    </form>
</div>

<?php include 'partials/footer.php'; ?>