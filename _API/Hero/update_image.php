<?php

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, OPTIONS');
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
if ($method !== 'PUT') {
    header('Allow: PUT, OPTIONS');
    $respond(405, ['status' => 'error', 'message' => 'Method tidak diizinkan. Gunakan PUT.']);
}

$config = require __DIR__ . '/../../_Config/config.php';
require_once __DIR__ . '/../../_Helper/GlobalFunction.php';
require_once __DIR__ . '/../../_Helper/Database.php';
require_once __DIR__ . '/../../_Helper/HeroImageStorage.php';
$userData = validateJWT($config);

// Pemanggil harus mengunci baris parent file_manager sebelum memeriksa referensi.
$hasReferences = static function (PDO $pdo, int $fileId, ?int $excludeHero = null): bool {
    foreach (['teachers', 'articles', 'article_contents', 'facilities', 'galleries', 'hero_slides', 'testimonials'] as $table) {
        $sql = 'SELECT id FROM `' . $table . '` WHERE id_file_manager = :file_id';
        $params = ['file_id' => $fileId];
        if ($table === 'hero_slides' && $excludeHero !== null) {
            $sql .= ' AND id <> :hero_id';
            $params['hero_id'] = $excludeHero;
        }
        $stmt = $pdo->prepare($sql . ' LIMIT 1 FOR UPDATE');
        $stmt->execute($params);
        if ($stmt->fetchColumn() !== false) {
            return true;
        }
    }
    return false;
};

$pdo = null;
$newStorage = new HeroImageStorage($config);
$oldStorage = new HeroImageStorage($config);
$safeToDeleteNew = true;
$committed = false;
$oldFileId = null;
$oldStatus = 'no_file';
$oldMetadataDeleted = false;
$warnings = [];

