<?php
require_once __DIR__ . '/src/bootstrap.php';
require_guest();

$email = trim($_GET['email'] ?? $_POST['email'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { flash('error', 'Invalid CSRF token.'); header('Location: verify_code.php?email=' . urlencode($email)); exit; }
    $digits = [];
    for ($i = 1; $i <= 6; $i++) { $digits[] = preg_replace('/\D/', '', $_POST['d'.$i] ?? ''); }
    $code = implode('', $digits);

    $stmt = db()->prepare('SELECT id FROM users WHERE email=:email LIMIT 1');
    $stmt->execute(['email'=>$email]);
    $user = $stmt->fetch();

    if (!$user || strlen($code) !== 6) { flash('error', 'Invalid verification data.'); header('Location: verify_code.php?email=' . urlencode($email)); exit; }

    $stmt = db()->prepare('SELECT id, expires_at FROM password_resets WHERE user_id=:uid AND code=:code ORDER BY id DESC LIMIT 1');
    $stmt->execute(['uid'=>$user['id'],'code'=>$code]);
    $reset = $stmt->fetch();

    if (!$reset || new DateTime($reset['expires_at']) < new DateTime()) {
        flash('error', 'Code is invalid or expired.');
        header('Location: verify_code.php?email=' . urlencode($email));
        exit;
    }

    $_SESSION['reset_user_id'] = $user['id'];
    $_SESSION['reset_verified'] = true;
    flash('success', 'Code verified. Set your new password.');
    header('Location: reset_password.php');
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify Code | Socialize</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .code-grid { display: flex; gap: 0.5rem; justify-content: center; }
        .code-grid input { width: 3rem; height: 3.5rem; text-align: center; font-size: 1.5rem; font-weight: bold; }
    </style>
</head>
<body>
<div class="auth-layout">
    <aside class="hero">
        <div class="hero-content">
            <h1 class="brand">Socialize</h1>
            <p class="tag">Enter the 6-digit code sent to your email to verify it’s really you.</p>
        </div>
    </aside>
    <main class="panel">
        <div class="container">
            <h2 class="mb-4 text-center text-white">Verify Code</h2>
            <?php if ($m = flash('error')): ?><div class="alert alert-danger"><?= htmlspecialchars($m) ?></div><?php endif; ?>
            <?php if ($m = flash('success')): ?><div class="alert alert-success"><?= htmlspecialchars($m) ?></div><?php endif; ?>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">

                <div class="mb-4">
                    <label class="form-label text-center w-100">6-digit code</label>
                    <div class="code-grid">
                        <?php for($i=1;$i<=6;$i++): ?>
                            <input class="form-control" name="d<?=$i?>" maxlength="1" inputmode="numeric" pattern="[0-9]" required>
                        <?php endfor; ?>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100">Verify code</button>
            </form>

            <div class="text-center mt-4">
                <a href="forgot_password.php" class="text-decoration-none">Resend code</a>
            </div>
        </div>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const boxes = Array.from(document.querySelectorAll('.code-grid input'));
boxes.forEach((box, idx) => {
  box.addEventListener('input', (e) => {
    const val = e.target.value.replace(/\D/g, '');
    e.target.value = val.slice(0,1);
    if (val && idx < boxes.length - 1) boxes[idx + 1].focus();
  });
  box.addEventListener('keydown', (e) => {
    if (e.key === 'Backspace' && !e.target.value && idx > 0) boxes[idx - 1].focus();
    if (e.key === 'ArrowLeft' && idx > 0) boxes[idx - 1].focus();
    if (e.key === 'ArrowRight' && idx < boxes.length - 1) boxes[idx + 1].focus();
  });
  box.addEventListener('paste', (e) => {
    const data = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0,6);
    if (!data) return;
    e.preventDefault();
    data.split('').forEach((ch, i) => { if (boxes[i]) boxes[i].value = ch; });
    const targetIndex = Math.min(data.length, boxes.length - 1);
    boxes[targetIndex].focus();
  });
});
if (boxes[0]) boxes[0].focus();
</script>
</body>
</html>
