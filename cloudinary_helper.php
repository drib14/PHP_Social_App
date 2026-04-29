<?php
/**
 * Cloudinary cURL Helper
 * Uploads a file directly to Cloudinary using their REST API.
 */
function uploadToCloudinary($file_tmp_path, $resource_type = 'auto') {
    $cloud_name = 'dwquuisuj';
    $api_key = '655351295167741';
    $api_secret = 'F0UAKwbXYzDbcTbFr43iwL0D0qQ';

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
