<?php
require_once 'config.php';

$host = "localhost";
$user = "root";
$pass = "";
$db   = "auth_app";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Optional: set charset
$conn->set_charset("utf8mb4");