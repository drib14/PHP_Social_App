<?php require_once __DIR__.'/src/bootstrap.php'; header('Location: '.(is_logged_in()?'feed.php':'login.php')); exit;
