<?php
// Hanya konfigurasi tampilan publik; tidak mengirim kredensial ke browser.
$baseUrl = rtrim($config['base_url'], '/');
$isDevelopment = strtoupper($config['mode_environment'] ?? 'PRODUCTION') === 'DEVELOPMENT';
$escape = static function ($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
$siteUrl = static function ($path = '') use ($baseUrl) {
    return $baseUrl . '/' . ltrim($path, '/');
};
$pageUrl = static function ($page = '') use ($siteUrl) {
    return $siteUrl('index.php' . ($page === '' ? '' : '/' . implode('/', array_map('rawurlencode', explode('/', trim($page, '/'))))));
};
$assetVersion = $isDevelopment ? bin2hex(random_bytes(8)) : null;
$assetUrl = static function ($path) use ($siteUrl, $assetVersion) {
    $path = explode('?', $path, 2)[0];
    $file = dirname(__DIR__) . '/' . $path;
    $version = $assetVersion ?? (is_file($file) ? (string) filemtime($file) : '1');
    return $siteUrl($path) . '?v=' . rawurlencode($version);
};