try {
    if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') {
        throw new RuntimeException('Content-Type harus application/json.', 415);
    }
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
    if (!isset($input->id) || !is_int($input->id) || $input->id < 1 || $input->id > 2147483647) {
        throw new RuntimeException('Id wajib berupa bilangan bulat positif yang valid.', 400);
    }
    if (!isset($input->file_source) || !is_string($input->file_source)
        || !in_array($input->file_source, ['DELETE', 'Local Directory', 'External Link', 'Cloudinary', 'Imagekit'], true)) {
        throw new RuntimeException('File_source harus DELETE, Local Directory, External Link, Cloudinary, atau Imagekit.', 400);
    }
    if (array_diff(array_keys(get_object_vars($input)), ['id', 'file_source', 'base64', 'image_url'])) {
        throw new RuntimeException('Endpoint ini hanya menerima id, file_source, base64, dan image_url.', 400);
    }
    $id = $input->id;
    $source = $input->file_source;
    $deleting = $source === 'DELETE';
    if ($deleting && ((property_exists($input, 'base64') && $input->base64 !== '') || property_exists($input, 'image_url'))) {
        throw new RuntimeException('Untuk DELETE, base64 harus kosong dan image_url tidak boleh dikirim.', 400);
    }

    $pdo = Database::getConnection();
    // Periksa hero dan metadata sebelum mengunggah; tidak menahan kunci saat upload.
    $selectHero = $pdo->prepare('SELECT id_file_manager FROM hero_slides WHERE id = :id');
    $selectHero->execute(['id' => $id]);
    $hero = $selectHero->fetch(PDO::FETCH_ASSOC);
    if (!$hero) {
        throw new RuntimeException('Data hero tidak ditemukan.', 404);
    }
    $oldFileId = $hero['id_file_manager'] === null ? null : (int) $hero['id_file_manager'];
    if ($oldFileId !== null) {
        $stmt = $pdo->prepare('SELECT file_source, file_metadata FROM file_manager WHERE id_file_manager = :id');
        $stmt->execute(['id' => $oldFileId]);
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            throw new RuntimeException('Catatan file hero tidak ditemukan. Periksa relasi file_manager.', 409);
        }
    }
    $fileInsert = $pdo->prepare('INSERT INTO file_manager (file_source, file_metadata, creat_at) VALUES (:source, :metadata, UTC_TIMESTAMP())');
    $updateHero = $pdo->prepare('UPDATE hero_slides SET id_file_manager = :file_id WHERE id = :id');
    $metadata = $deleting ? null : $newStorage->store($source, $input);
    unset($input);

    $pdo->beginTransaction();
    $safeToDeleteNew = false;
    $stmt = $pdo->prepare('SELECT id_file_manager FROM hero_slides WHERE id = :id FOR UPDATE');
    $stmt->execute(['id' => $id]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$current) {
        throw new RuntimeException('Data hero tidak ditemukan.', 404);
    }
    $currentFileId = $current['id_file_manager'] === null ? null : (int) $current['id_file_manager'];
    if ($currentFileId !== $oldFileId) {
        throw new RuntimeException('Gambar hero telah diubah oleh request lain. Muat ulang data sebelum mencoba kembali.', 409);
    }

    $newFileId = null;
    if ($deleting) {
        if ($oldFileId !== null) {
            $stmt = $pdo->prepare('SELECT file_source, file_metadata FROM file_manager WHERE id_file_manager = :id FOR UPDATE');
            $stmt->execute(['id' => $oldFileId]);
            $oldFile = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$oldFile) {
                throw new RuntimeException('Catatan file hero tidak ditemukan.', 409);
            }
            $oldStatus = $hasReferences($pdo, $oldFileId, $id) ? 'retained_shared'
                : $oldStorage->deleteStoredFile($oldFile['file_source'], json_decode($oldFile['file_metadata']));
        }
        $updateHero->execute(['file_id' => null, 'id' => $id]);
        if ($oldFileId !== null && !in_array($oldStatus, ['retained_shared', 'skipped_inactive'], true)) {
            $stmt = $pdo->prepare('DELETE FROM file_manager WHERE id_file_manager = :id');
            $stmt->execute(['id' => $oldFileId]);
            $oldMetadataDeleted = true;
        }
    } else {
        $fileInsert->execute(['source' => $source, 'metadata' => json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)]);
        $newFileId = (int) $pdo->lastInsertId();
        $updateHero->execute(['file_id' => $newFileId, 'id' => $id]);
    }
    $pdo->commit();
    $committed = true;

    // Hapus gambar lama hanya SETELAH relasi ke gambar baru berhasil di-commit.
    // Kegagalan pembersihan tidak membatalkan gambar baru atau memicu upload ulang.
    if (!$deleting && $oldFileId !== null) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT file_source, file_metadata FROM file_manager WHERE id_file_manager = :id FOR UPDATE');
            $stmt->execute(['id' => $oldFileId]);
            $oldFile = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$oldFile) {
                $oldStatus = 'not_found';
                $oldMetadataDeleted = true;
            } elseif ($hasReferences($pdo, $oldFileId)) {
                $oldStatus = 'retained_shared';
            } else {
                $oldMetadata = json_decode($oldFile['file_metadata']);
                // External Link bisa menunjuk aset lama itu sendiri; jangan putuskan URL baru.
                $linksToOldAsset = false;
                if ($source === 'External Link' && is_object($oldMetadata)) {
                    $linkedUrl = $metadata['file_url'];
                    $linksToOldAsset = $linkedUrl === ($oldMetadata->url ?? null);
                    if ($oldFile['file_source'] === 'Local Directory' && is_string($oldMetadata->file_name ?? null)) {
                        $suffix = '/assets/img/local_directory/' . $oldMetadata->file_name;
                        $path = rawurldecode(parse_url($linkedUrl, PHP_URL_PATH) ?? '');
                        $linksToOldAsset = substr($path, -strlen($suffix)) === $suffix;
                    }
                }
                $oldStatus = $linksToOldAsset ? 'retained_linked'
                    : $oldStorage->deleteStoredFile($oldFile['file_source'], $oldMetadata);
                if (!in_array($oldStatus, ['skipped_inactive', 'retained_linked'], true)) {
                    $stmt = $pdo->prepare('DELETE FROM file_manager WHERE id_file_manager = :id');
                    $stmt->execute(['id' => $oldFileId]);
                    $oldMetadataDeleted = true;
                }
            }
            $pdo->commit();
        } catch (Throwable $cleanupError) {
            if ($pdo->inTransaction()) {
                try { $pdo->rollBack(); } catch (Throwable $rollbackError) {
                    error_log('update_hero_slides_image: rollback cleanup gagal; file_id=' . $oldFileId);
                }
            }
            $oldStatus = 'cleanup_pending';
            $oldMetadataDeleted = false;
            $warnings[] = 'Gambar baru berhasil disimpan, tetapi pembersihan gambar lama belum selesai. Jangan unggah ulang; periksa file lama melalui id_file_manager.';
            error_log('update_hero_slides_image: cleanup tertunda; hero_id=' . $id . '; file_id=' . $oldFileId);
        }
    }

    $respond(200, [
        'status' => 'success',
        'message' => $deleting ? 'Gambar hero berhasil dilepas.' : 'Gambar hero berhasil diperbarui.',
        'requested_by_app' => $userData['app_name'] ?? 'Unknown',
        'data' => [
            'id' => $id, 'id_file_manager' => $newFileId,
            'file_source' => $deleting ? null : $source, 'file_metadata' => $metadata,
            'previous_id_file_manager' => $oldFileId, 'old_file_deletion' => $oldStatus,
            'old_file_metadata_deleted' => $oldMetadataDeleted,
        ],
        'warnings' => $warnings,
    ]);
} catch (Throwable $error) {
    if ($pdo !== null && $pdo->inTransaction()) {
        try { $safeToDeleteNew = $pdo->rollBack(); } catch (Throwable $rollbackError) { $safeToDeleteNew = false; }
    }
    if (!$committed && $safeToDeleteNew) {
        try { $newStorage->cleanup(); } catch (Throwable $cleanupError) {
            error_log('update_hero_slides_image: pembersihan upload baru gagal; hero_id=' . ($id ?? 0));
        }
    } elseif (!$committed) {
        error_log('update_hero_slides_image: status commit tidak pasti; periksa upload baru; hero_id=' . ($id ?? 0));
    }
    if ($oldStatus === 'deleted') {
        error_log('update_hero_slides_image: periksa konsistensi file lama; file_id=' . $oldFileId);
    }
    if (get_class($error) === RuntimeException::class && in_array($error->getCode(), [400, 403, 404, 409, 413, 415, 422, 500, 502], true)) {
        $respond($error->getCode(), ['status' => 'error', 'message' => $error->getMessage()]);
    }
    error_log('update_hero_slides_image: exception=' . get_class($error));
    $respond(500, ['status' => 'error', 'message' => 'Pembaruan gambar hero gagal. Periksa kembali status data dan file.']);
}
