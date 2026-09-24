<?php

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Cache-Control: no-store');

$respond = static function ($code, array $body) {
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET, OPTIONS');
    $respond(405, [
        'status' => 'error',
        'message' => 'Method tidak diizinkan. Gunakan GET.'
    ]);
}

$config = require __DIR__ . '/../../_Config/config.php';
require_once __DIR__ . '/../../_Helper/GlobalFunction.php';
require_once __DIR__ . '/../../_Helper/Database.php';
$userData = validateJWT($config);

// Nama short_by mengikuti parameter API yang diminta.
$params = [
    'limit' => '12',
    'page' => '1',
    'order_by' => 'sort_order',
    'short_by' => 'ASC',
    'is_active' => '',
    'keyword' => ''
];

foreach ($params as $key => $default) {
    $value = $_GET[$key] ?? $default;
    if (!is_string($value)) {
        $respond(400, [
            'status' => 'error',
            'message' => 'Parameter ' . $key . ' tidak boleh berupa array.'
        ]);
    }
    $params[$key] = trim($value);
}

$limit = filter_var($params['limit'], FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 100]
]);
if ($limit === false) {
    $respond(400, [
        'status' => 'error',
        'message' => 'Limit harus berupa bilangan bulat antara 1 sampai 100.'
    ]);
}

$page = filter_var($params['page'], FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => intdiv(PHP_INT_MAX, $limit)]
]);
if ($page === false) {
    $respond(400, [
        'status' => 'error',
        'message' => 'Page harus berupa bilangan bulat positif dalam rentang yang didukung server.'
    ]);
}

$orderBy = $params['order_by'];
$shortBy = strtoupper($params['short_by']);
$keyword = $params['keyword'];

// Identifier dan arah ORDER BY hanya boleh berasal dari whitelist ini.
if (!in_array($orderBy, ['id', 'title', 'subtitle', 'sort_order', 'is_active'], true)) {
    $respond(400, [
        'status' => 'error',
        'message' => 'Order_by harus salah satu dari: id, title, subtitle, sort_order, is_active.'
    ]);
}
if (!in_array($shortBy, ['ASC', 'DESC'], true)) {
    $respond(400, [
        'status' => 'error',
        'message' => 'Short_by harus ASC atau DESC.'
    ]);
}

if (!in_array($params['is_active'], ['', '0', '1'], true)) {
    $respond(400, ['status' => 'error', 'message' => 'Is_active harus 0 atau 1.']);
}

$offset = ($page - 1) * $limit;
$where = '';
$bindings = [];

if ($keyword !== '') {
    // Cari sebagian teks di tiga kolom; %, _, dan ! diperlakukan literal.
    $search = '%' . strtr($keyword, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
    $where = " WHERE (
        CAST(t.`id` AS CHAR) LIKE :keyword_id ESCAPE '!'
        OR t.`title` LIKE :keyword_title ESCAPE '!'
        OR t.`subtitle` LIKE :keyword_subtitle ESCAPE '!'
    )";
    $bindings = [
        ':keyword_id' => $search,
        ':keyword_title' => $search,
        ':keyword_subtitle' => $search
    ];
}

if ($params['is_active'] !== '') {
    $where .= ($where === '' ? ' WHERE ' : ' AND ') . 't.is_active = :is_active';
    $bindings[':is_active'] = $params['is_active'];
}

try {
    $pdo = Database::getConnection();

    // Total hanya menghitung data yang sesuai pencarian.
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM `hero_slides` t' . $where);
    $countStmt->execute($bindings);
    $total = (int) $countStmt->fetchColumn();

    // ID menjadi pengurutan tambahan agar urutan nilai yang sama konsisten.
    $orderSql = 't.`' . $orderBy . '` ' . $shortBy;
    if ($orderBy !== 'id') {
        $orderSql .= ', t.`id` ' . $shortBy;
    }

    $stmt = $pdo->prepare(
        'SELECT t.`id`, t.`title`, t.`subtitle`, t.`id_file_manager`, t.`sort_order`, t.`is_active`, f.`file_source`, f.`file_metadata`
         FROM `hero_slides` t LEFT JOIN `file_manager` f ON f.`id_file_manager` = t.`id_file_manager`' . $where . '
         ORDER BY ' . $orderSql . ' LIMIT :limit OFFSET :offset'
    );
    foreach ($bindings as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $hero_slides = $stmt->fetchAll();

    // Path relatif root mengikuti domain saat ini, bukan hostname pada base_url konfigurasi.
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/_API/Hero/get_hero.php');
    $basePath = rtrim(str_replace('\\', '/', dirname($scriptName, 3)), '/.');

    foreach ($hero_slides as &$hero) {
        $hero['is_active'] = $hero['is_active'] === null ? null : (int) $hero['is_active'];
        $hero['id'] = (int) $hero['id'];
        $hero['id_file_manager'] = $hero['id_file_manager'] === null
            ? null : (int) $hero['id_file_manager'];
        $hero['sort_order'] = $hero['sort_order'] === null
            ? null : (int) $hero['sort_order'];

        // Kembalikan metadata sebagai objek JSON, bukan string JSON berlapis.
        $metadata = $hero['file_metadata'] === null ? null : json_decode($hero['file_metadata']);
        $hero['file_metadata'] = is_object($metadata) ? $metadata : null;
        $hero['image_url'] = null;
        if (!is_object($metadata)) {
            continue;
        }

        if ($hero['file_source'] === 'Local Directory') {
            $fileName = $metadata->file_name ?? null;
            if (is_string($fileName) && $fileName !== '' && $fileName !== '.' && $fileName !== '..'
                && strpbrk($fileName, "/\\\0") === false) {
                $hero['image_url'] = $basePath . '/assets/img/local_directory/' . rawurlencode($fileName);
            }
        } else {
            $url = null;
            if ($hero['file_source'] === 'External Link') {
                $url = $metadata->file_url ?? null;
            } elseif (in_array($hero['file_source'], ['Cloudinary', 'Imagekit'], true)) {
                $url = $metadata->url ?? null;
            }
            if (is_string($url) && filter_var($url, FILTER_VALIDATE_URL)
                && in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true)) {
                $hero['image_url'] = $url;
            }
        }
    }
    unset($hero);

    $respond(200, [
        'status' => 'success',
        'message' => $hero_slides ? 'Data hero berhasil ditampilkan.' : 'Data hero tidak ditemukan.',
        'requested_by_app' => $userData['app_name'] ?? 'Unknown',
        'data' => $hero_slides,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => (int) ceil($total / $limit)
        ],
        'filter' => [
            'order_by' => $orderBy,
            'short_by' => $shortBy,
            'is_active' => $params['is_active'] === '' ? null : (int) $params['is_active'],
            'keyword' => $keyword
        ]
    ]);
} catch (PDOException $e) {
    $respond(500, [
        'status' => 'error',
        'message' => 'Terjadi kesalahan pada server database.'
    ]);
}
