<?php
// Optional: keep only if you actually use it
// require_once 'config.php';

$host = "127.0.0.1";
$user = "root";
$pass = ""; // set your MySQL password here if needed
$db   = "auth_app";
$port = 3307;

// Enable mysqli exceptions
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Connect to MySQL (no DB selected yet)
    $conn = new mysqli($host, $user, $pass, "", $port);
    $conn->set_charset("utf8mb4");

    // Create database if not exists
    $conn->query("CREATE DATABASE IF NOT EXISTS `$db`");
    $conn->select_db($db);

    // USERS
    $conn->query("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100),
            email VARCHAR(100) UNIQUE,
            password VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // POSTS
    $conn->query("
        CREATE TABLE IF NOT EXISTS posts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            content TEXT,
            audience ENUM('public', 'followers', 'only_me') DEFAULT 'public',
            shared_post_id INT NULL,
            media_data LONGBLOB NULL,
            media_type VARCHAR(100) NULL,
            media_name VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (shared_post_id) REFERENCES posts(id) ON DELETE SET NULL
        )
    ");

    // MESSAGES
    $conn->query("
        CREATE TABLE IF NOT EXISTS messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sender_id INT,
            receiver_id INT,
            message TEXT,
            media_data LONGBLOB NULL,
            media_type VARCHAR(100) NULL,
            media_name VARCHAR(255) NULL,
            is_read BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");

    // LIKES
    $conn->query("
        CREATE TABLE IF NOT EXISTS likes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            post_id INT,
            UNIQUE(user_id, post_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
        )
    ");

    // COMMENTS
    $conn->query("
        CREATE TABLE IF NOT EXISTS comments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            post_id INT,
            content TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
        )
    ");

    // FOLLOWS
    $conn->query("
        CREATE TABLE IF NOT EXISTS follows (
            id INT AUTO_INCREMENT PRIMARY KEY,
            follower_id INT,
            following_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(follower_id, following_id),
            FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (following_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");

    // NOTIFICATIONS
    $conn->query("
        CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            actor_id INT,
            type VARCHAR(50),
            reference_id INT,
            is_read BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");

    echo "Database and tables are ready!";

} catch (mysqli_sql_exception $e) {
    if (strpos($e->getMessage(), 'Access denied') !== false) {
        die("
            <h3>Database Connection Error</h3>
            <p>Access denied for MySQL user <code>root</code>.</p>
            <p>Check your password or update <code>\$pass</code> in this file.</p>
        ");
    }

    die("<h3>Database Error</h3><p>" . htmlspecialchars($e->getMessage()) . "</p>");
}