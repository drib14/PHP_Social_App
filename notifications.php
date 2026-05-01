<?php
require_once __DIR__ . '/src/bootstrap.php';
require_auth();
header('Content-Type: application/json');
$u=current_user();
$s=db()->prepare('SELECT n.*,a.name actor_name FROM notifications n JOIN users a ON a.id=n.actor_id WHERE n.user_id=:u ORDER BY n.id DESC LIMIT 20');
$s->execute(['u'=>$u['id']]);
echo json_encode($s->fetchAll());
