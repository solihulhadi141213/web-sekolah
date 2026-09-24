<?php
    declare(strict_types=1);

    // Jalankan melalui terminal / GitHub Actions; tidak mengubah data.
    if (PHP_SAPI !== 'cli') {
        http_response_code(403);
        exit('Skrip ini hanya dapat dijalankan melalui CLI.');
    }

    ini_set('display_errors', '0');
    error_reporting(E_ALL);

    /** Membaca koneksi yang disiapkan dalam ci.yml, tanpa fallback produksi. */
    function connectTestDatabase(): mysqli
    {
        $config = [];
        foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'] as $name) {
            $value = getenv($name);
            if ($value === false || ($name !== 'DB_PASSWORD' && trim($value) === '')) {
                throw new RuntimeException("Environment variable $name belum diatur.");
            }
            $config[$name] = $value;
        }

        if (!ctype_digit($config['DB_PORT']) || (int) $config['DB_PORT'] < 1
            || (int) $config['DB_PORT'] > 65535) {
            throw new RuntimeException('DB_PORT harus angka antara 1 dan 65535.');
        }
        if (!extension_loaded('mysqli')) {
            throw new RuntimeException('Ekstensi mysqli belum tersedia.');
        }

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $db = mysqli_init();
        $db->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);
        $db->real_connect(
            $config['DB_HOST'], $config['DB_USER'], $config['DB_PASSWORD'],
            $config['DB_NAME'], (int) $config['DB_PORT']
        );
        $db->set_charset('utf8mb4');
        return $db;
    }

    try {
        $db = connectTestDatabase();
        // Periksa kelengkapan record di SQL tanpa mengambil nilai key/secret ke log.
        $result = $db->query("SELECT
            COUNT(*) AS active_count,
            COALESCE(SUM(CASE
                WHEN app_name IS NULL OR CHAR_LENGTH(TRIM(app_name)) = 0
                OR api_key IS NULL OR CHAR_LENGTH(TRIM(api_key)) = 0
                OR api_secret IS NULL OR CHAR_LENGTH(TRIM(api_secret)) = 0
                THEN 1 ELSE 0 END), 0) AS incomplete_count
            FROM api_credentials WHERE is_active = 1");
        $row = $result->fetch_assoc();
        $active = (int) $row['active_count'];
        $incomplete = (int) $row['incomplete_count'];
        $db->close();

        if ($active === 0) {
            throw new RuntimeException('Tidak ada record api_credentials dengan is_active = 1.');
        }
        if ($incomplete > 0) {
            throw new RuntimeException("Ada $incomplete record API aktif dengan app_name, api_key, atau api_secret kosong.");
        }

        echo "LOLOS: Ditemukan $active record kredensial API aktif dan terisi.\n";
        // Tes ini tidak memanggil endpoint API atau membuktikan autentikasi berhasil.
        exit(0);
    } catch (Throwable $error) {
        // Jangan cetak pesan SQL mentah: dapat memuat konfigurasi atau data sensitif.
        if ($error instanceof mysqli_sql_exception) {
            fwrite(STDERR, 'GAGAL: Koneksi/query MySQL bermasalah. Kode: '
                . $error->getCode() . '. Periksa koneksi, izin, dan struktur tabel.' . PHP_EOL);
        } elseif ($error instanceof RuntimeException) {
            fwrite(STDERR, 'GAGAL: ' . $error->getMessage() . PHP_EOL);
        } else {
            fwrite(STDERR, 'GAGAL: Kesalahan internal pada skrip pengujian.' . PHP_EOL);
        }
        exit(1);
    }
