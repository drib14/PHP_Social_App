<?php
require_once __DIR__ . '/src/bootstrap.php';
require_guest();

if (empty($_SESSION['reset_verified']) || empty($_SESSION['reset_user_id'])) {
    flash('error', 'Verify your code first.');
    header('Location: forgot_password.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { flash('error', 'Invalid CSRF token.'); header('Location: reset_password.php'); exit; }

    $newPassword = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (strlen($newPassword) < 8) { flash('error', 'Password must be at least 8 chars.'); header('Location: reset_password.php'); exit; }
    if ($newPassword !== $confirm) { flash('error', 'Passwords do not match.'); header('Location: reset_password.php'); exit; }

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    db()->prepare('UPDATE users SET password_hash=:hash WHERE id=:id')->execute(['hash'=>$hash,'id'=>$_SESSION['reset_user_id']]);
    db()->prepare('DELETE FROM password_resets WHERE user_id=:uid')->execute(['uid'=>$_SESSION['reset_user_id']]);

    unset($_SESSION['reset_user_id'], $_SESSION['reset_verified']);
    flash('success', 'Password reset successfully. Please log in.');
    header('Location: login.php'); exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password | Socialize</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="auth-layout">
    <aside class="hero">
        <div class="hero-content">
            <h1 class="brand">Socialize</h1>
            <p class="tag">You’re verified. Create a strong new password to secure your account.</p>
        </div>
    </aside>
    <main class="panel">
        <div class="container">
            <h2 class="mb-4 text-center text-white">Create new password</h2>
            <?php if ($m = flash('error')): ?><div class="alert alert-danger"><?= htmlspecialchars($m) ?></div><?php endif; ?>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                <div class="mb-3">
                    <label class="form-label">New password</label>
                    <input type="password" class="form-control" name="password" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Confirm password</label>
                    <input type="password" class="form-control" name="confirm_password" required>
                </div>

                <button type="submit" class="btn btn-primary w-100 mt-3">Reset password</button>
            </form>
        </div>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
