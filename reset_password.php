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
<!doctype html><html><head><meta charset="utf-8"><title>Reset Password | Socialize</title><link rel="stylesheet" href="assets/style.css"></head><body><div class="auth-layout"><aside class="hero"><div class="hero-content"><h2 class="brand">Socialize</h2><p class="tag">You’re verified. Create a strong new password to secure your account.</p></div></aside><main class="panel"><div class="container"><h1>Create new password</h1><?php if($m=flash('error')): ?><div class="alert error"><?=htmlspecialchars($m)?></div><?php endif; ?><form method="post"><input type="hidden" name="csrf_token" value="<?=csrf_token()?>"><label>New password</label><input type="password" name="password" required><label>Confirm password</label><input type="password" name="confirm_password" required><button>Reset password</button></form></div></main></div></body></html>
