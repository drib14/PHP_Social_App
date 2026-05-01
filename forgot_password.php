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
        db()->prepare('INSERT INTO password_resets (user_id, code, expires_at) VALUES (:uid, :code, :exp)')->execute(['uid'=>$user['id'],'code'=>$code,'exp'=>$exp]);
        Mailer::sendResetCode($email, $code);
    }

    flash('success', 'If that email exists, a 6-digit code was sent.');
    header('Location: verify_code.php?email=' . urlencode($email));
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Password | Socialize</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="auth-layout">
    <aside class="hero">
        <div class="hero-content">
            <h1 class="brand">Socialize</h1>
            <p class="tag">Reconnect with friends and communities. Secure account recovery in seconds.</p>
        </div>
    </aside>
    <main class="panel">
        <div class="container">
            <h2 class="mb-4 text-center text-white">Forgot Password</h2>
            <?php if ($m = flash('error')): ?><div class="alert alert-danger"><?= htmlspecialchars($m) ?></div><?php endif; ?>
            <?php if ($m = flash('success')): ?><div class="alert alert-success"><?= htmlspecialchars($m) ?></div><?php endif; ?>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" name="email" required autofocus>
                </div>

                <button type="submit" class="btn btn-primary w-100 mt-3">Send 6-digit code</button>
            </form>

            <div class="text-center mt-4">
                <a href="login.php" class="text-decoration-none">Back to login</a>
            </div>
        </div>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
