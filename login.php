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
        header('Location: dashboard.php');
        exit;
    }

    flash('error', 'Invalid credentials.');
    header('Location: login.php');
    exit;
}
?>
<!doctype html><html><head><meta charset="utf-8"><title>Login | Socialize</title><link rel="stylesheet" href="assets/style.css"></head><body><div class="auth-layout"><aside class="hero"><div class="hero-content"><h2 class="brand">Socialize</h2><p class="tag">Your Socialize account is one click away.</p></div></aside><main class="panel"><div class="container"><h1>Welcome back</h1><?php if($m=flash('error')): ?><div class="alert error"><?=htmlspecialchars($m)?></div><?php endif; ?><?php if($m=flash('success')): ?><div class="alert success"><?=htmlspecialchars($m)?></div><?php endif; ?><form method="post"><input type="hidden" name="csrf_token" value="<?=csrf_token()?>"><label>Email</label><input type="email" name="email" required><label>Password</label><input type="password" name="password" required><button>Login</button></form><div class="links"><a href="register.php">Create account</a><a href="forgot_password.php">Forgot password?</a></div></div></main></div></body></html>