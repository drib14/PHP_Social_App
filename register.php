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
 Mailer::sendWelcome($email,$name); flash('success','Account created.'); header('Location: login.php'); exit;
}
?>
<!doctype html><html><head><meta charset='utf-8'><link rel='stylesheet' href='assets/style.css'><title>Register</title></head><body><div class='panel'><div class='container'><h1>Join Socialize</h1><?php if($m=flash('error')):?><div class='alert error'><?=htmlspecialchars($m)?></div><?php endif;?><form method='post'><input type='hidden' name='csrf_token' value='<?=csrf_token()?>'><label>Name</label><input name='name' required><label>Email</label><input type='email' name='email' required><label>Password</label><input type='password' name='password' required><button>Create account</button></form></div></div></body></html>
