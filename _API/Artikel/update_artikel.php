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
$userData = validateJWT($config);
require_once __DIR__ . '/../../_Helper/ArticleData.php';

if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') {
    $respond(415, ['status' => 'error', 'message' => 'Content-Type harus application/json.']);
}

$input = json_decode(file_get_contents('php://input'));
if (json_last_error() !== JSON_ERROR_NONE || !is_object($input)) {
    $respond(400, ['status' => 'error', 'message' => 'Body request harus berupa objek JSON yang valid.']);
}
if (array_diff(array_keys(get_object_vars($input)), ['id','title','slug','category_tag','summary','status','tag_ids','tags'])) {
    $respond(400, ['status' => 'error', 'message' => 'Payload hanya boleh memuat data utama artikel dan tag.']);
}
if (!isset($input->id) || !is_int($input->id) || $input->id < 1 || $input->id > 4294967295) {
    $respond(400, ['status' => 'error', 'message' => 'Id harus berupa bilangan bulat positif yang valid.']);
}

$pdo = null;
try {
    $fields = ArticleData::fields($input);
    $tagInput = ArticleData::tagInput($input);
    $pdo = Database::getConnection();
    $pdo->beginTransaction();

    // Kunci baris selama pembaruan; payload identik tetap menghasilkan sukses.
    $stmt = $pdo->prepare('SELECT id, status FROM articles WHERE id = :id FOR UPDATE');
    $stmt->execute(['id' => $input->id]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$current) {
        $pdo->rollBack();
        $respond(404, ['status' => 'error', 'message' => 'Data artikel tidak ditemukan.']);
    }

    $fields['status'] = $fields['status'] ?? $current['status'];
    $stmt = $pdo->prepare('UPDATE articles SET title = :title, slug = :slug, category_tag = :category_tag, summary = :summary, status = :status WHERE id = :id');
    $stmt->execute($fields + ['id' => $input->id]);
    ArticleData::syncTags($pdo, $input->id, $tagInput);
    $tags = ArticleData::attachTags($pdo, [['id'=>$input->id]])[0]['tags'];
    $pdo->commit();

    $respond(200, [
        'status' => 'success',
        'message' => 'Informasi artikel berhasil diperbarui.',
        'requested_by_app' => $userData['app_name'] ?? 'Unknown',
        'data' => ['id' => $input->id, 'tags'=>$tags] + $fields,
    ]);
} catch (Throwable $error) {
    if ($pdo !== null && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (get_class($error) === RuntimeException::class && in_array($error->getCode(), [400,409], true)) $respond($error->getCode(), ['status'=>'error','message'=>$error->getMessage()]);
    if ($error instanceof PDOException && (int) ($error->errorInfo[1] ?? 0) === 1062) $respond(409, ['status'=>'error','message'=>'Slug artikel atau tag sudah digunakan.']);
    $respond(500, ['status' => 'error', 'message' => 'Terjadi kesalahan pada server database.']);
}
