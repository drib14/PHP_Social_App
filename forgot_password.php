<?php
require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/src/Mailer.php';
require_guest();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { flash('error', 'Invalid CSRF token.'); header('Location: forgot_password.php'); exit; }
    $email = trim($_POST['email'] ?? '');

    $stmt = db()->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if ($user) {
        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $exp = (new DateTime('+15 minutes'))->format('Y-m-d H:i:s');

        db()->prepare('DELETE FROM password_resets WHERE user_id = :uid')->execute(['uid' => $user['id']]);
        db()->prepare('INSERT INTO password_resets (user_id, code, expires_at) VALUES (:uid, :code, :exp)')
            ->execute(['uid'=>$user['id'],'code'=>$code,'exp'=>$exp]);

        Mailer::sendResetCode($email, $code);
    }

    flash('success', 'If that email exists, a reset code was sent.');
    header('Location: reset_password.php');
    exit;
}
?>
<!doctype html><html><head><meta charset="utf-8"><title>Forgot Password</title><link rel="stylesheet" href="assets/style.css"></head><body><div class="container"><h1>Forgot password</h1><?php if($m=flash('error')): ?><div class="alert error"><?=htmlspecialchars($m)?></div><?php endif; ?><?php if($m=flash('success')): ?><div class="alert success"><?=htmlspecialchars($m)?></div><?php endif; ?><form method="post"><input type="hidden" name="csrf_token" value="<?=csrf_token()?>"><label>Email</label><input type="email" name="email" required><button>Send reset code</button></form><div class="links"><a href="login.php">Back to login</a></div></div></body></html>
