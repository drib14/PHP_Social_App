<?php
// includes/cloudinary.php

function uploadToCloudinary($file_path, $resource_type = 'auto') {
    $cloud_name = CLOUDINARY_CLOUD_NAME;
    $api_key = CLOUDINARY_API_KEY;
    $api_secret = CLOUDINARY_API_SECRET;

    $timestamp = time();
    $signature_string = "timestamp=" . $timestamp . $api_secret;
    $signature = sha1($signature_string);

    $url = "https://api.cloudinary.com/v1_1/" . $cloud_name . "/" . $resource_type . "/upload";

    $cfile = new CURLFile($file_path);

    $post_fields = array(
        "file" => $cfile,
        "api_key" => $api_key,
        "timestamp" => $timestamp,
        "signature" => $signature
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpcode == 200) {
        $result = json_decode($response, true);
        return $result['secure_url'] ?? null;
    } else {
        error_log("Cloudinary Upload Error: " . $response);
        return null;
    }
}
?>