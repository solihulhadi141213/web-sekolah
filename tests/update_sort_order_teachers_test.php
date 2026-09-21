<?php
// php tests/update_sort_order_teachers_test.php. Uses temporary MySQL table only.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!isset($argv[1])) {
    foreach (['up', 'down', 'first', 'last', 'single', 'missing', 'gap', 'duplicate', 'invalid_order', 'invalid_id', 'invalid_json', 'rollback', 'auth', 'method', 'options'] as $case) {
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
$p->exec("INSERT INTO teachers (id,name,role,subject,id_file_manager,sort_order) VALUES (1,'A','Role','Subject',42,1),(2,'B','Role','Subject',43,2),(3,'C','Role','Subject',44,3)");
$case = $argv[1];
$payload = ['id' => 2, 'sort_order' => 'UP'];
$expected = 200;
$expectedOrders = [1 => 2, 2 => 1, 3 => 3];
if ($case === 'down') { $payload['sort_order'] = 'DOWN'; $expectedOrders = [1=>1,2=>3,3=>2]; }
if ($case === 'first') { $payload['id'] = 1; $expected = 409; }
if ($case === 'last') { $payload = ['id'=>3,'sort_order'=>'DOWN']; $expected = 409; }
if ($case === 'single') { $p->exec('DELETE FROM teachers WHERE id <> 1'); $payload = ['id'=>1,'sort_order'=>'DOWN']; $expected = 409; }
if ($case === 'missing') { $payload['id'] = 999; $expected = 404; }
if ($case === 'gap') { $p->exec('UPDATE teachers SET sort_order=sort_order*10'); $expectedOrders = [1=>20,2=>10,3=>30]; }
if ($case === 'duplicate') { $p->exec('UPDATE teachers SET sort_order=2 WHERE id=3'); $expected = 409; }
if ($case === 'invalid_order') { $payload['sort_order'] = 'LEFT'; $expected = 400; }
if ($case === 'invalid_id') { $payload['id'] = '2'; $expected = 400; }
if ($case === 'rollback') { $p->exec("ALTER TABLE teachers ADD CONSTRAINT reject_swap CHECK (name <> 'A' OR sort_order = 1)"); $expected = 500; }
$before = $p->query('SELECT * FROM teachers ORDER BY id')->fetchAll();
$testBody = json_encode($payload);
if ($case === 'invalid_json') { $testBody = '{'; $expected = 400; }
$_SERVER['REQUEST_METHOD'] = 'PUT';
$_SERVER['CONTENT_TYPE'] = 'application/json';
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . Firebase\JWT\JWT::encode(['exp'=>time()+60,'app_name'=>'Test'], $c['jwt_secret_key'], 'HS256');
if ($case === 'auth') { unset($_SERVER['HTTP_AUTHORIZATION']); $expected = 401; }
if ($case === 'method') { $_SERVER['REQUEST_METHOD'] = 'GET'; $expected = 405; }
if ($case === 'options') { $_SERVER['REQUEST_METHOD'] = 'OPTIONS'; $expected = 204; }
ob_start();
register_shutdown_function(function () use ($p,$case,$expected,$before,$expectedOrders,$payload) {
    $body = json_decode(ob_get_clean(),true);
    $after = $p->query('SELECT * FROM teachers ORDER BY id')->fetchAll();
    $ok = http_response_code() === $expected && !$p->inTransaction();
    if ($expected === 200) {
        foreach ($after as $i => $row) {
            $ok = $ok && (int)$row['sort_order'] === $expectedOrders[(int)$row['id']];
            unset($row['sort_order'], $before[$i]['sort_order']);
            $ok = $ok && $row === $before[$i];
        }
        $ok = $ok && $body['data']['sort_order'] === $expectedOrders[$payload['id']] && $body['data']['swapped_id'] === ($case === 'down' ? 3 : 1);
    } else { $ok = $ok && $before === $after; }
    echo ($ok ? 'PASS ' : 'FAIL ') . $case . "\n";
    if (!$ok) { exit(1); }
});
$code = file_get_contents($root . '/_API/Teachers/update_sort_order_teachers.php');
$code = str_replace('__DIR__',var_export($root . '/_API/Teachers',true),$code);
$code = str_replace("file_get_contents('php://input')",'$testBody',$code);
eval(substr($code,5));
