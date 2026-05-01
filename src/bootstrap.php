<?php
session_start();
$config = require __DIR__ . '/../config.php';

function db(): PDO { static $pdo=null; global $config; if(!$pdo){$dsn=sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s',$config['db']['host'],$config['db']['port'],$config['db']['name'],$config['db']['charset']);$pdo=new PDO($dsn,$config['db']['user'],$config['db']['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);} return $pdo; }
function csrf_token(): string { if(empty($_SESSION['csrf_token'])) $_SESSION['csrf_token']=bin2hex(random_bytes(32)); return $_SESSION['csrf_token']; }
function verify_csrf(): bool { return isset($_POST['csrf_token'],$_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'],$_POST['csrf_token']); }
function flash(string $key, ?string $message=null): ?string { if($message!==null){$_SESSION['flash'][$key]=$message; return null;} $m=$_SESSION['flash'][$key]??null; unset($_SESSION['flash'][$key]); return $m; }
function is_logged_in(): bool { return !empty($_SESSION['user_id']); }
function require_guest(): void { if(is_logged_in()){header('Location: feed.php'); exit;} }
function require_auth(): void { if(!is_logged_in()){header('Location: login.php'); exit;} }
function current_user(): ?array { if(!is_logged_in()) return null; $s=db()->prepare('SELECT * FROM users WHERE id=:id');$s->execute(['id'=>$_SESSION['user_id']]); return $s->fetch()?:null; }
function initials(string $name): string { $parts=preg_split('/\s+/', trim($name)); $a=strtoupper(substr($parts[0]??'U',0,1)); $b=strtoupper(substr($parts[1]??'',0,1)); return $a.$b; }
function react_options(): array { return [['key'=>'like','label'=>'👍 Like'],['key'=>'love','label'=>'❤️ Love'],['key'=>'laugh','label'=>'😂 Funny'],['key'=>'wow','label'=>'😮 Wow'],['key'=>'custom','label'=>'✨ Custom']]; }

function ensure_schema(): void {
    static $done=false; if($done) return; $done=true;
    try {
        $exists = db()->query("SHOW TABLES LIKE 'users'")->fetchColumn();
        if(!$exists){ $sql=file_get_contents(__DIR__.'/../schema.sql'); foreach(array_filter(array_map('trim', explode(';',$sql))) as $stmt){ if($stmt!=='') db()->exec($stmt); } }
    } catch (Throwable $e) { error_log('Schema init failed: '.$e->getMessage()); }
}
ensure_schema();
