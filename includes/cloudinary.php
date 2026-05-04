<?php
// includes/cloudinary.php
require_once __DIR__ . '/../config.php';

function uploadToCloudinary($tmpFilePath) {
    if (!file_exists($tmpFilePath)) {
        return false;
    }

    $url = "https://api.cloudinary.com/v1_1/" . CLOUDINARY_CLOUD_NAME . "/image/upload";

    // Generate signature
    $timestamp = time();
    $paramsToSign = "timestamp=" . $timestamp;
    $signature = sha1($paramsToSign . CLOUDINARY_API_SECRET);

    $cFile = new CURLFile($tmpFilePath);

    $postFields = [
        'file' => $cFile,
        'api_key' => CLOUDINARY_API_KEY,
        'timestamp' => $timestamp,
        'signature' => $signature
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // Ignore SSL issues on local dev
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        error_log("cURL Error #: " . $err);
        return false;
    }

    $resDecoded = json_decode($response, true);
    if (isset($resDecoded['secure_url'])) {
        return $resDecoded['secure_url'];
    }

    error_log("Cloudinary Upload Error: " . $response);
    return false;
}
?>