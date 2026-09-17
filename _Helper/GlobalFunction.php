<?php
    // Helper yang akan digunakan berulang

    require_once __DIR__ . '/../vendor/autoload.php';
    use Firebase\JWT\JWT;
    use Firebase\JWT\Key;
    use Firebase\JWT\ExpiredException;

    /**
     * Fungsi global untuk memvalidasi token JWT dari Header HTTP Authorization.
     * 
     * @param array $config Konfigurasi sistem dari config.php
     * @return array Mengembalikan data payload jika valid, atau mengirim respon error HTTP 401 dan menghentikan eksekusi jika tidak valid.
     */
    function validateJWT($config) {
        // Ambil semua HTTP headers
        $headers = [];
        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
        } else {
            // Fallback jika bukan Apache (misal Nginx)
            foreach ($_SERVER as $key => $value) {
                if (substr($key, 0, 5) == 'HTTP_') {
                    $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))))] = $value;
                }
            }
        }

        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        // Ekstrak token dari format "Bearer <token>"
        preg_match('/Bearer\s(\S+)/', $authHeader, $matches);
        $jwt = $matches[1] ?? '';

        if (empty($jwt)) {
            http_response_code(401);
            echo json_encode([
                "status" => "error",
                "message" => "Akses ditolak. Token autentikasi tidak ditemukan pada header."
            ]);
            exit;
        }

        try {
            // Decode dan verifikasi token menggunakan library firebase/php-jwt
            $decoded = JWT::decode($jwt, new Key($config['jwt_secret_key'], 'HS256'));
            
            // Konversi objek JWT ke bentuk array asosiatif agar mudah diakses
            return (array) $decoded;

        } catch (ExpiredException $e) {
            http_response_code(401);
            echo json_encode([
                "status" => "error",
                "message" => "Token sudah kedaluwarsa. Silakan lakukan *regenerate token*."
            ]);
            exit;
        } catch (\Exception $e) {
            http_response_code(401);
            echo json_encode([
                "status" => "error",
                "message" => "Token tidak valid: " . $e->getMessage()
            ]);
            exit;
        }
    }