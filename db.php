<?php
require_once 'config.php';

$host = "localhost";
$user = "root";
$pass = ""; // Change this if your XAMPP/WAMP MySQL root user has a password!
$db   = "auth_app";

// Enable mysqli exceptions
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Attempt to connect without DB first to create it if it doesn't exist
    $conn = new mysqli($host, $user, $pass);

    // Create DB if not exists
    $conn->query("CREATE DATABASE IF NOT EXISTS $db");
    $conn->select_db($db);

    // Optional: set charset
    $conn->set_charset("utf8mb4");

    // Automatically create tables if they do not exist
    $tables = [
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100),
            email VARCHAR(100) UNIQUE,
            password VARCHAR(255),
            bio TEXT NULL,
            profile_pic LONGBLOB NULL,
            profile_pic_type VARCHAR(100) NULL,
            cover_photo LONGBLOB NULL,
            cover_photo_type VARCHAR(100) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS user_groups (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255),
            description TEXT NULL,
            cover_photo LONGBLOB NULL,
            cover_photo_type VARCHAR(100) NULL,
            creator_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS group_members (
            id INT AUTO_INCREMENT PRIMARY KEY,
            group_id INT,
            user_id INT,
            role ENUM('admin', 'member') DEFAULT 'member',
            joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(group_id, user_id),
            FOREIGN KEY (group_id) REFERENCES user_groups(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS pages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255),
            description TEXT NULL,
            profile_pic LONGBLOB NULL,
            profile_pic_type VARCHAR(100) NULL,
            cover_photo LONGBLOB NULL,
            cover_photo_type VARCHAR(100) NULL,
            creator_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS page_followers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            page_id INT,
            user_id INT,
            followed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(page_id, user_id),
            FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS posts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            group_id INT NULL,
            page_id INT NULL,
            content TEXT,
            audience ENUM('public', 'followers', 'only_me') DEFAULT 'public',
            shared_post_id INT NULL,
            media_data LONGBLOB NULL,
            media_type VARCHAR(100) NULL,
            media_name VARCHAR(255) NULL,
            is_edited BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (group_id) REFERENCES user_groups(id) ON DELETE CASCADE,
            FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE,
            FOREIGN KEY (shared_post_id) REFERENCES posts(id) ON DELETE SET NULL
        )",
        "CREATE TABLE IF NOT EXISTS chat_groups (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NULL,
            creator_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS chat_group_members (
            id INT AUTO_INCREMENT PRIMARY KEY,
            chat_group_id INT,
            user_id INT,
            joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(chat_group_id, user_id),
            FOREIGN KEY (chat_group_id) REFERENCES chat_groups(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sender_id INT,
            receiver_id INT NULL,
            chat_group_id INT NULL,
            message TEXT,
            media_data LONGBLOB NULL,
            media_type VARCHAR(100) NULL,
            media_name VARCHAR(255) NULL,
            is_read BOOLEAN DEFAULT FALSE,
            is_edited BOOLEAN DEFAULT FALSE,
            is_deleted BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (chat_group_id) REFERENCES chat_groups(id) ON DELETE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS likes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            post_id INT,
            UNIQUE(user_id, post_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS comments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            post_id INT,
            parent_id INT NULL,
            content TEXT,
            is_edited BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
            FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS follows (
            id INT AUTO_INCREMENT PRIMARY KEY,
            follower_id INT,
            following_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(follower_id, following_id),
            FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (following_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            actor_id INT,
            type VARCHAR(50),
            reference_id INT,
            is_read BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE CASCADE
        )"
    ];

    foreach ($tables as $sql) {
        $conn->query($sql);
    }

    // Alter existing tables if needed to keep existing databases backwards compatible
    $alter_queries = [
        // Posts
        "ALTER TABLE posts ADD COLUMN IF NOT EXISTS group_id INT NULL",
        "ALTER TABLE posts ADD COLUMN IF NOT EXISTS page_id INT NULL",
        "ALTER TABLE posts ADD COLUMN IF NOT EXISTS audience ENUM('public', 'followers', 'only_me') DEFAULT 'public'",
        "ALTER TABLE posts ADD COLUMN IF NOT EXISTS shared_post_id INT NULL",
        "ALTER TABLE posts ADD COLUMN IF NOT EXISTS media_url VARCHAR(500) NULL",
        "ALTER TABLE posts ADD COLUMN IF NOT EXISTS media_type VARCHAR(100) NULL",
        "ALTER TABLE posts ADD COLUMN IF NOT EXISTS media_name VARCHAR(255) NULL",
        "ALTER TABLE posts ADD COLUMN IF NOT EXISTS is_edited BOOLEAN DEFAULT FALSE",
        "ALTER TABLE posts ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
        "ALTER TABLE posts ADD CONSTRAINT fk_shared_post FOREIGN KEY (shared_post_id) REFERENCES posts(id) ON DELETE SET NULL",
        "ALTER TABLE posts ADD CONSTRAINT fk_group_post FOREIGN KEY (group_id) REFERENCES user_groups(id) ON DELETE CASCADE",
        "ALTER TABLE posts ADD CONSTRAINT fk_page_post FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE",

        // Users
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS bio TEXT NULL",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_pic VARCHAR(500) NULL",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_pic_type VARCHAR(100) NULL",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS cover_photo VARCHAR(500) NULL",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS cover_photo_type VARCHAR(100) NULL",

        // Comments
        "ALTER TABLE comments ADD COLUMN IF NOT EXISTS parent_id INT NULL",
        "ALTER TABLE comments ADD COLUMN IF NOT EXISTS is_edited BOOLEAN DEFAULT FALSE",
        "ALTER TABLE comments ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
        "ALTER TABLE comments ADD CONSTRAINT fk_comment_parent FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE",

        // Messages
        "ALTER TABLE messages MODIFY COLUMN receiver_id INT NULL",
        "ALTER TABLE messages ADD COLUMN IF NOT EXISTS chat_group_id INT NULL",
        "ALTER TABLE messages ADD COLUMN IF NOT EXISTS media_url VARCHAR(500) NULL",
        "ALTER TABLE messages ADD COLUMN IF NOT EXISTS is_edited BOOLEAN DEFAULT FALSE",
        "ALTER TABLE messages ADD COLUMN IF NOT EXISTS is_deleted BOOLEAN DEFAULT FALSE",
        "ALTER TABLE messages ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
        "ALTER TABLE messages ADD CONSTRAINT fk_message_chatgroup FOREIGN KEY (chat_group_id) REFERENCES chat_groups(id) ON DELETE CASCADE"
    ];

    // Drop LONGBLOB columns if they exist from previous schema
    $drop_blobs = [
        "ALTER TABLE posts DROP COLUMN media_data",
        "ALTER TABLE users DROP COLUMN profile_pic", // Need to change type, drop first
        "ALTER TABLE users DROP COLUMN cover_photo",
        "ALTER TABLE messages DROP COLUMN media_data"
    ];
    foreach ($drop_blobs as $sql) {
        try { $conn->query($sql); } catch (Exception $e) {}
    }

    foreach ($alter_queries as $sql) {
        try {
            $conn->query($sql);
        } catch (Exception $e) {
            // Ignore alter errors if constraints already exist
        }
    }
} catch (mysqli_sql_exception $e) {
    if (strpos($e->getMessage(), 'Access denied for user') !== false) {
        die("<h3>Database Error: Access Denied</h3><p>Your MySQL server is requiring a password, but <code>db.php</code> is configured to use a blank password.</p><p>Please open <code>db.php</code> and update the <code>\$pass</code> variable on line 6 to match your XAMPP MySQL root password.</p>");
    } else {
        die("<h3>Database Error</h3><p>" . htmlspecialchars($e->getMessage()) . "</p>");
    }
}