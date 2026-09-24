<?php
// php tests/hero_test.php: temporary tables and test-owned files; providers mocked.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!isset($argv[1])) {
    $cases = ['add_inactive','add_bad_active','add_null_active','add_missing_title','add_missing_subtitle','add_none','add_local','add_cloud','add_imagekit','add_external','add_disabled','add_pdf','add_long_title','add_long_subtitle','add_255_subtitle','add_rollback',
        'get_active','get_inactive','get_active_search','get_bad_active','get_all','get_page','get_search','get_literal','get_order','get_empty','get_invalid',
        'update_inactive','update_bad_active','update_preserve_inactive','update','update_same','update_missing','update_protected','update_long','update_bad_id',
        'sort_up','sort_down','sort_first','sort_last','sort_zero','sort_gap','sort_duplicate','sort_missing','sort_rollback',
        'delete','delete_missing','delete_cloud','delete_inactive','delete_shared_hero','delete_shared_gallery','delete_shared_facility','delete_shared_teacher','delete_failure',
        'image_local','image_cloud','image_imagekit','image_external','image_delete','image_none_delete','image_inactive','image_shared_hero','image_shared_gallery','image_shared_facility','image_shared_teacher','image_cleanup_failure','image_upload_failure','image_rollback','image_race','image_missing','image_pdf',
        'auth','method','options'];
    foreach ($cases as $case) {
        $process = proc_open([PHP_BINARY,'-d','xdebug.mode=off',__FILE__,$case],[STDIN,STDOUT,STDERR],$pipes);
        if (!is_resource($process) || proc_close($process) !== 0) { exit(1); }
    }
    exit;
}
$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
require $root . '/_Helper/Database.php';
require $root . '/_Helper/HeroImageStorage.php';
$case = $argv[1];
$testConfig = require $root . '/_Config/config.php';
foreach (['local_directory','cloudinary','imagekit'] as $prefix) {
    $testConfig[$prefix . '_status']='Active';
    $testConfig[$prefix . '_upload_allowed_formats']=['png'];
    $testConfig[$prefix . '_upload_max_bytes']=5242880;
}
$testConfig['external_link_status']='Active';
$testConfig['external_link_verify']=false;
$p=Database::getConnection();
$sql=file_get_contents($root . '/DB/web_sekolah.sql');
foreach (['teachers','file_manager','articles','article_contents','facilities','galleries','hero_slides','testimonials'] as $table) {
    preg_match('/CREATE TABLE IF NOT EXISTS `' . $table . '` .*?;/s',$sql,$match);
    $p->exec(str_replace('CREATE TABLE IF NOT EXISTS','CREATE TEMPORARY TABLE',$match[0]));
}
$image=imagecreatetruecolor(2,2);
ob_start(); imagepng($image); $png=ob_get_clean(); imagedestroy($image);
$directory=$root . '/assets/img/local_directory';
if (!is_dir($directory)) { mkdir($directory,0755,true); }
$oldPath=$directory . '/hero-test-' . bin2hex(random_bytes(12)) . '.png';
file_put_contents($oldPath,$png);
$oldSource=in_array($case,['delete_cloud','delete_inactive'],true) ? 'Cloudinary' : 'Local Directory';
$metadata=$oldSource==='Cloudinary' ? ['public_id'=>'old-hero','resource_type'=>'Image'] : ['file_name'=>basename($oldPath),'file_mime_type'=>'image/png'];
$stmt=$p->prepare('INSERT INTO file_manager (file_source,file_metadata,creat_at) VALUES (?,?,UTC_TIMESTAMP())');
$stmt->execute([$oldSource,json_encode($metadata)]);
$p->exec("INSERT INTO hero_slides (id,title,subtitle,id_file_manager,sort_order) VALUES (1,'Library','Books 100%_!',1,1),(2,'Lab','Science',NULL,2),(3,'Hall','Assembly',NULL,3)");
if (in_array($case,['delete_shared_teacher','image_shared_teacher'],true)) {
    $p->exec("INSERT INTO teachers (id,name,role,id_file_manager) VALUES (1,'Teacher','Role',1)");
}

