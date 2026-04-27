<?php
// Start session globally
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// App settings
define('BASE_URL', 'http://localhost/auth-app/');

// Error reporting (dev only)
error_reporting(E_ALL);
ini_set('display_errors', 1);