<?php
require_once __DIR__ . '/src/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    session_unset();
    session_destroy();
}
header('Location: login.php');
exit;
