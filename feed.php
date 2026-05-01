<?php
require_once __DIR__ . '/src/bootstrap.php';
require_auth();
$u = current_user();
if (!$u) { session_unset(); session_destroy(); header('Location: login.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) die('csrf');
    if (isset($_POST['new_post'])) db()->prepare('INSERT INTO posts(user_id,body) VALUES(:u,:b)')->execute(['u'=>$u['id'],'b'=>trim($_POST['body'])]);
    if (isset($_POST['share_post'])) { $sid=(int)$_POST['share_post_id']; db()->prepare('INSERT INTO posts(user_id,body,shared_post_id) VALUES(:u,:b,:s)')->execute(['u'=>$u['id'],'b'=>trim($_POST['share_body']??'Shared post'),'s'=>$sid]); }
    if (isset($_POST['comment'])) { $pid=(int)$_POST['post_id']; db()->prepare('INSERT INTO comments(post_id,user_id,body) VALUES(:p,:u,:b)')->execute(['p'=>$pid,'u'=>$u['id'],'b'=>trim($_POST['comment_body'])]); }
    if (isset($_POST['react'])) { $k=$_POST['reaction_key'];$label=$_POST['reaction_label']; if($k==='custom'){$label=trim($_POST['custom_reaction']);$k='custom';} $tt=$_POST['target_type'];$tid=(int)$_POST['target_id']; db()->prepare('INSERT INTO reactions(user_id,target_type,target_id,reaction_key,reaction_label) VALUES(:u,:t,:id,:k,:l) ON DUPLICATE KEY UPDATE reaction_key=VALUES(reaction_key),reaction_label=VALUES(reaction_label)')->execute(['u'=>$u['id'],'t'=>$tt,'id'=>$tid,'k'=>$k,'l'=>$label]); }
    header('Location: feed.php'); exit;
}
$posts = db()->query('SELECT p.*,u.name FROM posts p JOIN users u ON u.id=p.user_id ORDER BY p.id DESC LIMIT 40')->fetchAll();
?>
<!doctype html><html><head><meta charset='utf-8'><link rel='stylesheet' href='assets/style.css'><title>Socialize Feed</title></head><body class='feed-body'>
<header class='topbar'><div class='logo'>Socialize</div><div class='search'>🔎 Search Socialize</div><div class='nav-icons'><div class='icon-btn' title='Home'>🏠</div><div class='icon-btn' title='Friends'>👥</div><div class='icon-btn' title='Watch'>🎬</div><div class='icon-btn' title='Groups'>🧩</div></div><div class='user-pill'><?=initials($u['name'] ?? '')?></div><a class='logout-btn' href='logout.php' onclick="event.preventDefault();document.getElementById('logoutForm').submit();">Logout</a><form id='logoutForm' method='post' action='logout.php' style='display:none'><input type='hidden' name='csrf_token' value='<?=csrf_token()?>'></form></header>
<div class='feed-layout'>
<aside class='left-rail card'><h3><?=htmlspecialchars($u['name'])?></h3><p>@<?=strtolower(str_replace(' ','',$u['name']))?></p></aside>
<main class='center-feed'>
<div class='card composer'><form method='post'><input type='hidden' name='csrf_token' value='<?=csrf_token()?>'><textarea name='body' placeholder="What's on your mind, <?=htmlspecialchars($u['name'])?>?" required></textarea><button name='new_post'>Post</button></form></div>
<?php foreach($posts as $p): ?><article class='card post'><div class='post-head'><div class='avatar'><?=initials($p['name'])?></div><div><strong><?=htmlspecialchars($p['name'])?></strong></div></div><p><?=nl2br(htmlspecialchars($p['body']))?></p><div class='actions'><form method='post'><input type='hidden' name='csrf_token' value='<?=csrf_token()?>'><input type='hidden' name='target_type' value='post'><input type='hidden' name='target_id' value='<?=$p['id']?>'><select name='reaction_key'><?php foreach(react_options() as $r):?><option value='<?=$r['key']?>'><?=$r['label']?></option><?php endforeach;?></select><input name='reaction_label' placeholder='label'><input name='custom_reaction' placeholder='custom'><button name='react'>React</button></form><form method='post'><input type='hidden' name='csrf_token' value='<?=csrf_token()?>'><input type='hidden' name='post_id' value='<?=$p['id']?>'><input name='comment_body' placeholder='Write a comment' required><button name='comment'>Comment</button></form><form method='post'><input type='hidden' name='csrf_token' value='<?=csrf_token()?>'><input type='hidden' name='share_post_id' value='<?=$p['id']?>'><input name='share_body' placeholder='Add a message'><button name='share_post'>Share</button></form></div></article><?php endforeach; ?>
</main>
<aside class='right-rail card'><h3>Notifications</h3><ul id='notifList'></ul></aside>
</div>
<script>async function loadN(){const r=await fetch('notifications.php');const j=await r.json();document.getElementById('notifList').innerHTML=j.slice(0,8).map(n=>`<li>${n.message}</li>`).join('');}loadN();setInterval(loadN,5000);</script>
</body></html>
