<?php
require_once 'db.php';
include 'partials/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $user_id = $_SESSION['user_id'];

    if (empty($name)) {
        $error = "Page name is required.";
    } else {
        $stmt = $conn->prepare("INSERT INTO pages (name, description, creator_id) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $name, $description, $user_id);
        if ($stmt->execute()) {
            $page_id = $conn->insert_id;

            // Auto follow the page you created
            $admin_stmt = $conn->prepare("INSERT INTO page_followers (page_id, user_id) VALUES (?, ?)");
            $admin_stmt->bind_param("ii", $page_id, $user_id);
            $admin_stmt->execute();

            header("Location: view_page.php?id=" . $page_id);
            exit;
        } else {
            $error = "Failed to create page.";
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card p-4">
            <h3 class="fw-bold mb-4">Create a Page</h3>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3">
                    <label class="form-label text-muted small fw-bold">Page Name</label>
                    <input type="text" name="name" class="form-control bg-dark border-secondary text-white" required autofocus>
                </div>

                <div class="mb-4">
                    <label class="form-label text-muted small fw-bold">Description (Optional)</label>
                    <textarea name="description" class="form-control bg-dark border-secondary text-white" rows="3"></textarea>
                </div>

                <div class="d-flex gap-2">
                    <a href="pages.php" class="btn btn-secondary flex-fill py-2">Cancel</a>
                    <button type="submit" class="btn btn-primary flex-fill py-2">Create Page</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'partials/footer.php'; ?>