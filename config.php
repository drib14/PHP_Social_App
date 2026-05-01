<?php
return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'php_auth_app',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
    'mail' => [
        'host' => 'smtp.gmail.com',
        'port' => 465,
        'secure' => 'tls',
        'user' => 'jhondribramirez7@gmail.com',
        'pass' => 'pxvm fnfn zclm nuah',
        'from' => 'jhondribramirez7@gmail.com',
        'from_name' => 'PHP Auth App',
    ],
    'cloudinary' => [
        'cloud_name' => getenv('CLOUDINARY_CLOUD_NAME') ?: '',
        'api_key' => getenv('CLOUDINARY_API_KEY') ?: '',
        'api_secret' => getenv('CLOUDINARY_API_SECRET') ?: '',
    ],
    'app' => [
        'base_url' => 'http://localhost/PHP_Social_App',
    ],
];
