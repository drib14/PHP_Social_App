<?php
// config.php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'minisocial');

// Cloudinary Configuration
define('CLOUDINARY_CLOUD_NAME', getenv('CLOUDINARY_CLOUD_NAME'));
define('CLOUDINARY_API_KEY', getenv('CLOUDINARY_API_KEY'));
define('CLOUDINARY_API_SECRET', getenv('CLOUDINARY_API_SECRET'));
?>