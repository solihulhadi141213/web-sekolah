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
    $respond(405, ['status' => 'error', 'message' => 'Method tidak diizinkan. Gunakan POST.']);
}

$config = require __DIR__ . '/../../_Config/config.php';
require_once __DIR__ . '/../../_Helper/GlobalFunction.php';
require_once __DIR__ . '/../../_Helper/Database.php';
$userData = validateJWT($config);

if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') {
    $respond(415, ['status' => 'error', 'message' => 'Content-Type harus application/json.']);
}

$input = json_decode(file_get_contents('php://input'));
if (json_last_error() !== JSON_ERROR_NONE || !is_object($input)) {
    $respond(400, ['status' => 'error', 'message' => 'Body request harus berupa objek JSON yang valid.']);
}
if (array_diff(array_keys(get_object_vars($input)), ['youtube_id', 'title', 'student_name'])) {
    $respond(400, ['status' => 'error', 'message' => 'Payload hanya boleh memuat youtube_id, title, dan student_name.']);
}

$fields = [];
foreach (['youtube_id' => 50, 'title' => 255, 'student_name' => 100] as $field => $maxLength) {
    if (!isset($input->$field) || !is_string($input->$field) || trim($input->$field) === '') {
        $respond(400, ['status' => 'error', 'message' => $field . ' wajib berupa teks yang tidak kosong.']);
    }
    $value = trim($input->$field);
    if (preg_match_all('/./us', $value) > $maxLength) {
        $respond(400, ['status' => 'error', 'message' => $field . ' maksimal ' . $maxLength . ' karakter.']);
    }
    $fields[$field] = $value;
}

if (!preg_match('/\A[A-Za-z0-9_-]{11}\z/', $fields['youtube_id'])) {
    $respond(400, ['status'=>'error', 'message'=>'Youtube_id harus berupa ID video 11 karakter, bukan URL.']);
}

$pdo = null;
try {
    $pdo = Database::getConnection();
    $pdo->beginTransaction();

    $sortOrder = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM videos')->fetchColumn();
    $stmt = $pdo->prepare('INSERT INTO videos (youtube_id, title, student_name, sort_order) VALUES (:youtube_id, :title, :student_name, :sort_order)');
    $stmt->execute($fields + ['sort_order' => $sortOrder]);
    $id = (int) $pdo->lastInsertId();
    $pdo->commit();

    $respond(201, [
        'status' => 'success',
        'message' => 'Video berhasil ditambahkan.',
        'requested_by_app' => $userData['app_name'] ?? 'Unknown',
        'data' => ['id' => $id] + $fields + ['sort_order' => $sortOrder],
    ]);
} catch (PDOException $error) {
    if ($pdo !== null && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $respond(500, ['status' => 'error', 'message' => 'Terjadi kesalahan pada server database.']);
}
