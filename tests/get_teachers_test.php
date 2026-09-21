<?php
// php tests/get_teachers_test.php: temporary tables only, no application rows changed.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!isset($argv[1])) {
    foreach (['all', 'root', 'page', 'search', 'literal', 'descending', 'empty', 'beyond', 'invalid_order', 'invalid_limit', 'array', 'auth', 'method', 'options'] as $case) {
        $process = proc_open([PHP_BINARY, '-d', 'xdebug.mode=off', __FILE__, $case], [STDIN, STDOUT, STDERR], $pipes);
        if (!is_resource($process) || proc_close($process) !== 0) { exit(1); }
    }
    exit;
}
$root = dirname(__DIR__);
require $root . '/_Helper/Database.php';
require $root . '/vendor/autoload.php';
$c = require $root . '/_Config/config.php';
$p = Database::getConnection();
$sql = file_get_contents($root . '/DB/web_sekolah.sql');
foreach (['teachers', 'file_manager'] as $table) {
    preg_match('/CREATE TABLE IF NOT EXISTS `' . $table . '` .*?;/s', $sql, $match);
    $p->exec(str_replace('CREATE TABLE IF NOT EXISTS', 'CREATE TEMPORARY TABLE', $match[0]));
}
$fileInsert = $p->prepare('INSERT INTO file_manager (file_source, file_metadata, creat_at) VALUES (?, ?, UTC_TIMESTAMP())');
$teacherInsert = $p->prepare('INSERT INTO teachers (name, role, subject, id_file_manager, sort_order) VALUES (?, ?, ?, ?, ?)');
$teacherInsert->execute(['No image', 'Guru', null, null, 1]);
foreach ([
    ['Local Directory', ['file_name' => 'photo 1.png', 'file_size' => 1, 'file_mime_type' => 'image/png', 'file_extension' => '.png', 'file_upload_at' => '2026-09-20T03:49:15.123Z']],
    ['External Link', ['file_url' => 'https://example.com/external.png', 'file_type' => 'Image']],
    ['Cloudinary', ['url' => 'https://example.com/cloud.png', 'public_id' => 'teachers/test', 'format' => 'png', 'resource_type' => 'Image', 'file_upload_at' => '2026-09-20T03:49:15.123Z']],
    ['Imagekit', ['url' => 'https://example.com/imagekit.png', 'file_id' => 'test-id', 'format' => 'PNG', 'resource_type' => 'Image', 'file_upload_at' => '2026-09-20T03:49:15.123Z']],
] as $i => [$source, $metadata]) {
    $fileInsert->execute([$source, json_encode($metadata)]);
    $teacherInsert->execute([$source, 'Guru', '100%_!', $p->lastInsertId(), $i + 2]);
}
$case = $argv[1];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = $case === 'root' ? '/_API/Teachers/get_teachers.php' : '/web-sekolah/_API/Teachers/get_teachers.php';
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . Firebase\JWT\JWT::encode(['exp' => time() + 60, 'app_name' => 'Test'], $c['jwt_secret_key'], 'HS256');
$_GET = [];
$expected = 200;
if ($case === 'page') { $_GET = ['limit' => '2', 'page' => '2']; }
if ($case === 'search') { $_GET = ['keyword' => 'Imagekit']; }
if ($case === 'literal') { $_GET = ['keyword' => '%_!']; }
if ($case === 'descending') { $_GET = ['order_by' => 'id', 'short_by' => 'DESC']; }
if ($case === 'empty') { $p->exec('DELETE FROM teachers'); }
if ($case === 'beyond') { $_GET = ['page' => '99']; }
if ($case === 'invalid_order') { $_GET = ['order_by' => 'id; DROP TABLE teachers']; $expected = 400; }
if ($case === 'invalid_limit') { $_GET = ['limit' => '101']; $expected = 400; }
if ($case === 'array') { $_GET = ['keyword' => []]; $expected = 400; }
if ($case === 'auth') { unset($_SERVER['HTTP_AUTHORIZATION']); $expected = 401; }
if ($case === 'method') { $_SERVER['REQUEST_METHOD'] = 'POST'; $expected = 405; }
if ($case === 'options') { $_SERVER['REQUEST_METHOD'] = 'OPTIONS'; $expected = 204; }
ob_start();
register_shutdown_function(function () use ($case, $expected) {
    $body = json_decode(ob_get_clean(), true);
    $ok = http_response_code() === $expected;
    if ($expected === 200) {
        $rows = $body['data'];
        $total = $body['pagination']['total'];
        if ($case === 'all' || $case === 'root') {
            $ok = $ok && count($rows) === 5 && $total === 5 && $rows[0]['image_url'] === null && $rows[0]['id_file_manager'] === null && $rows[0]['file_metadata'] === null;
            $prefix = $case === 'root' ? '' : '/web-sekolah';
            $ok = $ok && $rows[1]['image_url'] === $prefix . '/assets/img/local_directory/photo%201.png';
            foreach ([2 => 'external', 3 => 'cloud', 4 => 'imagekit'] as $i => $name) {
                $ok = $ok && $rows[$i]['image_url'] === 'https://example.com/' . $name . '.png' && is_array($rows[$i]['file_metadata']) && is_int($rows[$i]['id_file_manager']);
            }
        }
        if ($case === 'page') { $ok = $ok && array_column($rows, 'id') === [3, 4] && $total === 5 && $body['pagination']['total_pages'] === 3; }
        if ($case === 'search') { $ok = $ok && count($rows) === 1 && $total === 1 && $rows[0]['file_source'] === 'Imagekit'; }
        if ($case === 'literal') { $ok = $ok && count($rows) === 4 && $total === 4; }
        if ($case === 'descending') { $ok = $ok && array_column($rows, 'id') === [5, 4, 3, 2, 1]; }
        if ($case === 'empty') { $ok = $ok && $rows === [] && $total === 0; }
        if ($case === 'beyond') { $ok = $ok && $rows === [] && $total === 5; }
    }
    echo ($ok ? 'PASS ' : 'FAIL ') . $case . "\n";
    if (!$ok) { exit(1); }
});
require $root . '/_API/Teachers/get_teachers.php';
