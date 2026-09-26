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
if (!isset($input->id) || !is_int($input->id) || $input->id < 1 || $input->id > 2147483647) {
    $respond(400, ['status' => 'error', 'message' => 'Id wajib berupa bilangan bulat positif yang valid.']);
}
if (!isset($input->sort_order) || !in_array($input->sort_order, ['UP', 'DOWN'], true)) {
    $respond(400, ['status' => 'error', 'message' => 'Sort_order wajib diisi dengan UP atau DOWN.']);
}

$pdo = null;
try {
    $pdo = Database::getConnection();
    $pdo->beginTransaction();
    // Kunci urutan untuk mencegah pertukaran paralel saling menimpa.
    $rows = $pdo->query('SELECT id, sort_order FROM videos ORDER BY sort_order ASC, id ASC FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC);
    $position = null;
    foreach ($rows as $index => $row) {
        if ((int) $row['id'] === $input->id) {
            $position = $index;
            break;
        }
    }
    if ($position === null) {
        throw new RuntimeException('Data video tidak ditemukan.', 404);
    }
    $oldOrder = $rows[$position]['sort_order'];
    if ($oldOrder === null || (int) $oldOrder < 0) {
        throw new RuntimeException('Urutan video tidak valid. Sort_order tidak boleh negatif.', 409);
    }
    $oldOrder = (int) $oldOrder;
    if ($input->sort_order === 'UP' && $position === 0) {
        throw new RuntimeException('Video sudah berada di posisi paling atas dan tidak dapat dipindahkan ke atas.', 409);
    }
    if ($input->sort_order === 'DOWN' && $position === count($rows) - 1) {
        throw new RuntimeException('Video sudah berada di posisi terakhir dan tidak dapat dipindahkan ke bawah.', 409);
    }

    // Tukar dengan tetangga terdekat, termasuk jika ada celah urutan akibat penghapusan.
    $neighbor = $rows[$position + ($input->sort_order === 'UP' ? -1 : 1)];
    $newOrder = (int) $neighbor['sort_order'];
    if ($neighbor['sort_order'] === null || $newOrder < 0) {
        throw new RuntimeException('Urutan video di posisi tujuan tidak valid.', 409);
    }
    $matching = 0;
    foreach ($rows as $row) {
        if ((int) $row['sort_order'] === $oldOrder || (int) $row['sort_order'] === $newOrder) {
            $matching++;
        }
    }
    if ($oldOrder === $newOrder || $matching !== 2) {
        throw new RuntimeException('Urutan asal atau tujuan digunakan lebih dari satu video. Perbaiki urutan terlebih dahulu.', 409);
    }

    // Schema videos tidak memiliki indeks unik sort_order; kedua update berada dalam satu transaksi.
    $update = $pdo->prepare('UPDATE videos SET sort_order = :sort_order WHERE id = :id');
    $update->execute(['sort_order' => $newOrder, 'id' => $input->id]);
    $update->execute(['sort_order' => $oldOrder, 'id' => (int) $neighbor['id']]);
    $pdo->commit();

    $respond(200, [
        'status' => 'success',
        'message' => 'Posisi video berhasil diubah.',
        'requested_by_app' => $userData['app_name'] ?? 'Unknown',
        'data' => [
            'id' => $input->id,
            'direction' => $input->sort_order,
            'previous_sort_order' => $oldOrder,
            'sort_order' => $newOrder,
            'swapped_id' => (int) $neighbor['id'],
        ],
    ]);
} catch (Throwable $error) {
    if ($pdo !== null && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (get_class($error) === RuntimeException::class && in_array($error->getCode(), [404, 409], true)) {
        $respond($error->getCode(), ['status' => 'error', 'message' => $error->getMessage()]);
    }
    $respond(500, ['status' => 'error', 'message' => 'Terjadi kesalahan pada server database. Posisi video gagal diubah.']);
}
