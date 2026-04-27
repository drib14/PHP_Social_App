<?php
require_once 'config.php';

// Unset all session variables
$_SESSION = [];

// Destroy session
session_destroy();

// Redirect
header("Location: login.php");
exit;