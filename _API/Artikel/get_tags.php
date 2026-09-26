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
    'order_by' => 'name',
    'short_by' => 'ASC',
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
if (!in_array($orderBy, ['id', 'name', 'slug'], true)) {
    $respond(400, [
        'status' => 'error',
        'message' => 'Order_by harus salah satu dari: id, name, slug.'
    ]);
}
if (!in_array($shortBy, ['ASC', 'DESC'], true)) {
    $respond(400, [
        'status' => 'error',
        'message' => 'Short_by harus ASC atau DESC.'
    ]);
}

$offset = ($page - 1) * $limit;
$where = '';
$bindings = [];

if ($keyword !== '') {
    // Cari sebagian teks di tiga kolom; %, _, dan ! diperlakukan literal.
    $search = '%' . strtr($keyword, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
    $where = " WHERE (
        CAST(t.`id` AS CHAR) LIKE :keyword_id ESCAPE '!'
        OR t.`name` LIKE :keyword_name ESCAPE '!'
        OR t.`slug` LIKE :keyword_slug ESCAPE '!'
    )";
    $bindings = [
        ':keyword_id' => $search,
        ':keyword_name' => $search,
        ':keyword_slug' => $search,
    ];
}

try {
    $pdo = Database::getConnection();

    // Total hanya menghitung data yang sesuai pencarian.
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM `tags` t' . $where);
    $countStmt->execute($bindings);
    $total = (int) $countStmt->fetchColumn();

    // ID menjadi pengurutan tambahan agar urutan nilai yang sama konsisten.
    $orderSql = 't.`' . $orderBy . '` ' . $shortBy;
    if ($orderBy !== 'id') {
        $orderSql .= ', t.`id` ' . $shortBy;
    }

    $stmt = $pdo->prepare(
        'SELECT t.`id`, t.`name`, t.`slug`
         FROM `tags` t' . $where . '
         ORDER BY ' . $orderSql . ' LIMIT :limit OFFSET :offset'
    );
    foreach ($bindings as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $tags = $stmt->fetchAll();

    foreach ($tags as &$tag) {
        $tag['id'] = (int) $tag['id'];
    }
    unset($tag);

    $respond(200, [
        'status' => 'success',
        'message' => $tags ? 'Data tag berhasil ditampilkan.' : 'Data tag tidak ditemukan.',
        'requested_by_app' => $userData['app_name'] ?? 'Unknown',
        'data' => $tags,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => (int) ceil($total / $limit)
        ],
        'filter' => [
            'order_by' => $orderBy,
            'short_by' => $shortBy,
            'keyword' => $keyword
        ]
    ]);
} catch (PDOException $e) {
    $respond(500, [
        'status' => 'error',
        'message' => 'Terjadi kesalahan pada server database.'
    ]);
}
