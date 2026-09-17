<?php
    // Pastikan hanya admin yang bisa menjalankan script ini!
    // Di lingkungan produksi, amankan URL ini atau hapus file setelah digunakan.

    // 1. Tangkap array dari config.php
    $config = require "_Config/config.php";

    try {
        // 2. Membuat koneksi menggunakan PDO
        $pdo = new PDO(
            "mysql:host=" . $config['db_host'] . ";dbname=" . $config['db_name'] . ";charset=utf8mb4", 
            $config['db_user'], 
            $config['db_pass']
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // =========================================================================
        // FITUR KEAMANAN: Cek apakah sudah ada API Credential yang aktif
        // =========================================================================
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM api_credentials WHERE is_active = 1");
        $stmt_check->execute();
        $active_count = $stmt_check->fetchColumn();

        if ($active_count > 0) {
            // Jika sudah ada record aktif, blokir proses pembuatan!
            echo "<div style='font-family: sans-serif; max-width: 600px; margin: 40px auto; padding: 20px; border: 1px solid #f44336; border-radius: 8px; background-color: #fee;'>";
            echo "<h2 style='color: #d32f2f; margin-top: 0;'>Akses Ditolak: Kredensial Sudah Ada!</h2>";
            echo "<p>Sistem mendeteksi bahwa sudah ada <strong>" . $active_count . " kredensial aktif</strong> di dalam database.</p>";
            echo "<p>Untuk mencegah pembuatan kredensial ganda atau penyalahgunaan script, proses ini diblokir.</p>";
            echo "<hr style='border: 0; border-top: 1px solid #f44336; margin: 15px 0;'>";
            echo "<p><strong>Solusi:</strong> Jika Anda benar-benar ingin membuat kunci baru secara paksa, silakan nonaktifkan (set <code>is_active = 0</code>) atau hapus baris data di tabel <code>api_credentials</code> secara manual melalui phpMyAdmin, lalu muat ulang halaman ini.</p>";
            echo "</div>";
            
            exit; // HENTIKAN SCRIPT SAMPAI DI SINI
        }
        // =========================================================================

        // 3. Jika kosong/tidak ada yang aktif, Lanjutkan Generate API Key & Secret
        $api_key = bin2hex(random_bytes(16)); 
        $plain_api_secret = bin2hex(random_bytes(32)); 
        $hashed_api_secret = password_hash($plain_api_secret, PASSWORD_DEFAULT);

        // Konfigurasi Data Ekstra
        $app_name = "Aplikasi Eksternal Default";
        $permissions = json_encode(['read_articles', 'read_settings', 'write_gallery']);

        // 4. Masukkan ke Database
        $sql = "INSERT INTO api_credentials (app_name, api_key, api_secret, permissions, is_active) 
                VALUES (:app_name, :api_key, :api_secret, :permissions, 1)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':app_name'   => $app_name,
            ':api_key'    => $api_key,
            ':api_secret' => $hashed_api_secret,
            ':permissions'=> $permissions
        ]);

        // 5. Tampilkan hasilnya ke layar
        echo "<div style='font-family: sans-serif; max-width: 600px; margin: 40px auto;'>";
        echo "<h1>Kredensial API Berhasil Dibuat!</h1>";
        echo "<div style='background: #f4f4f4; border-left: 4px solid #4CAF50; padding: 15px; margin-bottom: 20px;'>";
        echo "<p><strong>Nama Aplikasi:</strong> " . htmlspecialchars($app_name) . "</p>";
        echo "<p><strong>API KEY (Header: x-api-key):</strong> <br><code style='background: #ddd; padding: 4px 8px; user-select: all; display: inline-block; margin-top: 5px; font-size: 16px;'>" . $api_key . "</code></p>";
        echo "<p><strong>API SECRET (Header: x-api-secret):</strong> <br><code style='background: #ddd; padding: 4px 8px; user-select: all; display: inline-block; margin-top: 5px; font-size: 16px;'>" . $plain_api_secret . "</code></p>";
        echo "</div>";
        
        echo "<h3 style='color: red;'>PERINGATAN PENTING!</h3>";
        echo "<p><strong>Harap copy dan simpan API SECRET di atas sekarang juga!</strong></p>";
        echo "<p>API Secret ini hanya ditampilkan sekali ini saja. Di database, nilai ini sudah di-hash (disamarkan) menggunakan Bcrypt. Anda tidak akan bisa melihat versi aslinya lagi dari database.</p>";
        echo "</div>";

    } catch (PDOException $e) {
        echo "<div style='font-family: sans-serif; max-width: 600px; margin: 40px auto; padding: 20px; border: 1px solid #ff9800; border-radius: 8px; background-color: #fff3e0;'>";
        echo "<h2 style='color: #e65100; margin-top: 0;'>Terjadi Kesalahan Database:</h2>";
        echo "<p>" . $e->getMessage() . "</p>";
        echo "</div>";
    }
?>