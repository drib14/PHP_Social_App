<?php
// config.php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'minisocial');
define('DB_PASS', 'password');
define('DB_NAME', 'minisocial');

// Cloudinary Configuration
define('CLOUDINARY_CLOUD_NAME', getenv('CLOUDINARY_CLOUD_NAME') ?: 'dwquuisuj');
define('CLOUDINARY_API_KEY', getenv('CLOUDINARY_API_KEY') ?: '655351295167741');
define('CLOUDINARY_API_SECRET', getenv('CLOUDINARY_API_SECRET') ?: 'F0UAKwbXYzDbcTbFr43iwL0D0qQ');
?>