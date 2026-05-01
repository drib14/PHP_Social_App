<?php
require_once __DIR__ . '/src/bootstrap.php';
require_auth();
$user = current_user();
?>
<!doctype html><html><head><meta charset="utf-8"><title>Dashboard</title><link rel="stylesheet" href="assets/style.css"></head><body><div class="container"><h1>Hello, <?=htmlspecialchars($user['name'])?></h1><?php if($m=flash('success')): ?><div class="alert success"><?=htmlspecialchars($m)?></div><?php endif; ?><p>You are logged in as <?=htmlspecialchars($user['email'])?>.</p><form method="post" action="logout.php"><input type="hidden" name="csrf_token" value="<?=csrf_token()?>"><button>Logout</button></form></div></body></html>