if (strpos($case, 'shared_hero') !== false) { $p->exec('UPDATE hero_slides SET id_file_manager=1 WHERE id=2'); }
if (strpos($case, 'shared_gallery') !== false) { $p->exec("INSERT INTO galleries (caption,id_file_manager) VALUES ('Shared',1)"); }
if (strpos($case, 'shared_facility') !== false) { $p->exec("INSERT INTO facilities (title,description,id_file_manager) VALUES ('Shared','Shared',1)"); }

class HeroTestStorage extends HeroImageStorage
{
    public static array $events=[];
    protected function uploadCloudinary(string $file,string $publicId,string $extension): array
    {
        if (strpos($publicId,'web_sekolah/hero_slides/')!==0) { throw new LogicException('Wrong Cloudinary folder'); }
        return ['public_id'=>$publicId,'resource_type'=>'image','secure_url'=>'https://example.com/new.png','format'=>$extension];
    }
    protected function deleteCloudinary(string $publicId): void { self::$events[]='cloud_delete:' . $publicId; }
    protected function imagekitRequest(string $method,array $fields=[],?string $fileId=null): array
    {
        if ($method==='DELETE') { self::$events[]='imagekit_delete:' . $fileId; return []; }
        if ($fields['folder']!=='/web_sekolah/hero_slides') { throw new LogicException('Wrong Imagekit folder'); }
        return ['fileId'=>'new-hero','fileType'=>'image','url'=>'https://example.com/new.png'];
    }
    public function store(string $source,object $input): array
    {
        global $case,$p;
        if ($case==='image_upload_failure') { throw new RuntimeException('Upload gagal.',502); }
        $result=parent::store($source,$input);
        self::$events[]='uploaded';
        if ($case==='image_race') { $p->exec('UPDATE hero_slides SET id_file_manager=NULL WHERE id=1'); }
        return $result;
    }
    public function deleteStoredFile(string $source,$metadata): string
    {
        global $case,$p;
        if ($case==='image_cleanup_failure') {
            if ((int)$p->query('SELECT id_file_manager FROM hero_slides WHERE id=1')->fetchColumn()===1) { throw new LogicException('Cleanup before replacement'); }
            throw new RuntimeException('Cleanup gagal.',502);
        }
        if ($case==='delete_failure') { throw new RuntimeException('Penghapusan gagal.',502); }
        self::$events[]='old_cleanup';
        return parent::deleteStoredFile($source,$metadata);
    }
}

