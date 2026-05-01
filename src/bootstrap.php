<?php
session_start();
$config = require __DIR__ . '/../config.php';

function db(): PDO { static $pdo=null; global $config; if(!$pdo){$dsn=sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s',$config['db']['host'],$config['db']['port'],$config['db']['name'],$config['db']['charset']);$pdo=new PDO($dsn,$config['db']['user'],$config['db']['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);} return $pdo; }
function csrf_token(): string { if(empty($_SESSION['csrf_token'])) $_SESSION['csrf_token']=bin2hex(random_bytes(32)); return $_SESSION['csrf_token']; }
function verify_csrf(): bool { return isset($_POST['csrf_token'],$_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'],$_POST['csrf_token']); }
function flash(string $key, ?string $message=null): ?string { if($message!==null){$_SESSION['flash'][$key]=$message; return null;} $m=$_SESSION['flash'][$key]??null; unset($_SESSION['flash'][$key]); return $m; }
function is_logged_in(): bool { return !empty($_SESSION['user_id']); }
function require_guest(): void { if(is_logged_in()){header('Location: feed.php'); exit;} }
function require_auth(): void { if(!is_logged_in()){header('Location: login.php'); exit;} if(!current_user()){session_unset(); session_destroy(); header('Location: login.php'); exit;} }
function current_user(): ?array { if(!is_logged_in()) return null; $s=db()->prepare('SELECT * FROM users WHERE id=:id');$s->execute(['id'=>$_SESSION['user_id']]); return $s->fetch()?:null; }
function initials(?string $name): string { $name = trim((string)$name); if($name==='') return 'U'; $parts=preg_split('/\s+/', $name); $a=strtoupper(substr($parts[0]??'U',0,1)); $b=strtoupper(substr($parts[1]??'',0,1)); return $a.$b; }
function react_options(): array { return [['key'=>'like','label'=>'<i class="fa-solid fa-thumbs-up text-primary"></i> Like'],['key'=>'love','label'=>'<i class="fa-solid fa-heart text-danger"></i> Love'],['key'=>'laugh','label'=>'<i class="fa-solid fa-face-laugh-squint text-warning"></i> Haha'],['key'=>'wow','label'=>'<i class="fa-solid fa-face-surprise text-warning"></i> Wow'],['key'=>'custom','label'=>'<i class="fa-solid fa-star text-info"></i> Custom']]; }

function add_notification($targetUserId, $actorId, $type, $refType, $refId, $message) {
    if ($targetUserId == $actorId) return; // Don't notify self
    $stmt = db()->prepare('INSERT INTO notifications(user_id, actor_id, type, ref_type, ref_id, message) VALUES(:u, :a, :t, :rt, :rid, :m)');
    $stmt->execute(['u' => $targetUserId, 'a' => $actorId, 't' => $type, 'rt' => $refType, 'rid' => $refId, 'm' => $message]);
}

function get_post_owner($postId) {
    $stmt = db()->prepare('SELECT user_id FROM posts WHERE id = :id');
    $stmt->execute(['id' => $postId]);
    return $stmt->fetchColumn();
}

function get_comment_owner($commentId) {
    $stmt = db()->prepare('SELECT user_id FROM comments WHERE id = :id');
    $stmt->execute(['id' => $commentId]);
    return $stmt->fetchColumn();
}

function get_reply_owner($replyId) {
    $stmt = db()->prepare('SELECT user_id FROM replies WHERE id = :id');
    $stmt->execute(['id' => $replyId]);
    return $stmt->fetchColumn();
}

function ensure_schema(): void {
    static $done=false; if($done) return; $done=true;
    try {
        $exists = db()->query("SHOW TABLES LIKE 'users'")->fetchColumn();
        if(!$exists){ $sql=file_get_contents(__DIR__.'/../schema.sql'); foreach(array_filter(array_map('trim', explode(';',$sql))) as $stmt){ if($stmt!=='') db()->exec($stmt); } }
        $cols = db()->query("SHOW COLUMNS FROM posts LIKE 'media_url'")->fetchAll();
        if(empty($cols)){ db()->exec("ALTER TABLE posts ADD COLUMN media_url VARCHAR(255) NULL AFTER body"); }
    } catch (Throwable $e) { error_log('Schema init failed: '.$e->getMessage()); }
}
ensure_schema();
