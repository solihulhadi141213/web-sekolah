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
if (!in_array($orderBy, ['id', 'youtube_id', 'title', 'student_name', 'sort_order'], true)) {
    $respond(400, [
        'status' => 'error',
        'message' => 'Order_by harus salah satu dari: id, youtube_id, title, student_name, sort_order.'
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
    // Cari sebagian teks di empat kolom; %, _, dan ! diperlakukan literal.
    $search = '%' . strtr($keyword, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
    $where = " WHERE (
        CAST(t.`id` AS CHAR) LIKE :keyword_id ESCAPE '!'
        OR t.`youtube_id` LIKE :keyword_youtube_id ESCAPE '!'
        OR t.`title` LIKE :keyword_title ESCAPE '!'
        OR t.`student_name` LIKE :keyword_student_name ESCAPE '!'
    )";
    $bindings = [
        ':keyword_id' => $search,
        ':keyword_youtube_id' => $search,
        ':keyword_title' => $search,
        ':keyword_student_name' => $search
    ];
}

try {
    $pdo = Database::getConnection();

    // Total hanya menghitung data yang sesuai pencarian.
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM `videos` t' . $where);
    $countStmt->execute($bindings);
    $total = (int) $countStmt->fetchColumn();

    // ID menjadi penvideotan tambahan agar urutan nilai yang sama konsisten.
    $orderSql = 't.`' . $orderBy . '` ' . $shortBy;
    if ($orderBy !== 'id') {
        $orderSql .= ', t.`id` ' . $shortBy;
    }

    $stmt = $pdo->prepare(
        'SELECT t.`id`, t.`youtube_id`, t.`title`, t.`student_name`, t.`sort_order`
         FROM `videos` t' . $where . '
         ORDER BY ' . $orderSql . ' LIMIT :limit OFFSET :offset'
    );
    foreach ($bindings as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $videos = $stmt->fetchAll();

    foreach ($videos as &$video) {
        $video['id'] = (int) $video['id'];
        $video['sort_order'] = $video['sort_order'] === null ? null : (int) $video['sort_order'];
    }
    unset($video);

    $respond(200, [
        'status' => 'success',
        'message' => $videos ? 'Data video berhasil ditampilkan.' : 'Data video tidak ditemukan.',
        'requested_by_app' => $userData['app_name'] ?? 'Unknown',
        'data' => $videos,
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
