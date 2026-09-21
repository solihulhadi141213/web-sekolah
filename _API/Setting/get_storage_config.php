<?php

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET, OPTIONS');
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method tidak diizinkan. Gunakan GET.']);
    exit;
}

$config = require __DIR__ . '/../../_Config/config.php';
require_once __DIR__ . '/../../_Helper/GlobalFunction.php';
$userData = validateJWT($config);

// Pilih hanya informasi publik secara eksplisit. Jangan kirim seluruh konfigurasi.
// Status menunjukkan konfigurasi lokal, bukan hasil pengujian koneksi provider.
$storageStatus = static function (string $prefix) use ($config): string {
    return ($config[$prefix . '_status'] ?? 'Inactive') === 'Active' ? 'Active' : 'Inactive';
};
$uploadConfig = static function (string $prefix, string $name) use ($config, $storageStatus): array {
    return [
        'name' => $name,
        'status' => $storageStatus($prefix),
        'upload_allowed_formats' => $config[$prefix . '_upload_allowed_formats'] ?? [],
        'upload_max_bytes' => $config[$prefix . '_upload_max_bytes'] ?? 0,
    ];
};

$storages = [
    'local_directory' => $uploadConfig('local_directory', 'Local Directory'),
    'external_link' => [
        'name' => 'External Link',
        'status' => $storageStatus('external_link'),
        'verify_url' => ($config['external_link_verify'] ?? false) === true,
    ],
    'cloudinary' => $uploadConfig('cloudinary', 'Cloudinary'),
    'imagekit' => $uploadConfig('imagekit', 'ImageKit'),
];

http_response_code(200);
echo json_encode([
    'status' => 'success',
    'message' => 'Data konfigurasi storage berhasil dimuat.',
    'total_records' => count($storages),
    'requested_by_app' => $userData['app_name'] ?? 'Unknown',
    'data' => $storages,
], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
