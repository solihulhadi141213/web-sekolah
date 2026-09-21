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

if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') {
    $respond(415, ['status' => 'error', 'message' => 'Content-Type harus application/json.']);
}

$input = json_decode(file_get_contents('php://input'));
if (json_last_error() !== JSON_ERROR_NONE || !is_object($input)) {
    $respond(400, ['status' => 'error', 'message' => 'Body request harus berupa objek JSON yang valid.']);
}
if (array_diff(array_keys(get_object_vars($input)), ['id', 'name', 'role', 'subject'])) {
    $respond(400, ['status' => 'error', 'message' => 'Payload hanya boleh memuat id, name, role, dan subject.']);
}
if (!isset($input->id) || !is_int($input->id) || $input->id < 1 || $input->id > 4294967295) {
    $respond(400, ['status' => 'error', 'message' => 'Id harus berupa bilangan bulat positif yang valid.']);
}

$fields = [];
foreach (['name', 'role', 'subject'] as $field) {
    if (!isset($input->$field) || !is_string($input->$field) || trim($input->$field) === '') {
        $respond(400, ['status' => 'error', 'message' => $field . ' wajib berupa teks yang tidak kosong.']);
    }
    $value = trim($input->$field);
    if (preg_match_all('/./us', $value) > 100) {
        $respond(400, ['status' => 'error', 'message' => $field . ' maksimal 100 karakter.']);
    }
    $fields[$field] = $value;
}

$pdo = null;
try {
    $pdo = Database::getConnection();
    $pdo->beginTransaction();

    // Kunci baris selama pembaruan; payload identik tetap menghasilkan sukses.
    $stmt = $pdo->prepare('SELECT id FROM teachers WHERE id = :id FOR UPDATE');
    $stmt->execute(['id' => $input->id]);
    if ($stmt->fetchColumn() === false) {
        $pdo->rollBack();
        $respond(404, ['status' => 'error', 'message' => 'Data guru tidak ditemukan.']);
    }

    // Hanya tiga kolom informasi guru yang dapat diubah melalui endpoint ini.
    $stmt = $pdo->prepare('UPDATE teachers SET name = :name, role = :role, subject = :subject WHERE id = :id');
    $stmt->execute($fields + ['id' => $input->id]);
    $pdo->commit();

    $respond(200, [
        'status' => 'success',
        'message' => 'Informasi guru berhasil diperbarui.',
        'requested_by_app' => $userData['app_name'] ?? 'Unknown',
        'data' => ['id' => $input->id] + $fields,
    ]);
} catch (PDOException $error) {
    if ($pdo !== null && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $respond(500, ['status' => 'error', 'message' => 'Terjadi kesalahan pada server database.']);
}
