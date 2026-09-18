<?php
// Jalankan migrasi sebelum memakai endpoint:
// ALTER TABLE teachers ADD COLUMN image_public_id VARCHAR(255) NULL AFTER image_url;
// Memerlukan PDO MySQL, Fileinfo, GD (JPEG/PNG/WebP), dan Cloudinary PHP SDK.

use Cloudinary\Cloudinary;

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Cache-Control: no-store');

$respond = static function ($code, array $body) {
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
};
$method = $_SERVER['REQUEST_METHOD'] ?? '';
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($method !== 'POST') {
    header('Allow: POST, OPTIONS');
    $respond(405, ['status' => 'error', 'message' => 'Gunakan method POST.']);
}

$config = require __DIR__ . '/../../_Config/config.php';
require_once __DIR__ . '/../../_Helper/GlobalFunction.php';
$userData = validateJWT($config);

$tempFile = null;
$cloudinary = null;
$publicId = null;
$uploaded = false;
$safeToDelete = true;
$pdo = null;
$stage = 'validation';
$statusCode = 500;
$response = ['status' => 'error', 'message' => 'Terjadi kesalahan pada server.'];

try {
    $contentType = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
    if ($contentType !== 'application/json') {
        throw new RuntimeException('Content-Type harus application/json.', 415);
    }

    // Batas konfigurasi tidak boleh melampaui 5 MiB sesuai kontrak endpoint.
    $maxBytes = min(5 * 1024 * 1024, (int) ($config['upload_max_bytes'] ?? 5242880));
    if ($maxBytes < 1) {
        throw new RuntimeException('Konfigurasi batas upload tidak valid.', 500);
    }
    // Base64 lebih besar daripada file biner. Batasi pembacaan body juga.
    $maxBody = 4 * (int) ceil($maxBytes / 3) + 65536;
    if ((float) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $maxBody) {
        throw new RuntimeException('Body request terlalu besar.', 413);
    }
    $raw = file_get_contents('php://input', false, null, 0, $maxBody + 1);
    if ($raw === false) {
        throw new RuntimeException('Body request gagal dibaca.', 400);
    }
    if (strlen($raw) > $maxBody) {
        throw new RuntimeException('Body request terlalu besar.', 413);
    }
    $input = json_decode($raw);
    unset($raw);
    if (json_last_error() !== JSON_ERROR_NONE || !is_object($input)) {
        throw new RuntimeException('Body harus berupa objek JSON yang valid.', 400);
    }

    $fields = [];
    foreach (['name', 'role', 'subject'] as $field) {
        if (!isset($input->$field) || !is_string($input->$field)
            || trim($input->$field) === '') {
            throw new RuntimeException($field . ' wajib berupa teks yang tidak kosong.', 400);
        }
        $value = trim($input->$field);
        // JSON telah memastikan UTF-8 valid; hitung karakter tanpa wajib mbstring.
        if (preg_match_all('/./us', $value) > 100) {
            throw new RuntimeException($field . ' maksimal 100 karakter.', 400);
        }
        $fields[$field] = $value;
    }
    if (property_exists($input, 'image_base64') && !is_string($input->image_base64)) {
        throw new RuntimeException('image_base64 harus berupa string; gunakan "" jika tanpa gambar.', 400);
    }
    $base64 = trim($input->image_base64 ?? '');
    unset($input);
    $imageUrl = '';

    if ($base64 !== '') {
        if (!extension_loaded('fileinfo') || !extension_loaded('gd')) {
            throw new RuntimeException('Server memerlukan ekstensi Fileinfo dan GD untuk upload gambar.', 500);
        }
        $declaredMime = null;
        if (stripos($base64, 'data:') === 0) {
            if (!preg_match('~\Adata:(image/(?:jpeg|png|webp));base64,~i', $base64, $match)) {
                throw new RuntimeException('Data URI harus berupa JPEG, PNG, atau WebP base64.', 400);
            }
            $declaredMime = strtolower($match[1]);
            $base64 = substr($base64, strlen($match[0]));
        }
        $base64 = preg_replace('/\s+/', '', $base64);
        if (strlen($base64) > 4 * (int) ceil($maxBytes / 3)) {
            throw new RuntimeException('Ukuran gambar melebihi batas upload (maksimal 5 MB).', 413);
        }
        // Terima base64 standar, dengan atau tanpa padding; bukan base64url.
        $binary = base64_decode($base64, true);
        if ($binary === false || $binary === ''
            || rtrim(base64_encode($binary), '=') !== rtrim($base64, '=')) {
            throw new RuntimeException('image_base64 tidak valid atau terpotong.', 400);
        }
        unset($base64);
        if (strlen($binary) > $maxBytes) {
            throw new RuntimeException('Ukuran gambar melebihi batas upload (maksimal 5 MB).', 413);
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($binary);
        $formats = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $allowed = $config['upload_allowed_formats'] ?? ['jpg', 'png', 'webp'];
        if (!isset($formats[$mime]) || !in_array($formats[$mime], $allowed, true)) {
            throw new RuntimeException('Format gambar tidak diizinkan. Gunakan JPG, PNG, atau WebP.', 415);
        }
        if ($declaredMime !== null && $declaredMime !== $mime) {
            throw new RuntimeException('MIME pada Data URI berbeda dengan isi gambar.', 400);
        }
        $info = @getimagesizefromstring($binary);
        if ($info === false || ($info['mime'] ?? '') !== $mime) {
            throw new RuntimeException('Isi file bukan gambar yang valid.', 400);
        }
        // Batasi kebutuhan memori gambar terkompresi: maksimal 8 megapiksel.
        if ($info[0] < 1 || $info[1] < 1 || $info[0] > 6000 || $info[1] > 6000
            || $info[0] * $info[1] > 8000000) {
            throw new RuntimeException('Dimensi gambar maksimal 6000 piksel per sisi dan total 8 megapiksel.', 400);
        }
        $encoder = ['jpg' => 'imagejpeg', 'png' => 'imagepng', 'webp' => 'imagewebp'][$formats[$mime]];
        if (!function_exists($encoder)) {
            throw new RuntimeException('GD server belum mendukung format gambar ini.', 500);
        }
        $image = @imagecreatefromstring($binary);
        unset($binary);
        if ($image === false) {
            throw new RuntimeException('Gambar rusak atau tidak dapat didekode oleh GD.', 400);
        }
        try {
            $tempFile = tempnam(sys_get_temp_dir(), 'teacher_');
            if ($tempFile === false) {
                $tempFile = null;
                throw new RuntimeException('File sementara gagal dibuat.', 500);
            }
            // Encode ulang piksel, bukan meneruskan file asli beserta metadata/lampirannya.
            if ($mime === 'image/jpeg') {
                $written = imagejpeg($image, $tempFile, 90);
            } elseif ($mime === 'image/png') {
                imagesavealpha($image, true);
                $written = imagepng($image, $tempFile, 6);
            } else {
                imagesavealpha($image, true);
                $written = imagewebp($image, $tempFile, 90);
            }
        } finally {
            imagedestroy($image);
        }
        clearstatcache(true, $tempFile);
        if (!$written || !filesize($tempFile)) {
            throw new RuntimeException('Gagal mengodekan ulang gambar.', 500);
        }
        if (filesize($tempFile) > $maxBytes) {
            throw new RuntimeException('Gambar setelah diproses melebihi batas ukuran. Perkecil resolusinya.', 413);
        }
    }

    $stage = 'database';
    $pdo = new PDO(
        'mysql:host=' . $config['db_host'] . ';dbname=' . $config['db_name'] . ';charset=utf8mb4',
        $config['db_user'], $config['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
    // Persiapkan sebelum upload: migrasi yang belum dijalankan langsung terdeteksi.
    $stmt = $pdo->prepare('INSERT INTO teachers
        (name, role, subject, image_url, image_public_id, sort_order)
        VALUES (:name, :role, :subject, :image_url, :image_public_id, :sort_order)');

    if ($tempFile !== null) {
        $stage = 'cloudinary';
        $cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => $config['cloudinary_cloud_name'],
                'api_key' => $config['cloudinary_api_key'],
                'api_secret' => $config['cloudinary_api_secret']
            ],
            'url' => ['secure' => true]
        ]);
        $publicId = 'web_sekolah/teachers/' . bin2hex(random_bytes(16));
        $result = $cloudinary->uploadApi()->upload($tempFile, [
            'public_id' => $publicId,
            'resource_type' => 'image',
            'type' => 'upload',
            'overwrite' => false,
            'allowed_formats' => [$formats[$mime]],
            'timeout' => 60
        ]);
        $uploaded = true;
        $publicId = (string) ($result['public_id'] ?? $publicId);
        $imageUrl = (string) ($result['secure_url'] ?? '');
        if (strpos($imageUrl, 'https://') !== 0 || strlen($imageUrl) > 255 || strlen($publicId) > 255) {
            throw new RuntimeException('Respons upload gambar tidak sesuai format penyimpanan.', 502);
        }
    }

    $stage = 'database';
    $pdo->beginTransaction();
    $safeToDelete = false;
    // Urutan tidak unik; permintaan serentak dapat memiliki sort_order sama.
    $sortOrder = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM teachers')->fetchColumn();
    $stmt->execute($fields + [
        'image_url' => $imageUrl, 'image_public_id' => $publicId, 'sort_order' => $sortOrder
    ]);
    $id = (int) $pdo->lastInsertId();
    $pdo->commit();

    $statusCode = 201;
    $response = [
        'status' => 'success', 'message' => 'Data guru berhasil ditambahkan.',
        'requested_by_app' => $userData['app_name'] ?? 'Unknown',
        'data' => ['id' => $id] + $fields + [
            'image_url' => $imageUrl, 'image_public_id' => $publicId, 'sort_order' => $sortOrder
        ]
    ];
} catch (Throwable $e) {
    if ($pdo !== null && $pdo->inTransaction()) {
        try {
            $safeToDelete = $pdo->rollBack();
        } catch (Throwable $rollbackError) {
            $safeToDelete = false;
        }
    }
    // Kompensasi ketika upload sukses tetapi insert gagal dan aman dibatalkan.
    // Jika hasil commit tidak pasti, pertahankan aset untuk rekonsiliasi manual.
    if ($uploaded && $safeToDelete && $cloudinary !== null) {
        try {
            $deleted = $cloudinary->uploadApi()->destroy($publicId, [
                'resource_type' => 'image', 'type' => 'upload', 'invalidate' => true, 'timeout' => 15
            ]);
            if (!in_array($deleted['result'] ?? '', ['ok', 'not found'], true)) {
                error_log('add_teachers: perlu rekonsiliasi aset ' . $publicId);
            }
        } catch (Throwable $cleanupError) {
            error_log('add_teachers: gagal membersihkan aset ' . $publicId);
        }
    } elseif ($publicId !== null) {
        // Timeout upload atau hasil transaksi tidak pasti: jangan hapus secara membabi buta.
        error_log('add_teachers: periksa status database dan aset ' . $publicId);
    }
    if (get_class($e) === RuntimeException::class && in_array($e->getCode(), [400, 413, 415, 500, 502], true)) {
        $statusCode = $e->getCode();
        $response = ['status' => 'error', 'message' => $e->getMessage()];
    } else {
        $statusCode = $stage === 'cloudinary' ? 502 : 500;
        $response = ['status' => 'error', 'message' => $stage === 'cloudinary'
            ? 'Upload gambar ke Cloudinary gagal. Periksa konfigurasi dan koneksi server.'
            : 'Gagal menyimpan data guru. Periksa konfigurasi dan struktur database.'];
        // Hindari mencatat payload, base64, kredensial, atau URL request bertanda tangan.
        error_log('add_teachers: tahap=' . $stage . '; exception=' . get_class($e));
    }
} finally {
    if ($tempFile !== null && is_file($tempFile)) {
        unlink($tempFile);
    }
}

$respond($statusCode, $response);