<?php

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE, OPTIONS');
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
if ($method !== 'DELETE') {
    header('Allow: DELETE, OPTIONS');
    $respond(405, ['status' => 'error', 'message' => 'Method tidak diizinkan. Gunakan DELETE.']);
}

$config = require __DIR__ . '/../../_Config/config.php';
require_once __DIR__ . '/../../_Helper/GlobalFunction.php';
require_once __DIR__ . '/../../_Helper/Database.php';
require_once __DIR__ . '/../../_Helper/HeroImageStorage.php';
$userData = validateJWT($config);

$rawId = $_GET['id'] ?? '';
$id = is_string($rawId) ? filter_var(trim($rawId), FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 2147483647],
]) : false;
if ($id === false) {
    $respond(400, ['status' => 'error', 'message' => 'Id wajib berupa bilangan bulat positif yang valid.']);
}

$pdo = null;
$fileId = null;
$fileStatus = 'no_file';
$metadataDeleted = false;
try {
    $pdo = Database::getConnection();
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT id_file_manager FROM hero_slides WHERE id = :id FOR UPDATE');
    $stmt->execute(['id' => $id]);
    $hero = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$hero) {
        $pdo->rollBack();
        $respond(404, ['status' => 'error', 'message' => 'Data hero tidak ditemukan.']);
    }

    $fileId = $hero['id_file_manager'] === null ? null : (int) $hero['id_file_manager'];
    $removeMetadata = false;
    if ($fileId !== null) {
        $stmt = $pdo->prepare('SELECT file_source, file_metadata FROM file_manager WHERE id_file_manager = :id FOR UPDATE');
        $stmt->execute(['id' => $fileId]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$file) {
            throw new RuntimeException('Catatan file hero tidak ditemukan; periksa relasi file_manager.', 409);
        }

        // Seluruh relasi file_manager pada DB/web_sekolah.sql; jangan hapus aset bersama.
        // Kunci parent file_manager juga menahan penambahan referensi FK selama pemeriksaan.
        $shared = false;
        foreach (['teachers', 'articles', 'article_contents', 'facilities', 'galleries', 'hero_slides', 'testimonials'] as $table) {
            $query = 'SELECT id FROM `' . $table . '` WHERE id_file_manager = :file_id';
            $params = ['file_id' => $fileId];
            if ($table === 'hero_slides') {
                $query .= ' AND id <> :hero_id';
                $params['hero_id'] = $id;
            }
            $stmt = $pdo->prepare($query . ' LIMIT 1 FOR UPDATE');
            $stmt->execute($params);
            if ($stmt->fetchColumn() !== false) {
                $shared = true;
                break;
            }
        }
        if ($shared) {
            $fileStatus = 'retained_shared';
        } else {
            $storage = new HeroImageStorage($config);
            $fileStatus = $storage->deleteStoredFile($file['file_source'], json_decode($file['file_metadata']));
            // Storage nonaktif: simpan metadata untuk pengelolaan aset di kemudian hari.
            $removeMetadata = $fileStatus !== 'skipped_inactive';
        }
    }

    $stmt = $pdo->prepare('DELETE FROM hero_slides WHERE id = :id');
    $stmt->execute(['id' => $id]);
    if ($removeMetadata) {
        $stmt = $pdo->prepare('DELETE FROM file_manager WHERE id_file_manager = :id');
        $stmt->execute(['id' => $fileId]);
        $metadataDeleted = true;
    }
    $pdo->commit();

    $respond(200, [
        'status' => 'success',
        'message' => 'Data hero berhasil dihapus.',
        'requested_by_app' => $userData['app_name'] ?? 'Unknown',
        'data' => [
            'id' => $id,
            'id_file_manager' => $fileId,
            'file_deletion' => $fileStatus,
            'file_metadata_deleted' => $metadataDeleted,
        ],
    ]);
} catch (Throwable $error) {
    if ($pdo !== null && $pdo->inTransaction()) {
        try {
            $pdo->rollBack();
        } catch (Throwable $rollbackError) {
            error_log('delete_hero_slides: rollback gagal; hero_id=' . $id);
        }
    }
    // Transaksi SQL tidak dapat mengembalikan file yang sudah dihapus di storage.
    if ($fileStatus === 'deleted') {
        error_log('delete_hero_slides: rekonsiliasi diperlukan; hero_id=' . $id . '; file_id=' . $fileId);
    }
    if (get_class($error) === RuntimeException::class && in_array($error->getCode(), [409, 500, 502], true)) {
        $respond($error->getCode(), ['status' => 'error', 'message' => $error->getMessage()]);
    }
    error_log('delete_hero_slides: exception=' . get_class($error));
    $respond(500, ['status' => 'error', 'message' => 'Penghapusan data hero gagal. Silakan periksa kembali status data dan file.']);
}
