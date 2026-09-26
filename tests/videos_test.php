<?php
// Tabel sementara menutupi tabel asli untuk koneksi pengujian ini saja.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!isset($argv[1])) {
    foreach (['add','add_missing','add_url','add_long','update','update_same','update_missing','update_protected',
        'get','get_page','get_search','get_literal','get_invalid','get_empty','delete','delete_missing',
        'sort_up','sort_down','sort_first','sort_last','sort_gap','sort_duplicate','auth','method','options'] as $case) {
        $process = proc_open([PHP_BINARY, '-d', 'xdebug.mode=off', __FILE__, $case], [STDIN, STDOUT, STDERR], $pipes);
        if (!is_resource($process) || proc_close($process) !== 0) exit(1);
    }
    exit;
}
$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
require $root . '/_Helper/Database.php';
$config = require $root . '/_Config/config.php';
$p = Database::getConnection();
preg_match('/CREATE TABLE IF NOT EXISTS `videos` .*?;/s', file_get_contents($root . '/DB/web_sekolah.sql'), $schema);
$p->exec(str_replace('CREATE TABLE IF NOT EXISTS', 'CREATE TEMPORARY TABLE', $schema[0]));
$p->exec("INSERT INTO videos (youtube_id,title,student_name,sort_order) VALUES
    ('dpvPcsMVbWA','Kegiatan 100%_!','Kelas 1',1),('dpvPcsMVbWB','Olahraga','Kelas 2',2),('dpvPcsMVbWC','Literasi','Kelas 3',3)");
$case = $argv[1];
$kind = explode('_', $case)[0];
$files = ['add'=>'add_videos.php','update'=>'update_videos.php','get'=>'get_videos.php','delete'=>'delete_videos.php','sort'=>'update_sort_order.php'];
$file = $files[$kind] ?? 'add_videos.php';
$method = ['add'=>'POST','update'=>'PUT','get'=>'GET','delete'=>'DELETE','sort'=>'PUT'][$kind] ?? 'POST';
$expected = $kind === 'add' ? 201 : 200;
$payload = ['youtube_id'=>'dpvPcsMVbWA','title'=>'Video baru','student_name'=>'Kelas 4'];
$_GET = [];
if ($kind === 'add') {
    if ($case === 'add_missing') { unset($payload['student_name']); $expected = 400; }
    if ($case === 'add_url') { $payload['youtube_id']='https://youtu.be/dpvPcsMVbWA'; $expected=400; }
    if ($case === 'add_long') { $payload['title']=str_repeat('a',256); $expected=400; }
}
if ($kind === 'update') {
    $payload['id']=1;
    if ($case === 'update_same') { $payload['title']='Kegiatan 100%_!'; $payload['student_name']='Kelas 1'; }
    if ($case === 'update_missing') { $payload['id']=99; $expected=404; }
    if ($case === 'update_protected') { $payload['sort_order']=8; $expected=400; }
}
if ($kind === 'get') {
    if ($case === 'get_page') $_GET=['limit'=>'1','page'=>'2'];
    if ($case === 'get_search') $_GET=['keyword'=>'Olahraga'];
    if ($case === 'get_literal') $_GET=['keyword'=>'%_!'];
    if ($case === 'get_invalid') { $_GET=['order_by'=>'image_url']; $expected=400; }
    if ($case === 'get_empty') $p->exec('DELETE FROM videos');
}
if ($kind === 'delete') {
    $_GET=['id'=>$case === 'delete_missing' ? '99' : '1'];
    if ($case === 'delete_missing') $expected=404;
}
if ($kind === 'sort') {
    $payload=['id'=>2,'sort_order'=>'UP'];
    if ($case === 'sort_down') $payload['sort_order']='DOWN';
    if ($case === 'sort_first') { $payload['id']=1; $expected=409; }
    if ($case === 'sort_last') { $payload=['id'=>3,'sort_order'=>'DOWN']; $expected=409; }
    if ($case === 'sort_gap') $p->exec('UPDATE videos SET sort_order=sort_order*10');
    if ($case === 'sort_duplicate') { $p->exec('UPDATE videos SET sort_order=2 WHERE id=3'); $expected=409; }
}
$_SERVER['REQUEST_METHOD']=$method;
$_SERVER['CONTENT_TYPE']='application/json';
$_SERVER['HTTP_AUTHORIZATION']='Bearer ' . Firebase\JWT\JWT::encode(['exp'=>time()+60,'app_name'=>'Test'], $config['jwt_secret_key'], 'HS256');
if ($case === 'auth') { unset($_SERVER['HTTP_AUTHORIZATION']); $expected=401; }
if ($case === 'method') { $_SERVER['REQUEST_METHOD']='GET'; $expected=405; }
if ($case === 'options') { $_SERVER['REQUEST_METHOD']='OPTIONS'; $expected=204; }
$before=$p->query('SELECT * FROM videos ORDER BY id')->fetchAll();
$testBody=json_encode($payload);
ob_start();
register_shutdown_function(function () use ($p,$case,$kind,$expected,$before,$payload) {
    $body=json_decode(ob_get_clean(),true);
    $rows=$p->query('SELECT * FROM videos ORDER BY id')->fetchAll();
    $ok=http_response_code()===$expected && !$p->inTransaction();
    if ($expected >= 400 || $expected === 204) $ok=$ok && $rows===$before;
    elseif ($kind === 'add') $ok=$ok && count($rows)===4 && $rows[3]['youtube_id']===$payload['youtube_id'] && $rows[3]['sort_order']===4;
    elseif ($kind === 'update') $ok=$ok && $rows[0]['title']===$payload['title'] && $rows[0]['student_name']===$payload['student_name'] && $rows[0]['sort_order']===1;
    elseif ($kind === 'delete') $ok=$ok && array_column($rows,'id')===[2,3];
    elseif ($kind === 'sort') $ok=$ok && array_column($rows,'sort_order')===($case === 'sort_down' ? [1,3,2] : ($case === 'sort_gap' ? [20,10,30] : [2,1,3]));
    elseif ($kind === 'get') {
        $ids=array_column($body['data'],'id');
        $wanted=$case === 'get_empty' ? [] : (in_array($case,['get_page','get_search'],true) ? [2] : ($case === 'get_literal' ? [1] : [1,2,3]));
        $ok=$ok && $rows===$before && $ids===$wanted;
    }
    echo ($ok ? 'PASS ' : 'FAIL ') . $case . PHP_EOL;
    if (!$ok) { echo 'HTTP ' . http_response_code() . ', expected ' . $expected . PHP_EOL; exit(1); }
});
$code=file_get_contents($root . '/_API/Videos/' . $file);
$code=str_replace('__DIR__',var_export($root . '/_API/Videos',true),$code);
$code=str_replace("file_get_contents('php://input')",'$testBody',$code);
eval(substr($code,5));
