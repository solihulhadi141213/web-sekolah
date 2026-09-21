<?php
// php tests/update_teachers_test.php: temporary table only.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!isset($argv[1])) {
    foreach (['success', 'unchanged', 'missing', 'invalid_id', 'empty', 'long', 'invalid_json', 'image', 'sort_order', 'short_order', 'auth', 'method', 'options', 'content_type', 'db_failure'] as $case) {
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
preg_match('/CREATE TABLE IF NOT EXISTS `teachers` .*?;/s', $sql, $match);
$p->exec(str_replace('CREATE TABLE IF NOT EXISTS', 'CREATE TEMPORARY TABLE', $match[0]));
$p->exec("INSERT INTO teachers (id, name, role, subject, id_file_manager, sort_order) VALUES (1, 'Old name', 'Old role', 'Old subject', 42, 7)");
$case = $argv[1];
$payload = ['id' => 1, 'name' => ' Lia Mulyanengsih, S.Pd ', 'role' => 'Wali Kelas 1', 'subject' => 'Guru Bahasa Indonesia'];
$expected = 200;
if ($case === 'unchanged') { $payload = ['id' => 1, 'name' => 'Old name', 'role' => 'Old role', 'subject' => 'Old subject']; }
if ($case === 'missing') { $payload['id'] = 99; $expected = 404; }
if ($case === 'invalid_id') { $payload['id'] = '1'; $expected = 400; }
if ($case === 'empty') { $payload['name'] = ' '; $expected = 400; }
if ($case === 'long') { $payload['subject'] = str_repeat('a', 101); $expected = 400; }
if ($case === 'image') { $payload['id_file_manager'] = 99; $expected = 400; }
if ($case === 'sort_order' || $case === 'short_order') { $payload[$case] = 99; $expected = 400; }
if ($case === 'db_failure') { $p->exec("ALTER TABLE teachers ADD CONSTRAINT test_reject CHECK (name = 'Old name')"); $expected = 500; }
$testBody = json_encode($payload);
if ($case === 'invalid_json') { $testBody = '{'; $expected = 400; }
$_SERVER['REQUEST_METHOD'] = 'PUT';
$_SERVER['CONTENT_TYPE'] = 'application/json';
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . Firebase\JWT\JWT::encode(['exp' => time() + 60, 'app_name' => 'Test'], $c['jwt_secret_key'], 'HS256');
if ($case === 'auth') { unset($_SERVER['HTTP_AUTHORIZATION']); $expected = 401; }
if ($case === 'method') { $_SERVER['REQUEST_METHOD'] = 'GET'; $expected = 405; }
if ($case === 'options') { $_SERVER['REQUEST_METHOD'] = 'OPTIONS'; $expected = 204; }
if ($case === 'content_type') { $_SERVER['CONTENT_TYPE'] = 'text/plain'; $expected = 415; }
ob_start();
register_shutdown_function(function () use ($p, $case, $expected, $payload) {
    $body = json_decode(ob_get_clean(), true);
    $row = $p->query('SELECT * FROM teachers WHERE id = 1')->fetch();
    $ok = http_response_code() === $expected && (int) $row['id_file_manager'] === 42 && (int) $row['sort_order'] === 7 && !$p->inTransaction();
    foreach (['name', 'role', 'subject'] as $field) {
        $ok = $ok && $row[$field] === ($expected === 200 ? trim($payload[$field]) : 'Old ' . $field);
        if ($expected === 200) { $ok = $ok && $body['data'][$field] === $row[$field]; }
    }
    echo ($ok ? 'PASS ' : 'FAIL ') . $case . "\n";
    if (!$ok) { exit(1); }
});
$code = file_get_contents($root . '/_API/Teachers/update_teachers.php');
$code = str_replace('__DIR__', var_export($root . '/_API/Teachers', true), $code);
$code = str_replace("file_get_contents('php://input')", '$testBody', $code);
eval(substr($code, 5));
