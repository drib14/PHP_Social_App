<?php
require_once __DIR__ . '/src/bootstrap.php';
require_guest();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { flash('error', 'Invalid CSRF token.'); header('Location: reset_password.php'); exit; }

    $email = trim($_POST['email'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $newPassword = $_POST['password'] ?? '';

    if (strlen($newPassword) < 8) { flash('error', 'Password must be at least 8 chars.'); header('Location: reset_password.php'); exit; }

    $stmt = db()->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user) { flash('error', 'Invalid reset request.'); header('Location: reset_password.php'); exit; }

    $stmt = db()->prepare('SELECT id, expires_at FROM password_resets WHERE user_id=:uid AND code=:code ORDER BY id DESC LIMIT 1');
    $stmt->execute(['uid'=>$user['id'], 'code'=>$code]);
    $reset = $stmt->fetch();

    if (!$reset || new DateTime($reset['expires_at']) < new DateTime()) {
        flash('error', 'Invalid or expired code.'); header('Location: reset_password.php'); exit;
    }

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    db()->prepare('UPDATE users SET password_hash=:hash WHERE id=:id')->execute(['hash'=>$hash,'id'=>$user['id']]);
    db()->prepare('DELETE FROM password_resets WHERE user_id=:uid')->execute(['uid'=>$user['id']]);

    flash('success', 'Password reset successfully. Please log in.');
    header('Location: login.php'); exit;
}
?>
<!doctype html><html><head><meta charset="utf-8"><title>Reset Password</title><link rel="stylesheet" href="assets/style.css"></head><body><div class="container"><h1>Reset password</h1><?php if($m=flash('error')): ?><div class="alert error"><?=htmlspecialchars($m)?></div><?php endif; ?><?php if($m=flash('success')): ?><div class="alert success"><?=htmlspecialchars($m)?></div><?php endif; ?><form method="post"><input type="hidden" name="csrf_token" value="<?=csrf_token()?>"><label>Email</label><input type="email" name="email" required><label>6-digit code</label><input name="code" maxlength="6" required><label>New password</label><input type="password" name="password" required><button>Reset password</button></form><div class="links"><a href="forgot_password.php">Need new code?</a></div></div></body></html>
