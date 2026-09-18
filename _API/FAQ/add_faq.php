<?php

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST, OPTIONS');
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Method tidak diizinkan. Gunakan POST.'
    ]);
    exit;
}

$config = require __DIR__ . '/../../_Config/config.php';
require_once __DIR__ . '/../../_Helper/GlobalFunction.php';
$userData = validateJWT($config);

// Contoh body JSON: {"question":"Apakah ada biaya lain?","answer":"Tidak ada"}
$input = json_decode(file_get_contents('php://input'));
if (json_last_error() !== JSON_ERROR_NONE || !is_object($input)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Body request harus berupa objek JSON yang valid.'
    ]);
    exit;
}

if (!isset($input->question, $input->answer)
    || !is_string($input->question) || !is_string($input->answer)
    || trim($input->question) === '' || trim($input->answer) === '') {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Question dan answer wajib berupa teks yang tidak kosong.'
    ]);
    exit;
}

$question = trim($input->question);
$answer = trim($input->answer);

try {
    $pdo = new PDO(
        'mysql:host=' . $config['db_host'] . ';dbname=' . $config['db_name'] . ';charset=utf8mb4',
        $config['db_user'],
        $config['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // FAQ pertama memakai urutan 1; urutan berikutnya mengikuti nilai terbesar.
    $sortOrder = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM faqs')->fetchColumn();
    $stmt = $pdo->prepare('INSERT INTO faqs (question, answer, sort_order) VALUES (:question, :answer, :sort_order)');
    $stmt->execute([
        'question' => $question,
        'answer' => $answer,
        'sort_order' => $sortOrder
    ]);

    http_response_code(201);
    echo json_encode([
        'status' => 'success',
        'message' => 'FAQ berhasil ditambahkan.',
        'requested_by_app' => $userData['app_name'] ?? 'Unknown',
        'data' => [
            'id' => (int) $pdo->lastInsertId(),
            'question' => $question,
            'answer' => $answer,
            'sort_order' => $sortOrder
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Terjadi kesalahan pada server database.'
    ]);
}
