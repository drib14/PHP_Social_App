<?php
class Cloudinary {
    public static function upload($filePath) {
        global $config;
        $cloudName = $config['cloudinary']['cloud_name'];
        $apiKey = $config['cloudinary']['api_key'];
        $apiSecret = $config['cloudinary']['api_secret'];

        $url = "https://api.cloudinary.com/v1_1/$cloudName/auto/upload";

        $timestamp = time();
        $signature = sha1("timestamp=$timestamp" . $apiSecret);

        if (class_exists('CURLFile')) {
            $cfile = new CURLFile($filePath);
        } else {
            $cfile = '@' . realpath($filePath);
        }

        $postFields = [
            'file' => $cfile,
            'api_key' => $apiKey,
            'timestamp' => $timestamp,
            'signature' => $signature
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        if (isset($data['secure_url'])) {
            return $data['secure_url'];
        }
        return null;
    }
}
