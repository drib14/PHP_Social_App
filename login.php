<?php
require_once __DIR__ . '/src/bootstrap.php';
require_guest();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        flash('error', 'Invalid CSRF token.');
        header('Location: login.php');
        exit;
    }

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = db()->prepare('SELECT id, password_hash FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        flash('success', 'Logged in successfully.');
        header('Location: feed.php');
        exit;
    }

    flash('error', 'Invalid credentials.');
    header('Location: login.php');
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | Socialize</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="auth-layout">
    <aside class="hero">
        <div class="hero-content">
            <h1 class="brand">Socialize</h1>
            <p class="tag">Connect with friends and the world around you on Socialize.</p>
        </div>
    </aside>
    <main class="panel">
        <div class="container">
            <h2 class="mb-4 text-center text-white">Log In</h2>
            <?php if ($m = flash('error')): ?><div class="alert alert-danger"><?= htmlspecialchars($m) ?></div><?php endif; ?>
            <?php if ($m = flash('success')): ?><div class="alert alert-success"><?= htmlspecialchars($m) ?></div><?php endif; ?>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" name="email" required autofocus>
                </div>

                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" class="form-control" name="password" required>
                </div>

                <button type="submit" class="btn btn-primary w-100 mt-3">Log In</button>
            </form>

            <div class="text-center mt-4">
                <a href="forgot_password.php" class="text-decoration-none">Forgot Password?</a>
                <hr class="border-secondary my-4">
                <a href="register.php" class="btn btn-success w-100 fw-bold" style="background-color: #059669; border: none;">Create New Account</a>
            </div>
        </div>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>