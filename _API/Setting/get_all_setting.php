<?php

    // Header untuk mendefinisikan respons JSON dan mengizinkan CORS
    header("Content-Type: application/json; charset=UTF-8");
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET");
    header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

    // Batasi hanya method GET yang diizinkan
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode([
            "status" => "error",
            "message" => "Method tidak diizinkan. Gunakan GET."
        ]);
        exit;
    }

    // 1. Panggil file konfigurasi dan helper global (naik dua tingkat ke root, lalu masuk ke folder terkait)
    $config = require __DIR__ . '/../../_Config/config.php';
    require_once __DIR__ . '/../../_Helper/GlobalFunction.php';

    // 2. Jalankan validasi JWT (Otomatis memblokir akses jika token tidak valid/expired)
    $userData = validateJWT($config);

    try {
        // 3. Buat koneksi database PDO
        $pdo = new PDO(
            "mysql:host=" . $config['db_host'] . ";dbname=" . $config['db_name'] . ";charset=utf8mb4",
            $config['db_user'],
            $config['db_pass']
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 4. Ambil semua data dari tabel web_settings
        $stmt = $pdo->query("SELECT setting_key, setting_value, description, updated_at FROM web_settings ORDER BY id ASC");
        $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Ubah struktur array agar menjadi bentuk Key-Value yang lebih ramah konsumsi frontend/API,
        // atau biarkan dalam bentuk list. Di sini kita sediakan format list lengkap beserta key-value-nya.
        $formattedSettings = [];
        foreach ($settings as $row) {
            $formattedSettings[$row['setting_key']] = [
                "value"       => $row['setting_value'],
                "description" => $row['description'],
                "updated_at"  => $row['updated_at']
            ];
        }

        // 5. Kirim respons sukses berformat JSON
        http_response_code(200);
        echo json_encode([
            "status"           => "success",
            "message"          => "Data pengaturan berhasil dimuat.",
            "total_records"    => count($settings),
            "requested_by_app" => $userData['app_name'] ?? 'Unknown',
            "data"             => $formattedSettings
        ]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            "status"  => "error",
            "message" => "Terjadi kesalahan pada server database."
        ]);
    }
?>