<?php

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Cache-Control: no-store');

$respond = static function (int $code, array $body): void {
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
require_once __DIR__ . '/../../_Helper/Database.php';
require_once __DIR__ . '/../../_Helper/GalleryImageStorage.php';
$userData = validateJWT($config);

$pdo = null;
$storage = new GalleryImageStorage($config);
$safeToDelete = true;
$statusCode = 500;
$response = ['status' => 'error', 'message' => 'Gagal menyimpan data galeri.'];

try {
    if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') {
        throw new RuntimeException('Content-Type harus application/json.', 415);
    }
    // Batasi body sesuai batas storage, ditambah ruang untuk field JSON.
    // Sertakan storage nonaktif agar payload normal tetap mendapat pesan status nonaktif.
    $maxBytes = 0;
    foreach (['local_directory', 'cloudinary', 'imagekit'] as $prefix) {
        $maxBytes = max($maxBytes, (int) ($config[$prefix . '_upload_max_bytes'] ?? 0));
    }
    $maxBody = 4 * (int) ceil($maxBytes / 3) + 65536;
    if ((float) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $maxBody) {
        throw new RuntimeException('Body request terlalu besar.', 413);
    }
    $raw = file_get_contents('php://input', false, null, 0, $maxBody + 1);
    if ($raw === false || strlen($raw) > $maxBody) {
        throw new RuntimeException('Body request tidak dapat dibaca atau terlalu besar.', 413);
    }
    $input = json_decode($raw);
    unset($raw);
    if (json_last_error() !== JSON_ERROR_NONE || !is_object($input)) {
        throw new RuntimeException('Body harus berupa objek JSON yang valid.', 400);
    }
    $fields = [];
    foreach (['caption' => 255] as $field => $maxLength) {
        if (!isset($input->$field) || !is_string($input->$field) || trim($input->$field) === '') {
            throw new RuntimeException($field . ' wajib berupa teks yang tidak kosong.', 400);
        }
        $value = trim($input->$field);
        if (preg_match_all('/./us', $value) > $maxLength) {
            throw new RuntimeException($field . ' maksimal ' . $maxLength . ' karakter.', 400);
        }
        $fields[$field] = $value;
    }
    if (!isset($input->file_source) || !is_string($input->file_source)
        || !in_array($input->file_source, ['Local Directory', 'External Link', 'Cloudinary', 'Imagekit'], true)) {
        throw new RuntimeException('Gambar wajib disertakan. File_source harus Local Directory, External Link, Cloudinary, atau Imagekit.', 400);
    }
    $source = $input->file_source;
    $imageField = $source === 'External Link' ? 'image_url' : 'base64';
    if (!isset($input->$imageField) || !is_string($input->$imageField) || trim($input->$imageField) === '') {
        throw new RuntimeException('Gambar wajib disertakan. ' . $imageField . ' harus berupa teks yang tidak kosong.', 400);
    }

    // Persiapkan query sebelum mengunggah untuk mendeteksi struktur DB yang tidak sesuai.
    $pdo = Database::getConnection();
    $galleryInsert = $pdo->prepare('INSERT INTO galleries (caption, id_file_manager, sort_order)
        VALUES (:caption, :id_file_manager, :sort_order)');
    $fileInsert = $pdo->prepare('INSERT INTO file_manager (file_source, file_metadata, creat_at)
        VALUES (:file_source, :file_metadata, UTC_TIMESTAMP())');
    $metadata = $storage->store($source, $input);
    unset($input);

    // Upload dilakukan sebelum transaksi agar tidak menahan kunci DB selama akses jaringan.
    $pdo->beginTransaction();
    $safeToDelete = false;
    $fileInsert->execute([
        'file_source' => $source,
        'file_metadata' => json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
    ]);
    $fileId = (int) $pdo->lastInsertId();
    $sortOrder = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM galleries')->fetchColumn();
    $galleryInsert->execute($fields + ['id_file_manager' => $fileId, 'sort_order' => $sortOrder]);
    $id = (int) $pdo->lastInsertId();
    $pdo->commit();

    $statusCode = 201;
    $response = [
        'status' => 'success', 'message' => 'Data galeri berhasil ditambahkan.',
        'requested_by_app' => $userData['app_name'] ?? 'Unknown',
        'data' => ['id' => $id] + $fields + [
            'id_file_manager' => $fileId, 'sort_order' => $sortOrder,
            'file_source' => $source, 'file_metadata' => $metadata,
        ],
    ];
} catch (Throwable $error) {
    if ($pdo !== null && $pdo->inTransaction()) {
        try {
            $safeToDelete = $pdo->rollBack();
        } catch (Throwable $rollbackError) {
            $safeToDelete = false;
        }
    }
    // Hindari menghapus aset jika hasil commit/rollback belum dapat dipastikan.
    if ($safeToDelete) {
        try {
            $storage->cleanup();
        } catch (Throwable $cleanupError) {
            error_log('add_galleries: pembersihan aset gagal; perlu rekonsiliasi.');
        }
    } else {
        error_log('add_galleries: hasil transaksi tidak pasti; periksa aset dan database.');
    }
    if (get_class($error) === RuntimeException::class && in_array($error->getCode(), [400, 403, 413, 415, 422, 500, 502], true)) {
        $statusCode = $error->getCode();
        $response = ['status' => 'error', 'message' => $error->getMessage()];
    } else {
        error_log('add_galleries: exception=' . get_class($error));
    }
}

$respond($statusCode, $response);