$kind=explode('_',$case)[0];
$map=['add'=>'add_hero.php','get'=>'get_hero.php','update'=>'update_hero.php','sort'=>'update_sort_order.php','delete'=>'delete_hero.php','image'=>'update_image.php'];
$endpoint=$map[$kind] ?? 'add_hero.php';
$method=['add'=>'POST','get'=>'GET','update'=>'PUT','sort'=>'PUT','delete'=>'DELETE','image'=>'PUT'][$kind] ?? 'POST';
$expected=$kind==='add' ? 201 : 200;
$payload=['title'=>'New hero','subtitle'=>'New subtitle'];
$_GET=[];
if ($kind==='add') {
    if ($case==='add_inactive') { $payload['is_active']=0; }
    if ($case==='add_bad_active') { $payload['is_active']='1'; $expected=400; }
    if ($case==='add_null_active') { $payload['is_active']=null; $expected=400; }
    if ($case==='add_missing_title') { unset($payload['title']); $expected=400; }
    if ($case==='add_missing_subtitle') { unset($payload['subtitle']); $expected=400; }
    if (in_array($case,['add_local','add_cloud','add_imagekit','add_external','add_disabled','add_pdf','add_rollback'],true)) {
        $payload['file_source']=$case==='add_cloud' ? 'Cloudinary' : ($case==='add_imagekit' || $case==='add_disabled' ? 'Imagekit' : 'Local Directory');
        $payload['base64']=base64_encode($png);
    }
    if ($case==='add_external') { $payload['file_source']='External Link'; unset($payload['base64']); $payload['image_url']='example.com/image.png'; }
    if ($case==='add_disabled') { $testConfig['imagekit_status']='Inactive'; $expected=403; }
    if ($case==='add_pdf') { $payload['base64']=base64_encode('%PDF-1.7 document'); $expected=415; }
    if ($case==='add_long_title') { $payload['title']=str_repeat('a',101); $expected=400; }
    if ($case==='add_long_subtitle') { $payload['subtitle']=str_repeat('a',256); $expected=400; }
    if ($case==='add_255_subtitle') { $payload['subtitle']=str_repeat('a',255); }
    if ($case==='add_rollback') { $p->exec("ALTER TABLE hero_slides ADD CONSTRAINT reject_new CHECK (title <> 'New hero')"); $expected=500; }
}
if ($kind==='get') {
    if (in_array($case,['get_active','get_inactive','get_active_search'],true)) {
        $p->exec('UPDATE hero_slides SET is_active=0 WHERE id=2');
        $_GET=['is_active'=>$case==='get_active' ? '1' : '0'];
        if ($case==='get_active_search') { $_GET['keyword']='Science'; }
    }
    if ($case==='get_bad_active') { $_GET=['is_active'=>'true']; $expected=400; }
    if ($case==='get_page') { $_GET=['limit'=>'1','page'=>'2']; }
    if ($case==='get_search') { $_GET=['keyword'=>'Science']; }
    if ($case==='get_literal') { $_GET=['keyword'=>'%_!']; }
    if ($case==='get_order') { $_GET=['order_by'=>'title','short_by'=>'DESC']; }
    if ($case==='get_empty') { $p->exec('DELETE FROM hero_slides'); }
    if ($case==='get_invalid') { $_GET=['order_by'=>'name']; $expected=400; }
}
if ($kind==='update') {
    if ($case==='update_inactive') { $payload['is_active']=0; }
    if ($case==='update_bad_active') { $payload['is_active']=true; $expected=400; }
    if ($case==='update_preserve_inactive') { $p->exec('UPDATE hero_slides SET is_active=0 WHERE id=1'); }
    $payload['id']=1;
    if ($case==='update_same') { $payload=['id'=>1,'title'=>'Library','subtitle'=>'Books 100%_!']; }
    if ($case==='update_missing') { $payload['id']=99; $expected=404; }
    if ($case==='update_bad_id') { $payload['id']=2147483648; $expected=400; }
    if ($case==='update_protected') { $payload['sort_order']=99; $expected=400; }
    if ($case==='update_long') { $payload['subtitle']=str_repeat('a',256); $expected=400; }
}
if ($kind==='sort') {
    $payload=['id'=>2,'sort_order'=>'UP'];
    if ($case==='sort_down') { $payload['sort_order']='DOWN'; }
    if ($case==='sort_first') { $payload['id']=1; $expected=409; }
    if ($case==='sort_last') { $payload=['id'=>3,'sort_order'=>'DOWN']; $expected=409; }
    if ($case==='sort_zero') { $p->exec('UPDATE hero_slides SET sort_order=sort_order-1'); }
    if ($case==='sort_gap') { $p->exec('UPDATE hero_slides SET sort_order=sort_order*10'); }
    if ($case==='sort_duplicate') { $p->exec('UPDATE hero_slides SET sort_order=2 WHERE id=3'); $expected=409; }
    if ($case==='sort_missing') { $payload['id']=99; $expected=404; }
    if ($case==='sort_rollback') { $p->exec("ALTER TABLE hero_slides ADD CONSTRAINT reject_swap CHECK (title <> 'Library' OR sort_order=1)"); $expected=500; }
}
if ($kind==='delete') {
    $_GET=['id'=>'1'];
    if ($case==='delete_missing') { $_GET=['id'=>'99']; $expected=404; }
    if ($case==='delete_inactive') { $testConfig['cloudinary_status']='Inactive'; }
    if ($case==='delete_failure') { $expected=502; }
}
if ($kind==='image') {
    $payload=['id'=>1,'file_source'=>'Local Directory','base64'=>base64_encode($png)];
    if ($case==='image_cloud') { $payload['file_source']='Cloudinary'; }
    if ($case==='image_imagekit' || $case==='image_inactive') { $payload['file_source']='Imagekit'; }
    if ($case==='image_external') { $payload=['id'=>1,'file_source'=>'External Link','image_url'=>'example.com/new.png']; }
    if ($case==='image_delete' || $case==='image_none_delete') { $payload=['id'=>1,'file_source'=>'DELETE','base64'=>'']; }
    if ($case==='image_none_delete') { $p->exec('UPDATE hero_slides SET id_file_manager=NULL WHERE id=1'); }
    if ($case==='image_inactive') { $testConfig['imagekit_status']='Inactive'; $expected=403; }
    if ($case==='image_upload_failure') { $expected=502; }
    if ($case==='image_rollback') { $p->exec('ALTER TABLE hero_slides ADD CONSTRAINT reject_image CHECK (id_file_manager IS NULL OR id_file_manager=1)'); $expected=500; }
    if ($case==='image_race') { $expected=409; }
    if ($case==='image_missing') { $payload['id']=99; $expected=404; }
    if ($case==='image_pdf') { $payload['base64']=base64_encode('%PDF-1.7 document'); $expected=415; }
}
$before=$p->query('SELECT * FROM hero_slides ORDER BY id')->fetchAll();
$beforeFiles=glob($directory . '/*') ?: [];
$testBody=json_encode($payload);
$_SERVER['REQUEST_METHOD']=$method;
$_SERVER['CONTENT_TYPE']='application/json';
$_SERVER['SCRIPT_NAME']='/web-sekolah/_API/Hero/' . $endpoint;
$_SERVER['HTTP_AUTHORIZATION']='Bearer ' . Firebase\JWT\JWT::encode(['exp'=>time()+60,'app_name'=>'Test'],$testConfig['jwt_secret_key'],'HS256');
if ($case==='auth') { unset($_SERVER['HTTP_AUTHORIZATION']); $expected=401; }
if ($case==='method') { $_SERVER['REQUEST_METHOD']='GET'; $expected=405; }
if ($case==='options') { $_SERVER['REQUEST_METHOD']='OPTIONS'; $expected=204; }
ob_start();
register_shutdown_function(function () use ($p,$case,$kind,$expected,$before,$beforeFiles,$directory,$oldPath,$oldSource,$payload) {
    global $storage,$newStorage;
    $body=json_decode(ob_get_clean(),true);
    $rows=$p->query('SELECT * FROM hero_slides ORDER BY id')->fetchAll();
    $ok=http_response_code()===$expected && !$p->inTransaction();
    $oldExists=(bool)$p->query('SELECT COUNT(*) FROM file_manager WHERE id_file_manager=1')->fetchColumn();
    if ($expected>=400 || $expected===204) {
        if ($case==='image_race') { $before[0]['id_file_manager']=null; }
        $ok=$ok && $rows===$before && $oldExists && is_file($oldPath)
            && (glob($directory . '/*') ?: [])===$beforeFiles;
    } elseif ($kind==='add') {
        $row=end($rows);
        $ok=$ok && $row['is_active']===($payload['is_active'] ?? 1) && $body['data']['is_active']===$row['is_active'] && $row['sort_order']===4;
        $ok=$ok && count($rows)===4 && $row['title']===$payload['title'] && $row['subtitle']===$payload['subtitle'];
        if (isset($payload['file_source'])) {
            $ok=$ok && $row['id_file_manager']!==null && $body['data']['file_source']===$payload['file_source'];
            if ($payload['file_source']==='Local Directory') { $ok=$ok && is_file($directory . '/' . $body['data']['file_metadata']['file_name']); }
        } else { $ok=$ok && $row['id_file_manager']===null; }
    } elseif ($kind==='get') {
        $ok=$ok && $rows===$before;
        $data=$body['data'];
        if ($case==='get_active') { $ok=$ok && array_column($data,'id')===[1,3] && $body['pagination']['total']===2; }
        if ($case==='get_inactive' || $case==='get_active_search') { $ok=$ok && array_column($data,'id')===[2] && $data[0]['is_active']===0 && $body['filter']['is_active']===0; }
        if ($case==='get_all') { $ok=$ok && count($data)===3 && $body['pagination']['total']===3 && is_array($data[0]['file_metadata']) && $data[1]['image_url']===null && $data[0]['image_url']==='/web-sekolah/assets/img/local_directory/' . basename($oldPath); }
        if ($case==='get_page' || $case==='get_search') { $ok=$ok && count($data)===1 && $data[0]['id']===2; }
        if ($case==='get_literal') { $ok=$ok && count($data)===1 && $data[0]['id']===1; }
        if ($case==='get_order') { $ok=$ok && array_column($data,'id')===[1,2,3]; }
        if ($case==='get_empty') { $ok=$ok && $data===[] && $body['pagination']['total']===0; }
    } elseif ($kind==='update') {
        $ok=$ok && $rows[0]['is_active']===($payload['is_active'] ?? $before[0]['is_active']) && $body['data']['is_active']===$rows[0]['is_active'];
        $ok=$ok && $rows[0]['title']===$payload['title'] && $rows[0]['subtitle']===$payload['subtitle'] && $rows[0]['id_file_manager']===1 && $rows[0]['sort_order']===1;
    } elseif ($kind==='sort') {
        $expectedOrder=$case==='sort_down' ? [1,3,2] : ($case==='sort_zero' ? [1,0,2] : ($case==='sort_gap' ? [20,10,30] : [2,1,3]));
        $ok=$ok && array_column($rows,'sort_order')===$expectedOrder;
        foreach ($rows as $i=>$row) { unset($row['sort_order'],$before[$i]['sort_order']); $ok=$ok && $row===$before[$i]; }
    } elseif ($kind==='delete') {
        $retained=in_array($case,['delete_inactive','delete_shared_teacher','delete_shared_hero','delete_shared_gallery','delete_shared_facility'],true);
        $ok=$ok && count($rows)===2 && $oldExists===$retained && $body['data']['file_metadata_deleted']===!$retained;
        if ($oldSource==='Local Directory') { $ok=$ok && is_file($oldPath)===$retained; }
        if ($case==='delete_inactive' || $case==='delete_shared_teacher') { $ok=$ok && !in_array('cloud_delete:old-hero',HeroTestStorage::$events,true); }
        if ($case==='delete_cloud') { $ok=$ok && in_array('cloud_delete:old-hero',HeroTestStorage::$events,true); }
    } elseif ($kind==='image') {
        $retained=in_array($case,['image_none_delete','image_shared_teacher','image_shared_hero','image_shared_gallery','image_shared_facility','image_cleanup_failure'],true);
        $ok=$ok && $oldExists===$retained && is_file($oldPath)===$retained;
        $row=$rows[0];
        $ok=$ok && $row['title']===$before[0]['title'] && $row['subtitle']===$before[0]['subtitle'] && $row['sort_order']===$before[0]['sort_order'];
        if ($payload['file_source']==='DELETE') { $ok=$ok && $row['id_file_manager']===null; }
        else { $ok=$ok && $row['id_file_manager']>1 && $body['data']['file_source']===$payload['file_source']; }
        if ($case==='image_cleanup_failure') { $ok=$ok && $body['data']['old_file_deletion']==='cleanup_pending' && count($body['warnings'])===1; }
    }
    if (isset($storage)) { $storage->cleanup(); }
    if (isset($newStorage)) { $newStorage->cleanup(); }
    if (is_file($oldPath)) {
        if (dirname(realpath($oldPath))!==realpath($directory)) { throw new RuntimeException('Unsafe cleanup'); }
        unlink($oldPath);
    }
    echo ($ok ? 'PASS ' : 'FAIL ') . $case . "\n";
    if (!$ok) { echo 'HTTP ' . http_response_code() . ', expected ' . $expected . "\n"; exit(1); }
});
$code=file_get_contents($root . '/_API/Hero/' . $endpoint);
$code=str_replace('__DIR__',var_export($root . '/_API/Hero',true),$code);
$code=preg_replace('~require .*?/../../_Config/config\.php\x27;~','$testConfig;',$code,1);
$code=str_replace(["file_get_contents('php://input', false, null, 0, \$maxBody + 1)","file_get_contents('php://input')"],'$testBody',$code);
$code=str_replace('new HeroImageStorage($config)','new HeroTestStorage($config)',$code);
eval(substr($code,5));
