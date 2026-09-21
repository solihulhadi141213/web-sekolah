<?php
// php tests/update_teachers_image_test.php. No live provider operations or permanent DB writes.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!isset($argv[1])) {
    $cases = ['replace_local','replace_none','replace_cloud','replace_imagekit','replace_external',
        'delete_local','delete_none','delete_cloud','delete_imagekit','delete_external',
        'inactive_old','inactive_new','shared_teacher','shared_gallery','delete_shared','linked',
        'cleanup_failure','delete_failure','db_failure','race','missing','invalid_json','invalid_id',
        'invalid_source','invalid_delete','protected_field','corrupt','pdf','oversize','auth','method','content_type','options'];
    foreach ($cases as $case) {
        $process = proc_open([PHP_BINARY, '-d', 'xdebug.mode=off', __FILE__, $case], [STDIN, STDOUT, STDERR], $pipes);
        if (!is_resource($process) || proc_close($process) !== 0) { exit(1); }
    }
    exit;
}
$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
require $root . '/_Helper/Database.php';
require $root . '/_Helper/TeacherImageStorage.php';
$case = $argv[1];
$testConfig = require $root . '/_Config/config.php';
foreach (['local_directory','cloudinary','imagekit'] as $prefix) {
    $testConfig[$prefix . '_status'] = 'Active';
    $testConfig[$prefix . '_upload_allowed_formats'] = ['png'];
    $testConfig[$prefix . '_upload_max_bytes'] = 5242880;
}
$testConfig['external_link_status'] = 'Active';
$testConfig['external_link_verify'] = false;
$p = Database::getConnection();
$sql = file_get_contents($root . '/DB/web_sekolah.sql');
foreach (['teachers','file_manager','articles','article_contents','facilities','galleries','hero_slides','testimonials'] as $table) {
    preg_match('/CREATE TABLE IF NOT EXISTS `' . $table . '` .*?;/s', $sql, $match);
    $p->exec(str_replace('CREATE TABLE IF NOT EXISTS','CREATE TEMPORARY TABLE',$match[0]));
}
$image = imagecreatetruecolor(2,2);
ob_start(); imagepng($image); $png = ob_get_clean(); imagedestroy($image);
$directory = $root . '/assets/img/local_directory';
if (!is_dir($directory)) { mkdir($directory,0755,true); }
$beforeFiles = glob($directory . '/*') ?: [];
$oldPath = $directory . '/image-update-test-' . bin2hex(random_bytes(12)) . '.png';
file_put_contents($oldPath,$png);
$oldSource = 'Local Directory';
$oldMetadata = ['file_name'=>basename($oldPath),'file_mime_type'=>'image/png'];
if (in_array($case,['delete_cloud','inactive_old','linked'],true)) {
    $oldSource = 'Cloudinary';
    $oldMetadata = ['public_id'=>'old-cloud-id','url'=>'https://example.com/old.png','resource_type'=>'Image'];
}
if ($case === 'delete_imagekit') { $oldSource = 'Imagekit'; $oldMetadata = ['file_id'=>'old-imagekit-id','resource_type'=>'Image']; }
if ($case === 'delete_external') { $oldSource = 'External Link'; $oldMetadata = ['file_url'=>'https://example.com/old.png','file_type'=>'Image']; }
$oldId = null;
if (!in_array($case,['replace_none','delete_none'],true)) {
    $stmt = $p->prepare('INSERT INTO file_manager (file_source,file_metadata,creat_at) VALUES (?,?,UTC_TIMESTAMP())');
    $stmt->execute([$oldSource,json_encode($oldMetadata)]);
    $oldId = (int)$p->lastInsertId();
}
$stmt = $p->prepare("INSERT INTO teachers (id,name,role,subject,id_file_manager,sort_order) VALUES (5,'Teacher','Role','Subject',?,7)");
$stmt->execute([$oldId]);
if (in_array($case,['shared_teacher','delete_shared'],true)) { $p->exec('INSERT INTO teachers (id,name,role,id_file_manager) VALUES (6,\'Other\',\'Role\',' . $oldId . ')'); }
if ($case === 'shared_gallery') { $p->exec('INSERT INTO galleries (id_file_manager) VALUES (' . $oldId . ')'); }

