<?php
$ch = curl_init('https://api.cloudinary.com');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);

if($response === false) {
    echo 'cURL Error: ' . curl_error($ch);
} else {
    echo 'Berhasil! Koneksi SSL Cloudinary sudah dikenali.';
}
curl_close($ch);