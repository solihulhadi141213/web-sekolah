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
$userData = validateJWT($config);

$rawId = $_GET['id'] ?? '';
$id = is_string($rawId) ? filter_var(trim($rawId), FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 2147483647],
]) : false;
if ($id === false) {
    $respond(400, ['status' => 'error', 'message' => 'Id wajib berupa bilangan bulat positif yang valid.']);
}

try {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare('DELETE FROM student_activities WHERE id = :id');
    $stmt->execute(['id' => $id]);
    if ($stmt->rowCount() === 0) {
        $respond(404, ['status'=>'error', 'message'=>'Data aktivitas tidak ditemukan.']);
    }
    $respond(200, ['status'=>'success', 'message'=>'Data aktivitas berhasil dihapus.',
        'requested_by_app'=>$userData['app_name'] ?? 'Unknown', 'data'=>['id'=>$id]]);
} catch (PDOException $error) {
    $respond(500, ['status'=>'error', 'message'=>'Terjadi kesalahan pada server database.']);
}
