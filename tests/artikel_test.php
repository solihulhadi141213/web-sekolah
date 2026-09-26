<?php
// Hanya tabel sementara dan file milik tes; tidak mengubah konten asli.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!isset($argv[1])) {
    $cases = ['add_none','add_tags','add_reuse','add_conflict','add_bad_tag','add_slug_duplicate','add_local','add_rollback','add_invalid',
        'update','update_tags','update_clear','update_missing','update_duplicate','update_rollback',
        'get','get_page','get_status','get_literal','get_invalid','detail','detail_slug','detail_missing',
        'bytags','bytags_slug','bytags_missing','tags','delete','delete_contents','delete_shared',
        'image','image_delete','auth','method','options'];
    foreach ($cases as $case) {
        $process = proc_open([PHP_BINARY,'-d','xdebug.mode=off',__FILE__,$case],[STDIN,STDOUT,STDERR],$pipes);
        if (!is_resource($process) || proc_close($process) !== 0) exit(1);
    }
    exit;
}
$root=dirname(__DIR__);
require $root . '/vendor/autoload.php';
require $root . '/_Helper/Database.php';
$testConfig=require $root . '/_Config/config.php';
$testConfig['local_directory_status']='Active';
$testConfig['local_directory_upload_allowed_formats']=['png'];
$testConfig['local_directory_upload_max_bytes']=5242880;
$p=Database::getConnection();
$sql=file_get_contents($root . '/DB/web_sekolah.sql');
foreach (['articles','article_contents','article_tag_map','tags','file_manager','teachers','facilities','galleries','hero_slides','testimonials'] as $table) {
    preg_match('/CREATE TABLE IF NOT EXISTS `' . $table . '` .*?;/s',$sql,$match);
    $p->exec(str_replace('CREATE TABLE IF NOT EXISTS','CREATE TEMPORARY TABLE',$match[0]));
}
$p->exec("INSERT INTO articles (title,slug,category_tag,summary,status) VALUES ('Satu','satu','Berita','100%_!','draft'),('Dua','dua','Agenda','Kegiatan','published')");
$p->exec("INSERT INTO tags (name,slug) VALUES ('Literasi','literasi'),('Sekolah','sekolah')");
$p->exec('INSERT INTO article_tag_map VALUES (1,1),(1,2),(2,1)');
$case=$argv[1]; $kind=explode('_',$case)[0];
$endpoint=['add'=>'creat_artikel.php','update'=>'update_artikel.php','get'=>'get_artikel.php','detail'=>'detail_artikel.php','bytags'=>'get_artikel_by_tags.php','tags'=>'get_tags.php','delete'=>'delete_artikel.php','image'=>'update_image.php'][$kind] ?? 'creat_artikel.php';
$method=['add'=>'POST','update'=>'PUT','get'=>'GET','detail'=>'GET','bytags'=>'GET','tags'=>'GET','delete'=>'DELETE','image'=>'PUT'][$kind] ?? 'POST';
$expected=$kind==='add' ? 201 : 200;
$payload=['title'=>'Baru','slug'=>'baru','category_tag'=>'Berita','summary'=>'Ringkasan'];
$_GET=[];
$image=imagecreatetruecolor(2,2); ob_start(); imagepng($image); $png=ob_get_clean(); imagedestroy($image);
$directory=$root . '/assets/img/local_directory';
if (!is_dir($directory)) mkdir($directory,0755,true);
$oldPath=$directory . '/article-test-' . bin2hex(random_bytes(12)) . '.png';
file_put_contents($oldPath,$png);
$p->prepare('INSERT INTO file_manager (file_source,file_metadata,creat_at) VALUES (?,?,UTC_TIMESTAMP())')->execute(['Local Directory',json_encode(['file_name'=>basename($oldPath),'file_mime_type'=>'image/png'])]);
$p->exec('UPDATE articles SET id_file_manager=1 WHERE id=1');
if ($kind==='add') {
    if ($case==='add_tags') { $payload['tag_ids']=[1,1]; $payload['tags']=[(object)['name'=>'Baru','slug'=>'baru']]; }
    if ($case==='add_reuse') $payload['tags']=[(object)['name'=>'Literasi','slug'=>'literasi']];
    if ($case==='add_conflict') { $payload['tags']=[(object)['name'=>'Literasi','slug'=>'lain']]; $expected=409; }
    if ($case==='add_bad_tag') { $payload['tag_ids']=[999]; $expected=400; }
    if ($case==='add_slug_duplicate') { $payload['slug']='satu'; $expected=409; }
    if ($case==='add_invalid') { unset($payload['summary']); $expected=400; }
    if ($case==='add_local' || $case==='add_rollback') { $payload['file_source']='Local Directory'; $payload['base64']=base64_encode($png); }
    if ($case==='add_rollback') { $payload['tags']=[(object)['name'=>'Baru','slug'=>'baru'],(object)['name'=>'Literasi','slug'=>'conflict']]; $expected=409; }
}
if ($kind==='update') {
    $payload['id']=1;
    if ($case==='update_tags') $payload['tag_ids']=[2];
    if ($case==='update_clear') $payload['tags']=[];
    if ($case==='update_missing') { $payload['id']=99; $expected=404; }
    if ($case==='update_duplicate') { $payload['slug']='dua'; $expected=409; }
    if ($case==='update_rollback') { $payload['tags']=[(object)['name'=>'Baru','slug'=>'baru'],(object)['name'=>'Sekolah','slug'=>'conflict']]; $expected=409; }
}
if ($case==='get_page') $_GET=['page'=>'2','limit'=>'1'];
if ($case==='get_status') $_GET=['status'=>'draft'];
if ($case==='get_literal') $_GET=['keyword'=>'%_!'];
if ($case==='get_invalid') { $_GET=['order_by'=>'sort_order']; $expected=400; }
if ($kind==='detail') {
    $_GET=$case==='detail_slug' ? ['slug'=>'satu'] : ['id'=>$case==='detail_missing' ? '99' : '1'];
    if ($case==='detail_missing') $expected=404;
}
if ($kind==='bytags') {
    $_GET=$case==='bytags_slug' ? ['tag_slug'=>'literasi'] : ['tag_id'=>'1'];
    if ($case==='bytags_missing') { $_GET=[]; $expected=400; }
}
if ($kind==='delete') {
    $_GET=['id'=>'1'];
    if ($case==='delete_shared') $p->exec('UPDATE articles SET id_file_manager=1 WHERE id=2');
    if ($case==='delete_contents') { $p->exec("INSERT INTO article_contents (article_id,block_type,content_metadata) VALUES (1,'Paragraph','{}')"); $expected=409; }
}
if ($kind==='image') $payload=$case==='image_delete' ? ['id'=>1,'file_source'=>'DELETE','base64'=>''] : ['id'=>1,'file_source'=>'Local Directory','base64'=>base64_encode($png)];
$_SERVER['REQUEST_METHOD']=$method; $_SERVER['CONTENT_TYPE']='application/json';
$_SERVER['SCRIPT_NAME']='/web-sekolah/_API/Artikel/' . $endpoint;
$_SERVER['HTTP_AUTHORIZATION']='Bearer ' . Firebase\JWT\JWT::encode(['exp'=>time()+120,'app_name'=>'Test'],$testConfig['jwt_secret_key'],'HS256');
if ($case==='auth') { unset($_SERVER['HTTP_AUTHORIZATION']); $expected=401; }
if ($case==='method') { $_SERVER['REQUEST_METHOD']='GET'; $expected=405; }
if ($case==='options') { $_SERVER['REQUEST_METHOD']='OPTIONS'; $expected=204; }
$snapshot=static function () use ($p) {
    $state=[];
    foreach (['articles','tags','article_tag_map','file_manager','article_contents'] as $table) $state[$table]=$p->query('SELECT * FROM ' . $table . ' ORDER BY 1,2')->fetchAll();
    return $state;
};
$before=$snapshot(); $beforeFiles=glob($directory . '/*') ?: []; $testBody=json_encode($payload);
ob_start();
register_shutdown_function(function () use ($p,$case,$kind,$expected,$snapshot,$before,$beforeFiles,$directory,$oldPath,$payload) {
    global $storage,$newStorage;
    $body=json_decode(ob_get_clean(),true); $after=$snapshot();
    $ok=http_response_code()===$expected && !$p->inTransaction();
    if ($expected>=400 || $expected===204) $ok=$ok && $before===$after && (glob($directory . '/*') ?: [])===$beforeFiles;
    elseif ($kind==='add') {
        $row=end($after['articles']); $ok=$ok && count($after['articles'])===3 && $row['slug']==='baru';
        if ($case==='add_tags') $ok=$ok && count($body['data']['tags'])===2 && count($after['tags'])===3;
        if ($case==='add_reuse') $ok=$ok && count($body['data']['tags'])===1 && count($after['tags'])===2;
        if ($case==='add_local') $ok=$ok && $row['id_file_manager']>1;
    } elseif ($kind==='update') {
        $ok=$ok && $after['articles'][0]['slug']==='baru' && $after['articles'][0]['status']==='draft' && $after['articles'][0]['id_file_manager']===1;
        $wanted=$case==='update_clear' ? [] : ($case==='update_tags' ? [2] : [1,2]);
        $ok=$ok && array_column($body['data']['tags'],'id')===$wanted;
    } elseif ($kind==='get' || $kind==='bytags') {
        $wanted=in_array($case,['get_page','get_status','get_literal'],true) ? [1] : [2,1];
        $ok=$ok && array_column($body['data'],'id')===$wanted && $before===$after && count($body['data'][0]['tags'])>=1;
    } elseif ($kind==='detail') $ok=$ok && $body['data']['id']===1 && count($body['data']['tags'])===2 && $before===$after;
    elseif ($kind==='tags') $ok=$ok && count($body['data'])===2 && $before===$after;
    elseif ($kind==='delete') $ok=$ok && count($after['articles'])===1 && count($after['tags'])===2 && count($after['article_tag_map'])===1 && is_file($oldPath)===($case==='delete_shared');
    elseif ($kind==='image') $ok=$ok && !is_file($oldPath) && $before['article_tag_map']===$after['article_tag_map'] && ($case==='image_delete' ? $after['articles'][0]['id_file_manager']===null : $after['articles'][0]['id_file_manager']>1);
    if (isset($storage)) $storage->cleanup();
    if (isset($newStorage)) $newStorage->cleanup();
    if (is_file($oldPath) && dirname(realpath($oldPath))===realpath($directory)) unlink($oldPath);
    echo ($ok?'PASS ':'FAIL ') . $case . PHP_EOL;
    if (!$ok) { echo 'HTTP ' . http_response_code() . ', expected ' . $expected . PHP_EOL; exit(1); }
});
if ($kind==='detail' || $kind==='bytags') { $articleReadMode=$kind==='detail'?'detail':'tags'; $endpoint='get_artikel.php'; }
$code=file_get_contents($root . '/_API/Artikel/' . $endpoint);
$code=str_replace('__DIR__',var_export($root . '/_API/Artikel',true),$code);
$code=preg_replace('~require .*?/../../_Config/config\.php\x27;~','$testConfig;',$code,1);
$code=str_replace(["file_get_contents('php://input', false, null, 0, \$maxBody + 1)","file_get_contents('php://input')"],'$testBody',$code);
eval(substr($code,5));
