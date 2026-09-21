<?php
// Run: php tests/add_teachers_test.php. Uses temporary MySQL tables; provider calls are mocked.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/_Helper/Database.php';
require_once dirname(__DIR__) . '/_Helper/TeacherImageStorage.php';

if (!isset($argv[1])) {
    $cases = ['none', 'local', 'gif', 'external_off', 'external_on', 'cloudinary', 'imagekit',
        'disabled', 'local_disabled', 'unknown', 'pdf', 'corrupt', 'oversize', 'mime_mismatch',
        'format_disabled', 'unreachable', 'external_document', 'private_url', 'external_video',
        'missing_source', 'missing_base64', 'invalid_json', 'rollback_local', 'rollback_imagekit', 'rollback_cloudinary',
        'cloudinary_failure', 'imagekit_failure', 'data_uri', 'external_disabled',
        'method', 'auth', 'options'];
    foreach ($cases as $case) {
        $process = proc_open([PHP_BINARY, '-d', 'xdebug.mode=off', __FILE__, $case], [STDIN, STDOUT, STDERR], $pipes);
        if (!is_resource($process) || proc_close($process) !== 0) {
            exit(1);
        }
    }
    exit;
}

$case = $argv[1];
$root = dirname(__DIR__);
$testConfig = require $root . '/_Config/config.php';
foreach (['local_directory', 'cloudinary', 'imagekit'] as $prefix) {
    $testConfig[$prefix . '_status'] = 'Active';
    $testConfig[$prefix . '_upload_allowed_formats'] = ['jpg', 'png', 'webp', 'gif'];
    $testConfig[$prefix . '_upload_max_bytes'] = 5 * 1024 * 1024;
}
$testConfig['external_link_status'] = 'Active';
$testConfig['external_link_verify'] = true;
$image = imagecreatetruecolor(2, 2);
ob_start(); imagepng($image); $png = ob_get_clean();
ob_start(); imagegif($image); $gif = ob_get_clean();
imagedestroy($image);

class TestTeacherImageStorage extends TeacherImageStorage
{
    public static array $deleted = [];
    protected function fetchExternalImage(string $url): string
    {
        global $case, $png;
        if ($case === 'private_url') { return parent::fetchExternalImage($url); }
        if ($case === 'unreachable') { throw new RuntimeException('URL gambar tidak dapat diakses.', 422); }
        return $case === 'external_document' ? '%PDF-1.7 document' : $png;
    }
    protected function uploadCloudinary(string $file, string $publicId, string $extension): array
    {
        global $case;
        if ($case === 'cloudinary_failure') { throw new RuntimeException('Upload gambar ke Cloudinary gagal.', 502); }
        if (!is_file($file) || !getimagesize($file)) { throw new LogicException('Invalid upload file'); }
        return ['resource_type' => 'image', 'public_id' => $publicId, 'secure_url' => 'https://example.com/image.png', 'format' => $extension];
    }
    protected function imagekitRequest(string $method, array $fields = [], ?string $fileId = null): array
    {
        global $case;
        if ($case === 'imagekit_failure') { throw new RuntimeException('Operasi penyimpanan Imagekit gagal.', 502); }
        if ($method === 'DELETE') { self::$deleted[] = $fileId; return []; }
        if (!($fields['file'] instanceof CURLFile) || !is_file($fields['file']->getFilename())) {
            throw new LogicException('Invalid Imagekit upload');
        }
        return ['fileId' => 'test-imagekit-id', 'fileType' => 'image', 'url' => 'https://example.com/image.png'];
    }
    protected function deleteCloudinary(string $publicId): void
    {
        self::$deleted[] = $publicId;
    }
}

