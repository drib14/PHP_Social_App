<?php
/**
 * Cloudinary cURL Helper
 * Uploads a file directly to Cloudinary using their REST API.
 */
function uploadToCloudinary($file_tmp_path, $resource_type = 'auto') {
    // Read from environment variables to prevent exposing secrets in source code
    $cloud_name = getenv('CLOUDINARY_CLOUD_NAME') ?: 'YOUR_CLOUD_NAME';
    $api_key = getenv('CLOUDINARY_API_KEY') ?: 'YOUR_API_KEY';
    $api_secret = getenv('CLOUDINARY_API_SECRET') ?: 'YOUR_API_SECRET';

    // Fallback logic for local environment without env vars set natively
    if ($cloud_name === 'YOUR_CLOUD_NAME' && file_exists(__DIR__ . '/.env')) {
        $env = parse_ini_file(__DIR__ . '/.env');
        $cloud_name = $env['CLOUDINARY_CLOUD_NAME'] ?? '';
        $api_key = $env['CLOUDINARY_API_KEY'] ?? '';
        $api_secret = $env['CLOUDINARY_API_SECRET'] ?? '';
    }

    $url = "https://api.cloudinary.com/v1_1/{$cloud_name}/{$resource_type}/upload";

    $timestamp = time();
    // For unsigned uploads we could use a preset, but since we have the secret we can do a signed upload.
    $signature = sha1("timestamp={$timestamp}{$api_secret}");

    $cfile = new CURLFile($file_tmp_path);

    $post_data = array(
        'file' => $cfile,
        'api_key' => $api_key,
        'timestamp' => $timestamp,
        'signature' => $signature
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // In some local environments SSL verification fails
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 200) {
        $data = json_decode($response, true);
        return $data['secure_url'] ?? null;
    }

    // You can log $response here if it fails
    return null;
}
