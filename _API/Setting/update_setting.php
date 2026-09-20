<?php

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    header('Allow: PUT, OPTIONS');
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method tidak diizinkan. Gunakan PUT.']);
    exit;
}

$config = require __DIR__ . '/../../_Config/config.php';
require_once __DIR__ . '/../../_Helper/GlobalFunction.php';
require_once __DIR__ . '/../../_Helper/Database.php';
$userData = validateJWT($config);

// Format body mengikuti objek data dari get_all_setting.php.
$input = json_decode(file_get_contents('php://input'));
if (json_last_error() !== JSON_ERROR_NONE || !is_object($input) || count(get_object_vars($input)) === 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Body request harus berupa objek JSON pengaturan yang tidak kosong.']);
    exit;
}

foreach ($input as $key => $setting) {
    if (!is_object($setting)
        || !property_exists($setting, 'value')
        || !property_exists($setting, 'description')
        || ($setting->value !== null && !is_string($setting->value))
        || ($setting->description !== null && !is_string($setting->description))) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Setiap pengaturan wajib memiliki value dan description berupa teks atau null.']);
        exit;
    }

    // TEXT dibatasi dalam byte, VARCHAR(255) dalam jumlah karakter UTF-8.
    if (($setting->value !== null && strlen($setting->value) > 65535)
        || ($setting->description !== null && preg_match_all('/./us', $setting->description) > 255)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Value maksimal 65535 byte dan description maksimal 255 karakter.']);
        exit;
    }
}

$pdo = null;
try {
    $pdo = Database::getConnection();
    $pdo->beginTransaction();

    // Kunci dalam urutan konsisten dan pastikan semua key ada sebelum memperbarui.
    $keys = array_keys(get_object_vars($input));
    sort($keys, SORT_STRING);
    $select = $pdo->prepare('SELECT setting_key, setting_value, description, updated_at FROM web_settings WHERE setting_key = :setting_key FOR UPDATE');
    foreach ($keys as $key) {
        $select->execute(['setting_key' => $key]);
        if (!$select->fetch(PDO::FETCH_ASSOC)) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => 'Pengaturan tidak ditemukan.',
                'setting_key' => $key,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // updated_at dari klien diabaikan; waktu pembaruan ditentukan database.
    $update = $pdo->prepare('UPDATE web_settings SET setting_value = :value, description = :description, updated_at = CURRENT_TIMESTAMP WHERE setting_key = :setting_key');
    $formattedSettings = [];
    foreach ($keys as $key) {
        $setting = $input->{$key};
        $update->execute([
            'value' => $setting->value,
            'description' => $setting->description,
            'setting_key' => $key,
        ]);
        $select->execute(['setting_key' => $key]);
        $row = $select->fetch(PDO::FETCH_ASSOC);
        $formattedSettings[$key] = [
            'value' => $row['setting_value'],
            'description' => $row['description'],
            'updated_at' => $row['updated_at'],
        ];
    }
    $pdo->commit();

    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'Data pengaturan berhasil diperbarui.',
        'total_records' => count($formattedSettings),
        'requested_by_app' => $userData['app_name'] ?? 'Unknown',
        'data' => $formattedSettings,
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (PDOException $e) {
    if ($pdo !== null && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan pada server database.']);
}
