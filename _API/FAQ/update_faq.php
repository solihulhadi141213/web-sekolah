<?php

    header('Content-Type: application/json; charset=UTF-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: PUT, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Cache-Control: no-store');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
        header('Allow: PUT, OPTIONS');
        http_response_code(405);
        echo json_encode([
            'status' => 'error',
            'message' => 'Method tidak diizinkan. Gunakan PUT.'
        ]);
        exit;
    }

    $config = require __DIR__ . '/../../_Config/config.php';
    require_once __DIR__ . '/../../_Helper/GlobalFunction.php';
    $userData = validateJWT($config);

    // Contoh body JSON: {"id":1,"question":"Apakah ada tes masuk?","answer":"Ya, ada tes singkat."}
    $input = json_decode(file_get_contents('php://input'));
    if (json_last_error() !== JSON_ERROR_NONE || !is_object($input)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Body request harus berupa objek JSON yang valid.'
        ]);
        exit;
    }

    if (!isset($input->id) || !is_int($input->id) || $input->id <= 0) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Id wajib berupa bilangan bulat positif.'
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

    $id = $input->id;
    $question = trim($input->question);
    $answer = trim($input->answer);
    $pdo = null;

    try {
        $pdo = new PDO(
            'mysql:host=' . $config['db_host'] . ';dbname=' . $config['db_name'] . ';charset=utf8mb4',
            $config['db_user'],
            $config['db_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        // Kunci FAQ selama pembaruan; data yang sama tetap menghasilkan sukses.
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('SELECT id, sort_order FROM faqs WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $id]);
        $faq = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$faq) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => 'FAQ tidak ditemukan.'
            ]);
            exit;
        }

        $stmt = $pdo->prepare('UPDATE faqs SET question = :question, answer = :answer WHERE id = :id');
        $stmt->execute([
            'question' => $question,
            'answer' => $answer,
            'id' => $id
        ]);
        $pdo->commit();

        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => 'FAQ berhasil diperbarui.',
            'requested_by_app' => $userData['app_name'] ?? 'Unknown',
            'data' => [
                'id' => $id,
                'question' => $question,
                'answer' => $answer,
                'sort_order' => $faq['sort_order']
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    } catch (PDOException $e) {
        if ($pdo !== null && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Terjadi kesalahan pada server database.'
        ]);
    }
