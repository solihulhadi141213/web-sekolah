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
require_once __DIR__ . '/../../_Helper/ArticleImageStorage.php';
$userData = validateJWT($config);
require_once __DIR__ . '/../../_Helper/ArticleData.php';

$pdo = null;
$storage = new ArticleImageStorage($config);
$safeToDelete = true;
$statusCode = 500;
$response = ['status' => 'error', 'message' => 'Gagal menyimpan data artikel.'];

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
    if (array_diff(array_keys(get_object_vars($input)), ['title','slug','category_tag','summary','status','tag_ids','tags','file_source','base64','image_url'])) throw new RuntimeException('Payload berisi field yang tidak didukung.', 400);
    $fields = ArticleData::fields($input);
    $fields['status'] = $fields['status'] ?? 'published';
    $tagInput = ArticleData::tagInput($input);
    $source = null;
    if (property_exists($input, 'file_source')) {
        if (!is_string($input->file_source) || !in_array($input->file_source, ['Local Directory', 'External Link', 'Cloudinary', 'Imagekit'], true)) {
            throw new RuntimeException('File_source harus Local Directory, External Link, Cloudinary, atau Imagekit.', 400);
        }
        $source = $input->file_source;
    } elseif (property_exists($input, 'base64') || property_exists($input, 'image_url') || property_exists($input, 'image_base64')) {
        throw new RuntimeException('File_source wajib disertakan jika mengirim gambar.', 400);
    }

    // Persiapkan query sebelum mengunggah untuk mendeteksi struktur DB yang tidak sesuai.
    $pdo = Database::getConnection();
    $articleInsert = $pdo->prepare('INSERT INTO articles (title, slug, category_tag, summary, status, id_file_manager)
        VALUES (:title, :slug, :category_tag, :summary, :status, :id_file_manager)');
    $fileInsert = $pdo->prepare('INSERT INTO file_manager (file_source, file_metadata, creat_at)
        VALUES (:file_source, :file_metadata, UTC_TIMESTAMP())');
    $metadata = $source === null ? null : $storage->store($source, $input);
    unset($input);

    // Upload dilakukan sebelum transaksi agar tidak menahan kunci DB selama akses jaringan.
    $pdo->beginTransaction();
    $safeToDelete = false;
    $fileId = null;
    if ($metadata !== null) {
        $fileInsert->execute([
            'file_source' => $source,
            'file_metadata' => json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ]);
        $fileId = (int) $pdo->lastInsertId();
    }
    $articleInsert->execute($fields + ['id_file_manager' => $fileId]);
    $id = (int) $pdo->lastInsertId();
    ArticleData::syncTags($pdo, $id, $tagInput);
    $tags = ArticleData::attachTags($pdo, [['id'=>$id]])[0]['tags'];
    $pdo->commit();

    $statusCode = 201;
    $response = [
        'status' => 'success', 'message' => 'Data artikel berhasil ditambahkan.',
        'requested_by_app' => $userData['app_name'] ?? 'Unknown',
        'data' => ['id' => $id, 'tags'=>$tags] + $fields + [
            'id_file_manager' => $fileId,
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
            error_log('add_articles: pembersihan aset gagal; perlu rekonsiliasi.');
        }
    } else {
        error_log('add_articles: hasil transaksi tidak pasti; periksa aset dan database.');
    }
    if ($error instanceof PDOException && (int) ($error->errorInfo[1] ?? 0) === 1062) {
        $statusCode = 409; $response = ['status'=>'error','message'=>'Slug artikel atau tag sudah digunakan.'];
    } elseif (get_class($error) === RuntimeException::class && in_array($error->getCode(), [400, 403, 409, 413, 415, 422, 500, 502], true)) {
        $statusCode = $error->getCode();
        $response = ['status' => 'error', 'message' => $error->getMessage()];
    } else {
        error_log('add_articles: exception=' . get_class($error));
    }
}

$respond($statusCode, $response);
