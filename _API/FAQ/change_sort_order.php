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
    echo json_encode(['status' => 'error', 'message' => 'Method tidak diizinkan. Gunakan PUT.']);
    exit;
}

$config = require __DIR__ . '/../../_Config/config.php';
require_once __DIR__ . '/../../_Helper/GlobalFunction.php';
require_once __DIR__ . '/../../_Helper/Database.php';
$userData = validateJWT($config);

// Nama field request mengikuti kontrak API: short_order, kolom database: sort_order.
// Contoh: {"id":1,"short_order":"UP"}
$input = json_decode(file_get_contents('php://input'));
if (json_last_error() !== JSON_ERROR_NONE || !is_object($input)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Body request harus berupa objek JSON yang valid.']);
    exit;
}

if (!isset($input->id) || !is_int($input->id) || $input->id <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Id wajib berupa bilangan bulat positif.']);
    exit;
}

if (!isset($input->short_order) || !in_array($input->short_order, ['UP', 'DOWN'], true)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Short_order wajib diisi dengan UP atau DOWN.']);
    exit;
}

$pdo = null;
try {
    $pdo = Database::getConnection();
    $pdo->beginTransaction();

    // Kunci urutan secara konsisten agar permintaan pertukaran tidak saling menimpa.
    $rows = $pdo->query('SELECT id, sort_order FROM faqs ORDER BY id FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC);
    $faq = null;
    $maxOrder = 0;
    foreach ($rows as $row) {
        $maxOrder = max($maxOrder, (int) $row['sort_order']);
        if ((int) $row['id'] === $input->id) {
            $faq = $row;
        }
    }

    if ($faq === null) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'FAQ tidak ditemukan.']);
        exit;
    }

    $oldOrder = (int) $faq['sort_order'];
    // Tolak perpindahan keluar batas sebelum memperbarui posisi FAQ mana pun.
    if ($input->short_order === 'UP' && $oldOrder <= 1) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['status' => 'error', 'message' => 'FAQ sudah berada di posisi paling atas dan tidak dapat dipindahkan ke atas.']);
        exit;
    }

    if ($input->short_order === 'DOWN' && $oldOrder >= $maxOrder) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['status' => 'error', 'message' => 'FAQ sudah berada di posisi paling bawah dan tidak dapat dipindahkan ke bawah.']);
        exit;
    }

    $newOrder = $oldOrder + ($input->short_order === 'UP' ? -1 : 1);
    $neighbors = [];
    foreach ($rows as $row) {
        if ((int) $row['id'] !== $input->id && (int) $row['sort_order'] === $newOrder) {
            $neighbors[] = (int) $row['id'];
        }
    }

    if (count($neighbors) > 1) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['status' => 'error', 'message' => 'Posisi tujuan digunakan lebih dari satu FAQ. Perbaiki urutan terlebih dahulu.']);
        exit;
    }

    $update = $pdo->prepare('UPDATE faqs SET sort_order = :sort_order WHERE id = :id');
    if ($neighbors) {
        // Kosongkan posisi asal sementara agar pertukaran juga mendukung indeks unik.
        $update->execute(['sort_order' => $maxOrder + 1, 'id' => $input->id]);
        $update->execute(['sort_order' => $oldOrder, 'id' => $neighbors[0]]);
    }
    // Jika posisi tujuan kosong, cukup ubah nilai urutan sebesar satu.
    $update->execute(['sort_order' => $newOrder, 'id' => $input->id]);
    $pdo->commit();

    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'Posisi FAQ berhasil diubah.',
        'requested_by_app' => $userData['app_name'] ?? 'Unknown',
        'data' => [
            'id' => $input->id,
            'short_order' => $input->short_order,
            'previous_sort_order' => $oldOrder,
            'sort_order' => $newOrder,
            'swapped_id' => $neighbors[0] ?? null
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (PDOException $e) {
    if ($pdo !== null && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan pada server database.']);
}
