<?php
require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/src/Mailer.php';
require_guest();
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!verify_csrf()){flash('error','Invalid CSRF token.');header('Location: register.php');exit;}
 $name=trim($_POST['name']??'');$email=trim($_POST['email']??'');$password=$_POST['password']??'';
 if($name===''||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<8){flash('error','Invalid info.');header('Location: register.php');exit;}
 $s=db()->prepare('SELECT id FROM users WHERE email=:email');$s->execute(['email'=>$email]); if($s->fetch()){flash('error','Email already used.');header('Location: register.php');exit;}
 $h=password_hash($password,PASSWORD_DEFAULT); db()->prepare('INSERT INTO users(name,email,password_hash) VALUES(:n,:e,:p)')->execute(['n'=>$name,'e'=>$email,'p'=>$h]);
 Mailer::sendWelcome($email,$name); flash('success','Account created. Please log in.'); header('Location: login.php'); exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register | Socialize</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="auth-layout">
    <aside class="hero">
        <div class="hero-content">
            <h1 class="brand">Socialize</h1>
            <p class="tag">Join the community to share updates and connect with others.</p>
        </div>
    </aside>
    <main class="panel">
        <div class="container">
            <h2 class="text-white mb-4 text-center">Sign Up</h2>
            <?php if ($m = flash('error')): ?><div class="alert alert-danger"><?= htmlspecialchars($m) ?></div><?php endif; ?>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" class="form-control" name="name" required autofocus>
                </div>

                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" name="email" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">New Password</label>
                    <input type="password" class="form-control" name="password" required>
                </div>

                <button type="submit" class="btn btn-success w-100 mt-3" style="background-color: #059669; border: none;">Sign Up</button>
            </form>

            <div class="text-center mt-4">
                <a href="login.php" class="text-decoration-none">Already have an account?</a>
            </div>
        </div>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