class ImageUpdateTestStorage extends TeacherImageStorage
{
    public static array $events = [];
    public function store(string $source, object $input): array
    {
        global $p,$case,$oldId;
        if (($p->query('SELECT id_file_manager FROM teachers WHERE id=5')->fetchColumn() ?: null) != $oldId) { throw new LogicException('Old reference changed before upload'); }
        $metadata = parent::store($source,$input);
        self::$events[] = 'uploaded';
        if ($case === 'race') { $p->exec('UPDATE teachers SET id_file_manager=NULL WHERE id=5'); }
        return $metadata;
    }
    public function deleteStoredFile(string $source, $metadata): string
    {
        global $p,$case,$deleting,$oldId;
        if (!$deleting) {
            $currentId = $p->query('SELECT id_file_manager FROM teachers WHERE id=5')->fetchColumn();
            if ($currentId == $oldId || !in_array('uploaded',self::$events,true)) { throw new LogicException('Old file deleted before replacement'); }
        }
        self::$events[] = 'old_cleanup';
        if (in_array($case,['cleanup_failure','delete_failure'],true)) { throw new RuntimeException('Penghapusan gambar gagal.',502); }
        return parent::deleteStoredFile($source,$metadata);
    }
    protected function uploadCloudinary(string $file,string $publicId,string $extension): array
    {
        return ['public_id'=>$publicId,'resource_type'=>'image','secure_url'=>'https://example.com/new.png','format'=>$extension];
    }
    protected function deleteCloudinary(string $publicId): void { self::$events[] = 'cloud_delete:' . $publicId; }
    protected function imagekitRequest(string $method,array $fields=[],?string $fileId=null): array
    {
        if ($method === 'DELETE') { self::$events[] = 'imagekit_delete:' . $fileId; return []; }
        return ['fileId'=>'new-imagekit-id','fileType'=>'image','url'=>'https://example.com/new.png'];
    }
}
$deleting = strpos($case,'delete_') === 0;
$payload = ['id'=>5,'file_source'=>$deleting ? 'DELETE' : 'Local Directory','base64'=>$deleting ? '' : base64_encode($png)];
$expected = 200;
if ($case === 'replace_cloud') { $payload['file_source'] = 'Cloudinary'; }
if ($case === 'replace_imagekit' || $case === 'inactive_new') { $payload['file_source'] = 'Imagekit'; }
if ($case === 'replace_external' || $case === 'linked') { $payload['file_source'] = 'External Link'; unset($payload['base64']); $payload['image_url'] = 'https://example.com/' . ($case === 'linked' ? 'old' : 'new') . '.png'; }
if ($case === 'inactive_old') { $testConfig['cloudinary_status'] = 'Inactive'; }
if ($case === 'inactive_new') { $testConfig['imagekit_status'] = 'Inactive'; $expected = 403; }
if ($case === 'delete_failure') { $expected = 502; }
if ($case === 'db_failure') { $p->exec('ALTER TABLE teachers ADD CONSTRAINT reject_new CHECK (id_file_manager=1)'); $expected = 500; }
if ($case === 'race') { $expected = 409; }
if ($case === 'missing') { $payload['id'] = 999; $expected = 404; }
if ($case === 'invalid_id') { $payload['id'] = '5'; $expected = 400; }
if ($case === 'invalid_source') { $payload['file_source'] = 'Other'; $expected = 400; }
if ($case === 'invalid_delete') { $payload['file_source'] = 'DELETE'; $expected = 400; }
if ($case === 'protected_field') { $payload['sort_order'] = 99; $expected = 400; }
if ($case === 'corrupt') { $payload['base64'] = '/9j/...'; $expected = 400; }
if ($case === 'pdf') { $payload['base64'] = base64_encode('%PDF-1.7 document'); $expected = 415; }
if ($case === 'oversize') { $testConfig['local_directory_upload_max_bytes'] = 1; $expected = 413; }
$testBody = json_encode($payload);
if ($case === 'invalid_json') { $testBody = '{'; $expected = 400; }
$_SERVER['REQUEST_METHOD'] = 'PUT';
$_SERVER['CONTENT_TYPE'] = 'application/json';
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . Firebase\JWT\JWT::encode(['exp'=>time()+60,'app_name'=>'Test'],$testConfig['jwt_secret_key'],'HS256');
if ($case === 'auth') { unset($_SERVER['HTTP_AUTHORIZATION']); $expected = 401; }
if ($case === 'method') { $_SERVER['REQUEST_METHOD'] = 'GET'; $expected = 405; }
if ($case === 'options') { $_SERVER['REQUEST_METHOD'] = 'OPTIONS'; $expected = 204; }
if ($case === 'content_type') { $_SERVER['CONTENT_TYPE'] = 'text/plain'; $expected = 415; }
ob_start();
register_shutdown_function(function () use ($p,$case,$expected,$oldId,$oldPath,$oldSource,$deleting,$directory,$beforeFiles) {
    global $newStorage;
    $body = json_decode(ob_get_clean(),true);
    $teacher = $p->query('SELECT * FROM teachers WHERE id=5')->fetch();
    $ok = http_response_code() === $expected && !$p->inTransaction() && $teacher['name'] === 'Teacher' && $teacher['role'] === 'Role' && $teacher['subject'] === 'Subject' && (int)$teacher['sort_order'] === 7;
    $oldExists = $oldId !== null && (bool)$p->query('SELECT COUNT(*) FROM file_manager WHERE id_file_manager=' . $oldId)->fetchColumn();
    $retain = in_array($case,['inactive_old','shared_teacher','shared_gallery','delete_shared','linked','cleanup_failure'],true);
    if ($expected === 200) {
        $ok = $ok && $body['data']['id_file_manager'] === $teacher['id_file_manager'];
        $ok = $ok && $oldExists === ($oldId !== null && $retain);
        if ($deleting) { $ok = $ok && $teacher['id_file_manager'] === null && !in_array('uploaded',ImageUpdateTestStorage::$events,true); }
        else {
            $ok = $ok && $teacher['id_file_manager'] !== null && $teacher['id_file_manager'] != $oldId;
            $new = $p->query('SELECT * FROM file_manager WHERE id_file_manager=' . (int)$teacher['id_file_manager'])->fetch();
            $ok = $ok && json_decode($new['file_metadata'],true) == $body['data']['file_metadata'];
            if ($new['file_source'] === 'Local Directory') { $ok = $ok && is_file($directory . '/' . $body['data']['file_metadata']['file_name']); }
        }
        if ($case === 'cleanup_failure') { $ok = $ok && $body['data']['old_file_deletion'] === 'cleanup_pending' && count($body['warnings']) === 1; }
        if ($oldSource === 'Local Directory' && $oldId !== null) { $ok = $ok && is_file($oldPath) === $retain; }
        if ($case === 'inactive_old' || $case === 'linked') { $ok = $ok && !in_array('cloud_delete:old-cloud-id',ImageUpdateTestStorage::$events,true); }
        if ($case === 'delete_cloud') { $ok = $ok && in_array('cloud_delete:old-cloud-id',ImageUpdateTestStorage::$events,true); }
        if ($case === 'delete_imagekit') { $ok = $ok && in_array('imagekit_delete:old-imagekit-id',ImageUpdateTestStorage::$events,true); }
    } else {
        $ok = $ok && $teacher['id_file_manager'] === ($case === 'race' ? null : $oldId) && $oldExists === ($oldId !== null) && is_file($oldPath);
        $newFiles = array_values(array_diff(glob($directory . '/*') ?: [],$beforeFiles,[$oldPath]));
        $ok = $ok && $newFiles === [] && (int)$p->query('SELECT COUNT(*) FROM file_manager')->fetchColumn() === ($oldId === null ? 0 : 1);
    }
    // Only remove files created by this test; preserve all pre-existing application files.
    if (isset($newStorage)) { $newStorage->cleanup(); }
    if (is_file($oldPath)) {
        if (dirname(realpath($oldPath)) !== realpath($directory)) { throw new RuntimeException('Unsafe test path'); }
        unlink($oldPath);
    }
    echo ($ok ? 'PASS ' : 'FAIL ') . $case . "\n";
    if (!$ok) { echo 'HTTP ' . http_response_code() . ', expected ' . $expected . "\n"; exit(1); }
});
$code = file_get_contents($root . '/_API/Teachers/update_teachers_image.php');
$code = str_replace('__DIR__',var_export($root . '/_API/Teachers',true),$code);
$code = preg_replace('~require .*?/../../_Config/config\.php\x27;~','$testConfig;',$code,1);
$code = str_replace("file_get_contents('php://input', false, null, 0, \$maxBody + 1)",'$testBody',$code);
$code = str_replace('new TeacherImageStorage($config)','new ImageUpdateTestStorage($config)',$code);
eval(substr($code,5));
