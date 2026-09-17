<?php
    // Header untuk mendefinisikan respons JSON
    header("Content-Type: application/json; charset=UTF-8");
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST");
    header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

    // Batasi hanya method POST yang diizinkan untuk mengambil token
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            "status" => "error",
            "message" => "Method tidak diizinkan. Gunakan POST."
        ]);
        exit;
    }

    // 1. Panggil file konfigurasi dan autoloader Composer dari folder utama (naik dua tingkat)
    $config = require __DIR__ . '/../../_Config/config.php';
    require __DIR__ . '/../../vendor/autoload.php';

    use Firebase\JWT\JWT;

    // 2. Ambil data JSON atau Form-data yang dikirim oleh klien (Aplikasi/Mobile)
    $input = json_decode(file_get_contents("php://input"), true);
    $api_key = $input['api_key'] ?? $_POST['api_key'] ?? '';
    $api_secret = $input['api_secret'] ?? $_POST['api_secret'] ?? '';

    // Validasi input kosong
    if (empty($api_key) || empty($api_secret)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "API Key dan API Secret wajib diisi."
        ]);
        exit;
    }

    try {
        // 3. Buat koneksi database PDO
        $pdo = new PDO(
            "mysql:host=" . $config['db_host'] . ";dbname=" . $config['db_name'] . ";charset=utf8mb4",
            $config['db_user'],
            $config['db_pass']
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 4. Cari api_key di database dan pastikan statusnya aktif (is_active = 1)
        $stmt = $pdo->prepare("SELECT * FROM api_credentials WHERE api_key = :api_key AND is_active = 1");
        $stmt->execute([':api_key' => $api_key]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$client) {
            http_response_code(401);
            echo json_encode([
                "status" => "error",
                "message" => "Autentikasi gagal: API Key tidak valid atau dinonaktifkan."
            ]);
            exit;
        }

        // 5. Verifikasi kecocokan API Secret dengan hash Bcrypt di database
        if (!password_verify($api_secret, $client['api_secret'])) {
            http_response_code(401);
            echo json_encode([
                "status" => "error",
                "message" => "Autentikasi gagal: API Secret salah."
            ]);
            exit;
        }

        // 6. Jika validasi sukses, buat Payload JWT dengan masa aktif 1 jam (3600 detik)
        $issuedAt   = time();
        $expiration = $issuedAt + 3600; // kedaluwarsa 1 jam ke depan

        $payload = [
            'iss'           => $config['jwt_issuer'],
            'iat'           => $issuedAt,
            'exp'           => $expiration,
            'credential_id' => $client['id'],
            'app_name'      => $client['app_name'],
            'permissions'   => json_decode($client['permissions'])
        ];

        // Encode payload menjadi string JWT menggunakan algoritma HS256
        $jwt = JWT::encode($payload, $config['jwt_secret_key'], 'HS256');

        // 7. Update waktu terakhir kali credential ini digunakan
        $updateStmt = $pdo->prepare("UPDATE api_credentials SET last_used_at = NOW() WHERE id = :id");
        $updateStmt->execute([':id' => $client['id']]);

        // 8. Berikan respons sukses beserta token kepada klien
        http_response_code(200);
        echo json_encode([
            "status"       => "success",
            "message"      => "Token berhasil dibuat.",
            "access_token" => $jwt,
            "token_type"   => "Bearer",
            "expires_in"   => 3600, // Informasi ke klien bahwa token berumur 3600 detik (1 jam)
            "expired_at"   => date('Y-m-d H:i:s', $expiration)
        ]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Terjadi kesalahan pada server database."
        ]);
    }
?>