<?php
// includes/db.php
require_once __DIR__ . '/../config.php';

function getDbConnection() {
    static $conn = null;

    if ($conn !== null) {
        return $conn;
    }

    // Connect without DB name first to create it if necessary
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Create database
    $conn->query("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $conn->select_db(DB_NAME);

    // Auto-update schema
    updateDatabaseSchema($conn);

    return $conn;
}

function updateDatabaseSchema($conn) {
    $tables = [
        'users' => [
            'id INT AUTO_INCREMENT PRIMARY KEY',
            'username VARCHAR(50) NOT NULL UNIQUE',
            'email VARCHAR(100) NOT NULL UNIQUE',
            'password VARCHAR(255) NOT NULL',
            'bio TEXT',
            'avatar_url VARCHAR(255)',
            'cover_url VARCHAR(255)',
            'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
        ],
        'posts' => [
            'id INT AUTO_INCREMENT PRIMARY KEY',
            'user_id INT NOT NULL',
            'content TEXT',
            'media_url VARCHAR(255)',
            'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
        ],
        'comments' => [
            'id INT AUTO_INCREMENT PRIMARY KEY',
            'post_id INT NOT NULL',
            'user_id INT NOT NULL',
            'content TEXT NOT NULL',
            'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
        ],
        'reactions' => [
            'id INT AUTO_INCREMENT PRIMARY KEY',
            'post_id INT NOT NULL',
            'user_id INT NOT NULL',
            'reaction_type VARCHAR(50) NOT NULL',
            'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'UNIQUE KEY unique_user_post_reaction (user_id, post_id)'
        ],
        'connections' => [
            'id INT AUTO_INCREMENT PRIMARY KEY',
            'requester_id INT NOT NULL',
            'receiver_id INT NOT NULL',
            'status ENUM("pending", "accepted", "rejected") DEFAULT "pending"',
            'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'UNIQUE KEY unique_connection (requester_id, receiver_id)'
        ],
        'messages' => [
            'id INT AUTO_INCREMENT PRIMARY KEY',
            'sender_id INT NOT NULL',
            'receiver_id INT NOT NULL',
            'content TEXT',
            'is_read TINYINT(1) DEFAULT 0',
            'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
        ],
        'notifications' => [
            'id INT AUTO_INCREMENT PRIMARY KEY',
            'user_id INT NOT NULL',
            'actor_id INT NOT NULL',
            'type VARCHAR(50) NOT NULL',
            'reference_id INT',
            'is_read TINYINT(1) DEFAULT 0',
            'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
        ]
    ];

    foreach ($tables as $tableName => $columns) {
        $checkTable = $conn->query("SHOW TABLES LIKE '$tableName'");
        if ($checkTable->num_rows == 0) {
            // Table does not exist, create it
            $columnsSql = implode(', ', $columns);
            $createSql = "CREATE TABLE $tableName ($columnsSql) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            if (!$conn->query($createSql)) {
                die("Error creating table $tableName: " . $conn->error);
            }
        } else {
            // Table exists, check columns
            $existingColsQuery = $conn->query("SHOW COLUMNS FROM $tableName");
            $existingCols = [];
            while ($row = $existingColsQuery->fetch_assoc()) {
                $existingCols[] = $row['Field'];
            }

            foreach ($columns as $colDef) {
                if (preg_match('/^([a-zA-Z0-9_]+)\s+/', $colDef, $matches)) {
                    $colName = $matches[1];
                    if (strtoupper($colName) !== 'UNIQUE' && strtoupper($colName) !== 'PRIMARY' && strtoupper($colName) !== 'KEY') {
                         if (!in_array($colName, $existingCols)) {
                             $alterSql = "ALTER TABLE $tableName ADD COLUMN $colDef";
                             $conn->query($alterSql);
                         }
                    }
                }
            }
        }
    }
}

// Initialize connection automatically
$conn = getDbConnection();
?>