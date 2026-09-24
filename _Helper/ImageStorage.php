<?php

/** Penyimpanan gambar bersama; metadata mengikuti Image-Metadata-Explanation.md. */
class ImageStorage
{
    private array $config;
    private string $folder;
    private ?string $localFile = null;
    private ?string $cloudinaryId = null;
    private ?string $imagekitId = null;
    private ?\Cloudinary\Cloudinary $cloudinary = null;
    private const FORMATS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

    public function __construct(array $config, string $folder = 'teachers')
    {
        if (!preg_match('/\A[a-z0-9_-]+\z/', $folder)) {
            throw new InvalidArgumentException('Folder storage tidak valid.');
        }
        $this->folder = $folder;
        $this->config = $config;
    }

    public function store(string $source, object $input): array
    {
        $prefixes = ['Local Directory' => 'local_directory', 'External Link' => 'external_link', 'Cloudinary' => 'cloudinary', 'Imagekit' => 'imagekit'];
        if (!isset($prefixes[$source])) {
            throw new RuntimeException('File_source tidak valid.', 400);
        }
        $prefix = $prefixes[$source];
        if (($this->config[$prefix . '_status'] ?? 'Inactive') !== 'Active') {
            throw new RuntimeException('Pengaturan storage ' . $source . ' tidak aktif.', 403);
        }
        if ($source === 'External Link') {
            if (property_exists($input, 'base64')) {
                throw new RuntimeException('External Link menggunakan image_url, bukan base64.', 400);
            }
            $url = $this->normalizeUrl($input->image_url ?? null);
            if (($this->config['external_link_verify'] ?? false) === true) {
                // Batasi unduhan verifikasi hingga 5 MiB dan periksa isi, bukan Content-Type saja.
                $binary = $this->fetchExternalImage($url);
                $this->inspectImage($binary, array_values(self::FORMATS));
            } else {
                // Tanpa akses jaringan, tipe hanya dapat diperiksa dari ekstensi URL.
                $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
                if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
                    throw new RuntimeException('URL harus menunjuk file gambar JPG, PNG, WebP, atau GIF.', 415);
                }
            }
            return ['file_url' => $url, 'file_type' => 'Image'];
        }
        if (property_exists($input, 'image_url')) {
            throw new RuntimeException('Storage ini menggunakan base64, bukan image_url.', 400);
        }
        $maxBytes = (int) ($this->config[$prefix . '_upload_max_bytes'] ?? 0);
        $allowed = $this->config[$prefix . '_upload_allowed_formats'] ?? [];
        if ($maxBytes < 1 || !is_array($allowed) || !$allowed) {
            throw new RuntimeException('Konfigurasi batas atau format upload tidak valid.', 500);
        }
        [$binary, $mime, $extension] = $this->decodeImage($input->base64 ?? null, $maxBytes, $allowed);
        $file = tempnam(sys_get_temp_dir(), 'teacher_');
        if ($file === false) {
            throw new RuntimeException('File sementara gagal dibuat.', 500);
        }
        try {
            $this->encodeImage($binary, $mime, $file);
            unset($binary);
            $size = filesize($file);
            if (!$size || $size > $maxBytes) {
                throw new RuntimeException('Ukuran gambar setelah diproses melebihi batas upload.', 413);
            }
            $name = bin2hex(random_bytes(16)) . '.' . $extension;
            $uploadedAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.v\Z');
            if ($source === 'Local Directory') {
                $directory = dirname(__DIR__) . '/assets/img/local_directory';
                if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
                    throw new RuntimeException('Direktori upload gagal dibuat.', 500);
                }
                $this->localFile = $directory . '/' . $name;
                // Mode x mencegah menimpa file yang sudah ada.
                $output = fopen($this->localFile, 'xb');
                if ($output === false) {
                    $this->localFile = null;
                    throw new RuntimeException('File gambar gagal disimpan.', 500);
                }
                try {
                    $contents = file_get_contents($file);
                    if ($contents === false || fwrite($output, $contents) !== $size) {
                        throw new RuntimeException('File gambar gagal disimpan.', 500);
                    }
                } finally {
                    fclose($output);
                }
                return ['file_name' => $name, 'file_size' => round($size / 1024, 3), 'file_mime_type' => $mime,
                    'file_extension' => '.' . $extension, 'file_upload_at' => $uploadedAt];
            }
            if ($source === 'Cloudinary') {
                $publicId = 'web_sekolah/' . $this->folder . '/' . pathinfo($name, PATHINFO_FILENAME);
                $result = $this->uploadCloudinary($file, $publicId, $extension);
                $this->cloudinaryId = $publicId;
                if (($result['resource_type'] ?? '') !== 'image' || ($result['public_id'] ?? '') !== $publicId
                    || !is_string($result['secure_url'] ?? null) || strpos($result['secure_url'], 'https://') !== 0
                    || !in_array(strtolower($result['format'] ?? ''), ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
                    throw new RuntimeException('Respons upload Cloudinary tidak valid.', 502);
                }
                return ['url' => $result['secure_url'], 'public_id' => $publicId, 'format' => $result['format'],
                    'resource_type' => 'Image', 'file_upload_at' => $uploadedAt];
            }
            $result = $this->imagekitRequest('POST', [
                'file' => new CURLFile($file, $mime, $name), 'fileName' => $name,
                'folder' => '/web_sekolah/' . $this->folder, 'useUniqueFileName' => 'true',
            ]);
            if (is_string($result['fileId'] ?? null) && $result['fileId'] !== '') {
                $this->imagekitId = $result['fileId'];
            }
            if ($this->imagekitId === null || ($result['fileType'] ?? '') !== 'image'
                || !is_string($result['url'] ?? null) || strpos($result['url'], 'https://') !== 0) {
                throw new RuntimeException('Respons upload Imagekit tidak valid.', 502);
            }
            return ['url' => $result['url'], 'file_id' => $this->imagekitId, 'format' => strtoupper($extension),
                'resource_type' => 'Image', 'file_upload_at' => $uploadedAt];
        } finally {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    private function decodeImage($base64, int $maxBytes, array $allowed): array
    {
        if (!is_string($base64) || trim($base64) === '') {
            throw new RuntimeException('Base64 gambar wajib diisi.', 400);
        }
        $base64 = trim($base64);
        $declaredMime = null;
        if (stripos($base64, 'data:') === 0) {
            if (!preg_match('~\Adata:(image/(?:jpeg|png|webp|gif));base64,~i', $base64, $match)) {
                throw new RuntimeException('Data URI harus berupa gambar JPG, PNG, WebP, atau GIF.', 415);
            }
            $declaredMime = strtolower($match[1]);
            $base64 = substr($base64, strlen($match[0]));
        }
        $base64 = preg_replace('/\s+/', '', $base64);
        if (strlen($base64) > 4 * (int) ceil($maxBytes / 3)) {
            throw new RuntimeException('Ukuran gambar melebihi batas upload storage.', 413);
        }
        $binary = base64_decode($base64, true);
        if ($binary === false || $binary === '' || rtrim(base64_encode($binary), '=') !== rtrim($base64, '=')) {
            throw new RuntimeException('Base64 tidak valid atau terpotong.', 400);
        }
        if (strlen($binary) > $maxBytes) {
            throw new RuntimeException('Ukuran gambar melebihi batas upload storage.', 413);
        }
        [$mime, $extension] = $this->inspectImage($binary, $allowed);
        if ($declaredMime !== null && $declaredMime !== $mime) {
            throw new RuntimeException('MIME pada Data URI berbeda dengan isi gambar.', 400);
        }
        return [$binary, $mime, $extension];
    }

    private function inspectImage(string $binary, array $allowed): array
    {
        if (!extension_loaded('fileinfo') || !extension_loaded('gd')) {
            throw new RuntimeException('Server memerlukan ekstensi Fileinfo dan GD.', 500);
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($binary);
        $allowed = array_map(static function ($format) { return $format === 'jpeg' ? 'jpg' : $format; }, $allowed);
        if (!isset(self::FORMATS[$mime]) || !in_array(self::FORMATS[$mime], $allowed, true)) {
            throw new RuntimeException('Hanya gambar dengan format yang diizinkan storage yang dapat digunakan.', 415);
        }
        $info = @getimagesizefromstring($binary);
        if (!$info || ($info['mime'] ?? '') !== $mime || $info[0] < 1 || $info[1] < 1
            || $info[0] > 6000 || $info[1] > 6000 || $info[0] * $info[1] > 8000000) {
            throw new RuntimeException('Gambar tidak valid; maksimal 6000 piksel per sisi dan total 8 megapiksel.', 400);
        }
        $image = @imagecreatefromstring($binary);
        if ($image === false) {
            throw new RuntimeException('Isi gambar rusak atau tidak dapat dibaca.', 400);
        }
        imagedestroy($image);
        return [$mime, self::FORMATS[$mime]];
    }

    private function encodeImage(string $binary, string $mime, string $file): void
    {
        $image = @imagecreatefromstring($binary);
        if ($image === false) {
            throw new RuntimeException('Isi gambar tidak dapat dibaca.', 400);
        }
        try {
            imagesavealpha($image, true);
            $encoders = ['image/jpeg' => 'imagejpeg', 'image/png' => 'imagepng', 'image/webp' => 'imagewebp', 'image/gif' => 'imagegif'];
            $encoder = $encoders[$mime];
            if (!function_exists($encoder) || !$encoder($image, $file)) {
                throw new RuntimeException('Gambar gagal diproses oleh server.', 500);
            }
        } finally {
            imagedestroy($image);
        }
    }

    private function normalizeUrl($url): string
    {
        if (!is_string($url) || trim($url) === '') {
            throw new RuntimeException('Image_url wajib diisi.', 400);
        }
        $url = trim($url);
        if (!preg_match('~^[a-z][a-z0-9+.-]*:~i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }
        $parts = parse_url($url);
        if (!filter_var($url, FILTER_VALIDATE_URL) || !$parts
            || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            throw new RuntimeException('Image_url harus berupa URL HTTP/HTTPS tanpa kredensial atau fragmen.', 400);
        }
        return $url;
    }

    protected function fetchExternalImage(string $url): string
    {
        if (!extension_loaded('curl')) {
            throw new RuntimeException('Server memerlukan ekstensi cURL.', 500);
        }
        for ($redirect = 0; $redirect <= 3; $redirect++) {
            $url = $this->normalizeUrl($url);
            $parts = parse_url($url);
            $host = trim($parts['host'], '[]');
            $port = $parts['port'] ?? (strtolower($parts['scheme']) === 'https' ? 443 : 80);
            if (!in_array($port, [80, 443], true)) {
                throw new RuntimeException('Port URL gambar tidak diizinkan.', 400);
            }
            // Validasi DNS dan pin IP saat koneksi agar URL tidak mengakses jaringan internal.
            $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : [];
            if (!$ips) {
                foreach (dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $record) {
                    $ips[] = $record['ip'] ?? $record['ipv6'] ?? '';
                }
            }
            if (!$ips) {
                throw new RuntimeException('Host gambar tidak dapat ditemukan.', 422);
            }
            foreach ($ips as $ip) {
                if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
                    || (strpos($ip, ':') !== false && !preg_match('/^[23][0-9a-f]{3}:/i', $ip))
                    || (strpos($ip, ':') === false && (
                        (int) explode('.', $ip)[0] >= 224
                        || preg_match('/^100\.(?:6[4-9]|[7-9][0-9]|1[01][0-9]|12[0-7])\./', $ip)
                        || preg_match('/^198\.(?:18|19)\./', $ip)
                    ))) {
                    throw new RuntimeException('URL gambar harus menggunakan alamat jaringan publik.', 400);
                }
            }
            $body = '';
            $tooLarge = false;
            $handle = curl_init($url);
            $address = strpos($ips[0], ':') !== false ? '[' . $ips[0] . ']' : $ips[0];
            curl_setopt_array($handle, [
                CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 15, CURLOPT_PROXY => '',
                CURLOPT_RESOLVE => [$parts['host'] . ':' . $port . ':' . $address],
                CURLOPT_WRITEFUNCTION => static function ($handle, $chunk) use (&$body, &$tooLarge) {
                    if (strlen($body) + strlen($chunk) > 5 * 1024 * 1024) {
                        $tooLarge = true;
                        return 0;
                    }
                    $body .= $chunk;
                    return strlen($chunk);
                },
            ]);
            try {
                $ok = curl_exec($handle);
                $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
                $next = curl_getinfo($handle, CURLINFO_REDIRECT_URL);
            } finally {
                curl_close($handle);
            }
            if ($tooLarge) {
                throw new RuntimeException('Gambar eksternal melebihi batas verifikasi 5 MB.', 413);
            }
            if ($ok === false) {
                throw new RuntimeException('URL gambar tidak dapat diakses.', 422);
            }
            if ($status >= 300 && $status < 400 && $next && $redirect < 3) {
                $url = $next;
                continue;
            }
            if ($status < 200 || $status >= 300 || $body === '') {
                throw new RuntimeException('URL gambar tidak dapat diakses atau responsnya tidak valid.', 422);
            }
            return $body;
        }
        throw new RuntimeException('Terlalu banyak pengalihan URL gambar.', 422);
    }

    protected function uploadCloudinary(string $file, string $publicId, string $extension): array
    {
        $cloudinary = $this->cloudinaryClient();
        try {
            $result = $cloudinary->uploadApi()->upload($file, [
                'public_id' => $publicId, 'resource_type' => 'image', 'overwrite' => false,
                'allowed_formats' => [$extension], 'timeout' => 60,
            ]);
            return $result->getArrayCopy();
        } catch (Throwable $error) {
            // Timeout dapat berarti file sudah tersimpan; ID unik membantu rekonsiliasi.
            error_log('ImageStorage: periksa unggahan Cloudinary ' . $publicId);
            throw new RuntimeException('Upload gambar ke Cloudinary gagal.', 502);
        }
    }

    private function cloudinaryClient(): \Cloudinary\Cloudinary
    {
        if ($this->cloudinary !== null) {
            return $this->cloudinary;
        }
        foreach (['cloudinary_cloud_name', 'cloudinary_api_key', 'cloudinary_api_secret'] as $key) {
            if (empty($this->config[$key])) {
                throw new RuntimeException('Konfigurasi Cloudinary belum lengkap.', 500);
            }
        }
        $this->cloudinary = new \Cloudinary\Cloudinary(['cloud' => [
            'cloud_name' => $this->config['cloudinary_cloud_name'], 'api_key' => $this->config['cloudinary_api_key'],
            'api_secret' => $this->config['cloudinary_api_secret'],
        ], 'url' => ['secure' => true]]);
        return $this->cloudinary;
    }

    protected function imagekitRequest(string $method, array $fields = [], ?string $fileId = null): array
    {
        if (empty($this->config['imagekit_private_key']) || !extension_loaded('curl')) {
            throw new RuntimeException('Konfigurasi Imagekit atau ekstensi cURL belum tersedia.', 500);
        }
        $url = $method === 'POST' ? 'https://upload.imagekit.io/api/v1/files/upload'
            : 'https://api.imagekit.io/v1/files/' . rawurlencode($fileId);
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_USERPWD => $this->config['imagekit_private_key'] . ':', CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 60, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ]);
        if ($method === 'POST') {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $fields);
        }
        try {
            $body = curl_exec($handle);
            $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        } finally {
            curl_close($handle);
        }
        // DELETE bersifat idempoten: file yang sudah hilang tidak menghalangi penghapusan data.
        if ($method === 'DELETE' && $body !== false && $status === 404) {
            return [];
        }
        if ($body === false || $status < 200 || $status >= 300) {
            // Jangan bocorkan respons provider yang dapat berisi data sensitif.
            error_log('ImageStorage: operasi Imagekit gagal; method=' . $method . '; HTTP=' . $status);
            throw new RuntimeException('Operasi penyimpanan Imagekit gagal.', 502);
        }
        if ($method === 'DELETE') {
            return [];
        }
        $result = json_decode($body, true);
        if (!is_array($result)) {
            throw new RuntimeException('Respons Imagekit tidak valid.', 502);
        }
        return $result;
    }

