<?php
// php tests/delete_teachers_test.php: temporary tables and test-owned local files only.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!isset($argv[1])) {
    foreach (['none', 'local', 'local_missing', 'local_inactive', 'external', 'cloudinary', 'imagekit', 'cloudinary_inactive', 'imagekit_inactive', 'provider_failure', 'shared_teacher', 'shared_gallery', 'bad_metadata', 'bad_path', 'missing', 'invalid_id', 'array_id', 'auth', 'method', 'options'] as $case) {
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
foreach (['local_directory', 'external_link', 'cloudinary', 'imagekit'] as $prefix) { $testConfig[$prefix . '_status'] = 'Active'; }
class DeleteTestStorage extends TeacherImageStorage
{
    public static array $calls = [];
    protected function deleteCloudinary(string $publicId): void
    {
        global $case;
        if ($case === 'provider_failure') { throw new RuntimeException('Penghapusan gambar Cloudinary gagal.', 502); }
        self::$calls[] = ['Cloudinary', $publicId];
    }
    protected function imagekitRequest(string $method, array $fields = [], ?string $fileId = null): array
    {
        self::$calls[] = ['Imagekit', $method, $fileId];
        return [];
    }
}
$p = Database::getConnection();
$sql = file_get_contents($root . '/DB/web_sekolah.sql');
foreach (['teachers', 'file_manager', 'articles', 'article_contents', 'facilities', 'galleries', 'hero_slides', 'testimonials'] as $table) {
    preg_match('/CREATE TABLE IF NOT EXISTS `' . $table . '` .*?;/s', $sql, $match);
    $p->exec(str_replace('CREATE TABLE IF NOT EXISTS', 'CREATE TEMPORARY TABLE', $match[0]));
}
$expected = 200;
$fileId = null;
$testFile = null;
$source = null;
$retain = false;
if (in_array($case, ['local', 'local_missing', 'local_inactive', 'bad_path', 'bad_metadata'], true)) { $source = 'Local Directory'; }
if (in_array($case, ['cloudinary', 'cloudinary_inactive', 'provider_failure', 'shared_teacher', 'shared_gallery'], true)) { $source = 'Cloudinary'; }
if (in_array($case, ['imagekit', 'imagekit_inactive'], true)) { $source = 'Imagekit'; }
if ($case === 'external') { $source = 'External Link'; }
$metadata = ['public_id' => 'teachers/delete-test', 'file_id' => 'delete-test-id', 'resource_type' => 'Image'];
if ($source === 'Local Directory') {
    $directory = $root . '/assets/img/local_directory';
    if (!is_dir($directory)) { mkdir($directory, 0755, true); }
    $name = 'delete-test-' . bin2hex(random_bytes(16)) . '.png';
    $testFile = $directory . '/' . $name;
    if ($case !== 'local_missing') { file_put_contents($testFile, 'test-owned file'); }
    $metadata = ['file_name' => $name];
}
if ($case === 'external') { $metadata = ['file_url' => 'https://example.com/photo.png', 'file_type' => 'Image']; }
if ($case === 'bad_metadata') { $metadata = []; $expected = 409; }
if ($case === 'bad_path') { $metadata = ['file_name' => '../config.php']; $expected = 409; }
if ($case === 'provider_failure') { $expected = 502; }
foreach (['local_inactive' => 'local_directory', 'cloudinary_inactive' => 'cloudinary', 'imagekit_inactive' => 'imagekit'] as $inactiveCase => $prefix) {
    if ($case === $inactiveCase) { $testConfig[$prefix . '_status'] = 'Inactive'; $retain = true; }
}
if ($source !== null) {
    $stmt = $p->prepare('INSERT INTO file_manager (file_source, file_metadata, creat_at) VALUES (?, ?, UTC_TIMESTAMP())');
    $stmt->execute([$source, json_encode($metadata)]);
    $fileId = (int) $p->lastInsertId();
}
$stmt = $p->prepare('INSERT INTO teachers (id, name, role, subject, id_file_manager, sort_order) VALUES (1, ?, ?, ?, ?, 7)');
$stmt->execute(['Teacher', 'Role', 'Subject', $fileId]);
if ($case === 'shared_teacher') {
    $p->exec('INSERT INTO teachers (id, name, role, id_file_manager) VALUES (2, \'Other teacher\', \'Role\', ' . $fileId . ')');
    $retain = true;
}
if ($case === 'shared_gallery') {
    $p->exec('INSERT INTO galleries (id_file_manager) VALUES (' . $fileId . ')');
    $retain = true;
}
$_GET = ['id' => '1'];
$_SERVER['REQUEST_METHOD'] = 'DELETE';
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . Firebase\JWT\JWT::encode(['exp' => time() + 60, 'app_name' => 'Test'], $testConfig['jwt_secret_key'], 'HS256');
if ($case === 'missing') { $_GET['id'] = '999'; $expected = 404; }
if ($case === 'invalid_id') { $_GET['id'] = '-1'; $expected = 400; }
if ($case === 'array_id') { $_GET['id'] = []; $expected = 400; }
if ($case === 'auth') { unset($_SERVER['HTTP_AUTHORIZATION']); $expected = 401; }
if ($case === 'method') { $_SERVER['REQUEST_METHOD'] = 'GET'; $expected = 405; }
if ($case === 'options') { $_SERVER['REQUEST_METHOD'] = 'OPTIONS'; $expected = 204; }
ob_start();
register_shutdown_function(function () use ($p, $case, $expected, $source, $fileId, $retain, $testFile, $root) {
    $body = json_decode(ob_get_clean(), true);
    $teacherExists = (int) $p->query('SELECT COUNT(*) FROM teachers WHERE id=1')->fetchColumn() === 1;
    $fileExists = (int) $p->query('SELECT COUNT(*) FROM file_manager')->fetchColumn() === 1;
    $ok = http_response_code() === $expected && !$p->inTransaction();
    $ok = $ok && $teacherExists === ($expected !== 200);
    $ok = $ok && $fileExists === ($fileId !== null && ($retain || $expected !== 200));
    if ($expected === 200) {
        $ok = $ok && $body['data']['file_metadata_deleted'] === ($fileId !== null && !$retain);
        if ($retain) { $ok = $ok && in_array($body['data']['file_deletion'], ['skipped_inactive', 'retained_shared'], true); }
    }
    $expectedCalls = in_array($case, ['cloudinary', 'imagekit'], true) ? 1 : 0;
    $ok = $ok && count(DeleteTestStorage::$calls) === $expectedCalls;
    if ($case === 'local') { $ok = $ok && !file_exists($testFile); }
    if ($case === 'local_inactive' || $case === 'bad_path' || $case === 'bad_metadata') { $ok = $ok && is_file($testFile); }
    if ($case === 'shared_teacher') { $ok = $ok && (int)$p->query('SELECT id_file_manager FROM teachers WHERE id=2')->fetchColumn() === $fileId; }
    if ($case === 'shared_gallery') { $ok = $ok && (int)$p->query('SELECT id_file_manager FROM galleries LIMIT 1')->fetchColumn() === $fileId; }
    // Only remove this test's random file, after checking the resolved parent directory.
    if ($testFile !== null && is_file($testFile)) {
        if (dirname(realpath($testFile)) !== realpath($root . '/assets/img/local_directory')) { throw new RuntimeException('Unsafe test cleanup'); }
        unlink($testFile);
    }
    echo ($ok ? 'PASS ' : 'FAIL ') . $case . "\n";
    if (!$ok) { exit(1); }
});
$code = file_get_contents($root . '/_API/Teachers/delete_teachers.php');
$code = str_replace('__DIR__', var_export($root . '/_API/Teachers', true), $code);
$code = preg_replace('~require .*?/../../_Config/config\.php\x27;~', '$testConfig;', $code, 1);
$code = str_replace('new TeacherImageStorage($config)', 'new DeleteTestStorage($config)', $code);
eval(substr($code, 5));
