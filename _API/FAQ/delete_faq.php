<?php

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    header('Allow: DELETE, OPTIONS');
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Method tidak diizinkan. Gunakan DELETE.'
    ]);
    exit;
}

$config = require __DIR__ . '/../../_Config/config.php';
require_once __DIR__ . '/../../_Helper/GlobalFunction.php';
require_once __DIR__ . '/../../_Helper/Database.php';
$userData = validateJWT($config);

// ID diambil dari query string: delete_faq.php?id=11
$rawId = $_GET['id'] ?? '';

if (is_string($rawId)) {
    $rawId = trim($rawId);
}

if ($rawId === '') {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'ID FAQ tidak boleh kosong.'
    ]);
    exit;
}

// Sesuai kolom id INT signed: bilangan bulat positif hingga 2147483647.
$id = is_string($rawId)
    ? filter_var($rawId, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 2147483647]
    ])
    : false;

if ($id === false) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'ID FAQ harus berupa bilangan bulat positif yang valid.'
    ]);
    exit;
}

try {
    $pdo = Database::getConnection();

    $stmt = $pdo->prepare('DELETE FROM faqs WHERE id = :id');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'Data FAQ tidak ditemukan.'
        ]);
        exit;
    }

    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'FAQ berhasil dihapus.',
        'requested_by_app' => $userData['app_name'] ?? 'Unknown',
        'data' => ['id' => $id]
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Terjadi kesalahan pada server database. FAQ gagal dihapus.'
    ]);
}