    protected function deleteCloudinary(string $publicId): void
    {
        $cloudinary = $this->cloudinaryClient();
        try {
            $result = $cloudinary->uploadApi()->destroy($publicId, ['resource_type' => 'image', 'invalidate' => true, 'timeout' => 15]);
        } catch (Throwable $error) {
            throw new RuntimeException('Penghapusan gambar Cloudinary gagal.', 502);
        }
        if (!in_array($result['result'] ?? '', ['ok', 'not found'], true)) {
            throw new RuntimeException('Penghapusan gambar Cloudinary gagal.', 502);
        }
    }

    /** Hapus aset yang telah tercatat. Pemanggil memastikan file tidak dipakai data lain. */
    public function deleteStoredFile(string $source, $metadata): string
    {
        if ($source === 'External Link') {
            return 'external_link'; // File dikelola pemilik URL, cukup hapus catatan database.
        }
        $prefixes = ['Local Directory' => 'local_directory', 'Cloudinary' => 'cloudinary', 'Imagekit' => 'imagekit'];
        if (!isset($prefixes[$source])) {
            throw new RuntimeException('Sumber file tidak dikenal; penghapusan dibatalkan.', 409);
        }
        if (($this->config[$prefixes[$source] . '_status'] ?? 'Inactive') !== 'Active') {
            return 'skipped_inactive';
        }
        if (!is_object($metadata)) {
            throw new RuntimeException('Metadata file tidak valid; penghapusan dibatalkan.', 409);
        }
        if ($source === 'Local Directory') {
            $name = $metadata->file_name ?? null;
            if (!is_string($name) || $name === '' || $name === '.' || $name === '..'
                || preg_match('/[\x00-\x1F\x7F\\\\\/:]/', $name)) {
                throw new RuntimeException('Nama file lokal tidak valid.', 409);
            }
            $directory = dirname(__DIR__) . '/assets/img/local_directory';
            $file = $directory . '/' . $name;
            if (is_link($file)) {
                throw new RuntimeException('File lokal berupa symbolic link; penghapusan dibatalkan.', 409);
            }
            if (!file_exists($file)) {
                return 'not_found';
            }
            $resolved = realpath($file);
            $root = realpath($directory);
            if ($resolved === false || $root === false || dirname($resolved) !== $root || !is_file($resolved)) {
                throw new RuntimeException('Lokasi file lokal tidak valid.', 409);
            }
            if (!unlink($resolved)) {
                throw new RuntimeException('File lokal gagal dihapus.', 500);
            }
        } else {
            $key = $source === 'Cloudinary' ? 'public_id' : 'file_id';
            $id = $metadata->$key ?? null;
            if (!is_string($id) || trim($id) === '' || ($metadata->resource_type ?? '') !== 'Image') {
                throw new RuntimeException('Identitas atau tipe file pada metadata tidak valid.', 409);
            }
            if ($source === 'Cloudinary') {
                $this->deleteCloudinary($id);
            } else {
                $this->imagekitRequest('DELETE', [], $id);
            }
        }
        return 'deleted';
    }

    /** Hanya panggil jika insert belum dimulai atau rollback berhasil dikonfirmasi. */
    public function cleanup(): void
    {
        if ($this->localFile !== null && is_file($this->localFile) && !unlink($this->localFile)) {
            throw new RuntimeException('Pembersihan file lokal gagal.');
        }
        if ($this->cloudinaryId !== null) {
            $this->deleteCloudinary($this->cloudinaryId);
        }
        if ($this->imagekitId !== null) {
            $this->imagekitRequest('DELETE', [], $this->imagekitId);
        }
    }
}
