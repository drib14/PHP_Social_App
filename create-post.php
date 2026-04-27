<?php
require_once 'db.php';
include 'partials/header.php';

if ($_POST) {
    $content = trim($_POST['content']);

    if ($content) {
        $stmt = $conn->prepare("INSERT INTO posts (user_id, content) VALUES (?, ?)");
        $stmt->bind_param("is", $_SESSION['user_id'], $content);
        $stmt->execute();

        header("Location: dashboard.php");
        exit;
    }
}
?>

<div class="card p-3">
    <form method="POST">
        <textarea name="content" class="form-control mb-2" placeholder="Share something..."></textarea>
        <button class="btn btn-primary w-100">Post</button>
    </form>
</div>

<?php include 'partials/footer.php'; ?>