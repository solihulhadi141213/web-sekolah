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

/** Abaikan display width integer, tetapi tetap periksa unsigned dan panjang varchar. */
function normalizeType(string $type): string
{
    return preg_replace('/\b(tinyint|smallint|mediumint|int|bigint)\(\d+\)/i', '$1', strtolower($type));
}

try {
    $db = connectTestDatabase();
    $db->query('SELECT 1');
    echo "LOLOS: Koneksi database berhasil.\n";

    // Kontrak struktur dari SQL yang diberikan. Perbarui jika desain berubah sengaja.
    // Tabel/kolom tambahan diperbolehkan; bagian wajib berikut harus tetap sesuai.
    $expected = json_decode(<<<'JSON'
{
  "api_credentials": {
    "columns": {
      "id": {"type": "int unsigned", "nullable": "NO", "auto_increment": true},
      "app_name": {"type": "varchar(100)", "nullable": "NO", "auto_increment": false},
      "api_key": {"type": "varchar(64)", "nullable": "NO", "auto_increment": false},
      "api_secret": {"type": "varchar(255)", "nullable": "NO", "auto_increment": false},
      "permissions": {"type": "json", "nullable": "YES", "auto_increment": false},
      "is_active": {"type": "tinyint(1)", "nullable": "YES", "auto_increment": false},
      "last_used_at": {"type": "timestamp", "nullable": "YES", "auto_increment": false},
      "created_at": {"type": "timestamp", "nullable": "YES", "auto_increment": false}
    },
    "unique_keys": [["id"], ["api_key"]]
  },
  "articles": {
    "columns": {
      "id": {"type": "int unsigned", "nullable": "NO", "auto_increment": true},
      "title": {"type": "varchar(255)", "nullable": "NO", "auto_increment": false},
      "slug": {"type": "varchar(255)", "nullable": "NO", "auto_increment": false},
      "category_tag": {"type": "varchar(50)", "nullable": "NO", "auto_increment": false},
      "summary": {"type": "varchar(500)", "nullable": "NO", "auto_increment": false},
      "content": {"type": "longtext", "nullable": "NO", "auto_increment": false},
      "image_url": {"type": "varchar(255)", "nullable": "YES", "auto_increment": false},
      "status": {"type": "enum('published','draft')", "nullable": "YES", "auto_increment": false},
      "created_at": {"type": "timestamp", "nullable": "YES", "auto_increment": false},
      "updated_at": {"type": "timestamp", "nullable": "YES", "auto_increment": false}
    },
    "unique_keys": [["id"], ["slug"]]
  },
  "article_contents": {
    "columns": {
      "id": {"type": "int unsigned", "nullable": "NO", "auto_increment": true},
      "article_id": {"type": "int unsigned", "nullable": "NO", "auto_increment": false},
      "block_type": {"type": "varchar(50)", "nullable": "NO", "auto_increment": false},
      "content_data": {"type": "json", "nullable": "NO", "auto_increment": false},
      "sort_order": {"type": "int unsigned", "nullable": "YES", "auto_increment": false}
    },
    "unique_keys": [["id"]]
  },
  "article_tag_map": {
    "columns": {
      "article_id": {"type": "int unsigned", "nullable": "NO", "auto_increment": false},
      "tag_id": {"type": "int unsigned", "nullable": "NO", "auto_increment": false}
    },
    "unique_keys": [["article_id", "tag_id"]]
  },
  "facilities": {
    "columns": {
      "id": {"type": "int", "nullable": "NO", "auto_increment": true},
      "title": {"type": "varchar(100)", "nullable": "NO", "auto_increment": false},
      "description": {"type": "varchar(255)", "nullable": "NO", "auto_increment": false},
      "image_url": {"type": "varchar(255)", "nullable": "NO", "auto_increment": false},
      "sort_order": {"type": "int", "nullable": "YES", "auto_increment": false}
    },
    "unique_keys": [["id"]]
  },
  "faqs": {
    "columns": {
      "id": {"type": "int", "nullable": "NO", "auto_increment": true},
      "question": {"type": "varchar(255)", "nullable": "NO", "auto_increment": false},
      "answer": {"type": "text", "nullable": "NO", "auto_increment": false},
      "sort_order": {"type": "int", "nullable": "YES", "auto_increment": false}
    },
    "unique_keys": [["id"]]
  },
  "galleries": {
    "columns": {
      "id": {"type": "int", "nullable": "NO", "auto_increment": true},
      "image_url": {"type": "varchar(255)", "nullable": "NO", "auto_increment": false},
      "caption": {"type": "varchar(255)", "nullable": "YES", "auto_increment": false},
      "sort_order": {"type": "int", "nullable": "YES", "auto_increment": false}
    },
    "unique_keys": [["id"]]
  },
  "hero_slides": {
    "columns": {
      "id": {"type": "int", "nullable": "NO", "auto_increment": true},
      "title": {"type": "varchar(100)", "nullable": "NO", "auto_increment": false},
      "subtitle": {"type": "varchar(255)", "nullable": "NO", "auto_increment": false},
      "image_url": {"type": "varchar(255)", "nullable": "NO", "auto_increment": false},
      "sort_order": {"type": "int", "nullable": "YES", "auto_increment": false},
      "is_active": {"type": "tinyint(1)", "nullable": "YES", "auto_increment": false}
    },
    "unique_keys": [["id"]]
  },
  "student_activities": {
    "columns": {
      "id": {"type": "int", "nullable": "NO", "auto_increment": true},
      "category": {"type": "enum('organisasi','ekstrakurikuler','prestasi')", "nullable": "NO", "auto_increment": false},
      "title": {"type": "varchar(100)", "nullable": "NO", "auto_increment": false},
      "description": {"type": "text", "nullable": "NO", "auto_increment": false},
      "sort_order": {"type": "int", "nullable": "YES", "auto_increment": false}
    },
    "unique_keys": [["id"]]
  },
  "tags": {
    "columns": {
      "id": {"type": "int unsigned", "nullable": "NO", "auto_increment": true},
      "name": {"type": "varchar(50)", "nullable": "NO", "auto_increment": false},
      "slug": {"type": "varchar(50)", "nullable": "NO", "auto_increment": false}
    },
    "unique_keys": [["id"], ["name"], ["slug"]]
  },
  "teachers": {
    "columns": {
      "id": {"type": "int", "nullable": "NO", "auto_increment": true},
      "name": {"type": "varchar(100)", "nullable": "NO", "auto_increment": false},
      "role": {"type": "varchar(100)", "nullable": "NO", "auto_increment": false},
      "subject": {"type": "varchar(100)", "nullable": "YES", "auto_increment": false},
      "image_url": {"type": "varchar(255)", "nullable": "NO", "auto_increment": false},
      "sort_order": {"type": "int", "nullable": "YES", "auto_increment": false}
    },
    "unique_keys": [["id"]]
  },
  "testimonials": {
    "columns": {
      "id": {"type": "int", "nullable": "NO", "auto_increment": true},
      "parent_name": {"type": "varchar(100)", "nullable": "NO", "auto_increment": false},
      "quote": {"type": "text", "nullable": "NO", "auto_increment": false},
      "image_url": {"type": "varchar(255)", "nullable": "YES", "auto_increment": false},
      "sort_order": {"type": "int", "nullable": "YES", "auto_increment": false}
    },
    "unique_keys": [["id"]]
  },
  "videos": {
    "columns": {
      "id": {"type": "int", "nullable": "NO", "auto_increment": true},
      "youtube_id": {"type": "varchar(50)", "nullable": "NO", "auto_increment": false},
      "title": {"type": "varchar(255)", "nullable": "NO", "auto_increment": false},
      "student_name": {"type": "varchar(100)", "nullable": "NO", "auto_increment": false},
      "sort_order": {"type": "int", "nullable": "YES", "auto_increment": false}
    },
    "unique_keys": [["id"]]
  },
  "web_settings": {
    "columns": {
      "id": {"type": "int unsigned", "nullable": "NO", "auto_increment": true},
      "setting_key": {"type": "varchar(50)", "nullable": "NO", "auto_increment": false},
      "setting_value": {"type": "text", "nullable": "YES", "auto_increment": false},
      "description": {"type": "varchar(255)", "nullable": "YES", "auto_increment": false},
      "updated_at": {"type": "timestamp", "nullable": "YES", "auto_increment": false}
    },
    "unique_keys": [["id"], ["setting_key"]]
  }
}
JSON
    , true, 512, JSON_THROW_ON_ERROR);

    $errors = [];
    $tables = [];
    $result = $db->query("SELECT TABLE_NAME, TABLE_TYPE, ENGINE
        FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()");
    while ($row = $result->fetch_assoc()) {
        $tables[$row['TABLE_NAME']] = $row;
    }

    $columns = [];
    $result = $db->query("SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, EXTRA
        FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()");
    while ($row = $result->fetch_assoc()) {
        $columns[$row['TABLE_NAME']][$row['COLUMN_NAME']] = $row;
    }

    $keys = [];
    $result = $db->query("SELECT TABLE_NAME, INDEX_NAME, COLUMN_NAME, SUB_PART
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND NON_UNIQUE = 0
        ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX");
    while ($row = $result->fetch_assoc()) {
        $keys[$row['TABLE_NAME']][$row['INDEX_NAME']][] =
            $row['SUB_PART'] === null ? $row['COLUMN_NAME'] : '__prefix_index__';
    }

    foreach ($expected as $table => $spec) {
        if (!isset($tables[$table])) {
            $errors[] = "Tabel $table tidak ditemukan.";
            continue;
        }
        if ($tables[$table]['TABLE_TYPE'] !== 'BASE TABLE'
            || strcasecmp((string) $tables[$table]['ENGINE'], 'InnoDB') !== 0) {
            $errors[] = "$table harus berupa tabel InnoDB.";
        }
        foreach ($spec['columns'] as $name => $wanted) {
            $actual = $columns[$table][$name] ?? null;
            if ($actual === null) {
                $errors[] = "Kolom $table.$name tidak ditemukan.";
                continue;
            }
            if (normalizeType($actual['COLUMN_TYPE']) !== normalizeType($wanted['type'])) {
                $errors[] = "Tipe $table.$name harus {$wanted['type']}.";
            }
            if ($actual['IS_NULLABLE'] !== $wanted['nullable']) {
                $errors[] = "Aturan NULL $table.$name harus {$wanted['nullable']}.";
            }
            if ((strpos($actual['EXTRA'], 'auto_increment') !== false) !== $wanted['auto_increment']) {
                $errors[] = "Aturan AUTO_INCREMENT $table.$name tidak sesuai.";
            }
        }
        foreach ($spec['unique_keys'] as $index => $wantedColumns) {
            // Primary key adalah entri pertama dalam kontrak.
            if ($index === 0) {
                if (($keys[$table]['PRIMARY'] ?? []) !== $wantedColumns) {
                    $errors[] = "Primary key $table tidak sesuai.";
                }
            } elseif (!in_array($wantedColumns, array_values($keys[$table] ?? []), true)) {
                $errors[] = "Unique index $table (" . implode(', ', $wantedColumns) . ') tidak ditemukan.';
            }
        }
    }

    // Periksa relasi beserta ON DELETE CASCADE, tanpa bergantung pada nama constraint.
    $relations = [];
    $result = $db->query("SELECT k.TABLE_NAME, k.COLUMN_NAME, k.REFERENCED_TABLE_NAME,
            k.REFERENCED_COLUMN_NAME, r.DELETE_RULE
        FROM information_schema.KEY_COLUMN_USAGE k
        JOIN information_schema.REFERENTIAL_CONSTRAINTS r
          ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
         AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND r.TABLE_NAME = k.TABLE_NAME
        WHERE k.TABLE_SCHEMA = DATABASE() AND k.REFERENCED_TABLE_SCHEMA = DATABASE()");
    while ($row = $result->fetch_assoc()) {
        $relations[] = implode('|', array_values($row));
    }
    foreach ([
        'article_contents|article_id|articles|id|CASCADE',
        'article_tag_map|article_id|articles|id|CASCADE',
        'article_tag_map|tag_id|tags|id|CASCADE',
    ] as $relation) {
        if (!in_array($relation, $relations, true)) {
            $errors[] = "Foreign key tidak sesuai: $relation";
        }
    }
    $db->close();
    if ($errors !== []) {
        foreach ($errors as $message) {
            fwrite(STDERR, "GAGAL: $message\n");
        }
        exit(1);
    }
    echo "LOLOS: Struktur 14 tabel, kolom wajib, primary/unique key, dan 3 relasi sesuai.\n";
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