$p = Database::getConnection();
$sql = file_get_contents($root . '/DB/web_sekolah.sql');
foreach (['file_manager', 'teachers'] as $table) {
    preg_match('/CREATE TABLE IF NOT EXISTS `' . $table . '` .*?;/s', $sql, $match);
    $p->exec(str_replace('CREATE TABLE IF NOT EXISTS', 'CREATE TEMPORARY TABLE', $match[0]));
}
$payload = ['name' => 'Teacher test', 'role' => 'Wali Kelas 1', 'subject' => 'Bahasa Indonesia'];
$expected = 201;
$source = null;
if (in_array($case, ['local', 'gif', 'local_disabled', 'pdf', 'corrupt', 'oversize', 'mime_mismatch', 'format_disabled', 'missing_base64', 'rollback_local', 'data_uri'], true)) {
    $source = 'Local Directory';
} elseif (in_array($case, ['external_off', 'external_on', 'unreachable', 'external_document', 'private_url', 'external_video', 'external_disabled'], true)) {
    $source = 'External Link';
} elseif (in_array($case, ['cloudinary', 'rollback_cloudinary', 'cloudinary_failure'], true)) { $source = 'Cloudinary'; }
elseif (in_array($case, ['imagekit', 'disabled', 'rollback_imagekit', 'imagekit_failure'], true)) { $source = 'Imagekit'; }
if ($source !== null) {
    $payload['file_source'] = $source;
    if ($source === 'External Link') { $payload['image_url'] = 'example.com/photo.png'; }
    else { $payload['base64'] = base64_encode($png); }
}
if ($case === 'gif') { $payload['base64'] = base64_encode($gif); }
if ($case === 'data_uri') { $payload['base64'] = 'data:image/png;base64,' . base64_encode($png); }
if ($case === 'external_disabled') { $testConfig['external_link_status'] = 'Inactive'; $expected = 403; }
if ($case === 'cloudinary_failure' || $case === 'imagekit_failure') { $expected = 502; }
if ($case === 'external_off' || $case === 'external_video') { $testConfig['external_link_verify'] = false; }
if ($case === 'external_video') { $payload['image_url'] = 'https://example.com/movie.mp4'; $expected = 415; }
if ($case === 'disabled') { $testConfig['imagekit_status'] = 'Inactive'; $expected = 403; }
if ($case === 'local_disabled') { $testConfig['local_directory_status'] = 'Inactive'; $expected = 403; }
if ($case === 'unknown') { $payload['file_source'] = 'Other'; $expected = 400; }
if ($case === 'pdf') { $payload['base64'] = base64_encode('%PDF-1.7 document'); $expected = 415; }
if ($case === 'corrupt') { $payload['base64'] = '/9j/4AAQSkZJ...'; $expected = 400; }
if ($case === 'oversize') { $testConfig['local_directory_upload_max_bytes'] = 1; $expected = 413; }
if ($case === 'mime_mismatch') { $payload['base64'] = 'data:image/jpeg;base64,' . base64_encode($png); $expected = 400; }
if ($case === 'format_disabled') { $testConfig['local_directory_upload_allowed_formats'] = ['jpg']; $expected = 415; }
if ($case === 'unreachable') { $expected = 422; }
if ($case === 'external_document') { $expected = 415; }
if ($case === 'private_url') { $payload['image_url'] = 'http://127.0.0.1/photo.png'; $expected = 400; }
if ($case === 'missing_source') { $payload['base64'] = base64_encode($png); $expected = 400; }
if ($case === 'missing_base64') { unset($payload['base64']); $expected = 400; }
if (strpos($case, 'rollback_') === 0) {
    $p->exec("ALTER TABLE teachers ADD CONSTRAINT test_reject CHECK (name <> 'Teacher test')");
    $expected = 500;
}
$testBody = json_encode($payload);
if ($case === 'invalid_json') { $testBody = '{'; $expected = 400; }
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'application/json';
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . Firebase\JWT\JWT::encode([
    'iat' => time(), 'exp' => time() + 60, 'app_name' => 'Teacher test',
], $testConfig['jwt_secret_key'], 'HS256');
if ($case === 'method') { $_SERVER['REQUEST_METHOD'] = 'GET'; $expected = 405; }
if ($case === 'auth') { unset($_SERVER['HTTP_AUTHORIZATION']); $expected = 401; }
if ($case === 'options') { $_SERVER['REQUEST_METHOD'] = 'OPTIONS'; $expected = 204; }
$before = glob($root . '/assets/img/local_directory/*') ?: [];
ob_start();
register_shutdown_function(function () use ($p, $case, $expected, $source, $root, $before) {
    global $storage;
    $body = json_decode(ob_get_clean(), true);
    $ok = http_response_code() === $expected;
    $teachers = $p->query('SELECT * FROM teachers')->fetchAll();
    $files = $p->query('SELECT * FROM file_manager')->fetchAll();
    if ($expected === 201) {
        $ok = $ok && count($teachers) === 1 && $teachers[0]['name'] === 'Teacher test';
        if ($source === null) { $ok = $ok && !$files && $teachers[0]['id_file_manager'] === null; }
        else {
            $ok = $ok && count($files) === 1 && $files[0]['file_source'] === $source
                && $teachers[0]['id_file_manager'] === $files[0]['id_file_manager'];
            $metadata = json_decode($files[0]['file_metadata'], true);
            $ok = $ok && $body['data']['file_metadata'] == $metadata;
            if ($source === 'Local Directory') {
                $path = $root . '/assets/img/local_directory/' . $metadata['file_name'];
                $ok = $ok && is_file($path) && $metadata['file_size'] === round(filesize($path) / 1024, 3)
                    && getimagesize($path)['mime'] === $metadata['file_mime_type'] && substr($metadata['file_upload_at'], -1) === 'Z';
            } elseif ($source === 'External Link') {
                $ok = $ok && $metadata === ['file_url' => 'https://example.com/photo.png', 'file_type' => 'Image'];
            } else {
                $ok = $ok && $metadata['resource_type'] === 'Image' && isset($metadata[$source === 'Cloudinary' ? 'public_id' : 'file_id']);
            }
        }
    } else {
        $ok = $ok && !$teachers && !$files && (glob($root . '/assets/img/local_directory/*') ?: []) === $before;
        if ($case === 'rollback_imagekit') { $ok = $ok && TestTeacherImageStorage::$deleted === ['test-imagekit-id']; }
        if ($case === 'rollback_cloudinary') { $ok = $ok && count(TestTeacherImageStorage::$deleted) === 1; }
    }
    if (isset($storage)) { $storage->cleanup(); }
    echo ($ok ? 'PASS ' : 'FAIL ') . $case . "\n";
    if (!$ok) { echo 'HTTP ' . http_response_code() . '; expected ' . $expected . "\n"; exit(1); }
});
// Supply deterministic body/config while exercising endpoint validation, JWT, storage and SQL.
$sourceCode = file_get_contents($root . '/_API/Teachers/add_teachers.php');
$sourceCode = str_replace('__DIR__', var_export($root . '/_API/Teachers', true), $sourceCode);
$sourceCode = preg_replace('~require .*?/../../_Config/config\.php\x27;~', '$testConfig;', $sourceCode, 1);
$sourceCode = str_replace("file_get_contents('php://input', false, null, 0, \$maxBody + 1)", '$testBody', $sourceCode);
$sourceCode = str_replace('new TeacherImageStorage($config)', 'new TestTeacherImageStorage($config)', $sourceCode);
eval(substr($sourceCode, 5));
