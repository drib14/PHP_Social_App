<?php
require_once __DIR__ . '/src/bootstrap.php';
require_guest();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { flash('error', 'Invalid CSRF token.'); header('Location: register.php'); exit; }
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
        flash('error', 'Enter valid information (password min 8 chars).');
        header('Location: register.php'); exit;
    }

    $stmt = db()->prepare('SELECT id FROM users WHERE email = :email');
    $stmt->execute(['email' => $email]);
    if ($stmt->fetch()) { flash('error', 'Email already used.'); header('Location: register.php'); exit; }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = db()->prepare('INSERT INTO users (name, email, password_hash) VALUES (:name, :email, :hash)');
    $stmt->execute(['name'=>$name,'email'=>$email,'hash'=>$hash]);

    flash('success', 'Account created. Please login.');
    header('Location: login.php'); exit;
}
?>
<!doctype html><html><head><meta charset="utf-8"><title>Register</title><link rel="stylesheet" href="assets/style.css"></head><body><div class="container"><h1>Create account</h1><?php if($m=flash('error')): ?><div class="alert error"><?=htmlspecialchars($m)?></div><?php endif; ?><form method="post"><input type="hidden" name="csrf_token" value="<?=csrf_token()?>"><label>Name</label><input name="name" required><label>Email</label><input type="email" name="email" required><label>Password</label><input type="password" name="password" required><button>Register</button></form><div class="links"><a href="login.php">Already have account?</a></div></div></body></html>
