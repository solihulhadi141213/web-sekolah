<?php

// Tetapkan respons JSON dan izinkan header autentikasi untuk permintaan lintas origin.
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Cache-Control: no-store');

// Preflight hanya mengembalikan izin CORS, tanpa mengakses data FAQ.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Data FAQ hanya dapat diminta menggunakan metode GET.
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET, OPTIONS');
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Method tidak diizinkan. Gunakan GET.'
    ]);
    exit;
}

// Gunakan konfigurasi dan validasi JWT yang sama dengan endpoint pengaturan.
$config = require __DIR__ . '/../../_Config/config.php';
require_once __DIR__ . '/../../_Helper/GlobalFunction.php';
require_once __DIR__ . '/../../_Helper/Database.php';
$userData = validateJWT($config);

try {
    // Hubungkan database setelah token berhasil divalidasi.
    $pdo = Database::getConnection();

    // Urutkan FAQ berdasarkan urutan tampil, lalu ID agar hasil tetap konsisten.
    $stmt = $pdo->query('SELECT id, question, answer, sort_order FROM faqs ORDER BY sort_order ASC, id ASC');
    $faqs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Tabel kosong tetap menghasilkan respons sukses dengan data berupa array kosong.
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'Data FAQ berhasil dimuat.',
        'total_records' => count($faqs),
        'requested_by_app' => $userData['app_name'] ?? 'Unknown',
        'data' => $faqs
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (PDOException $e) {
    // Jangan tampilkan detail koneksi atau pesan internal database kepada klien.
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Terjadi kesalahan pada server database.'
    ]);
}
