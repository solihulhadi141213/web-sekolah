<?php
// Aset lokal melewati PHP supaya header cache mengikuti config.php,
// termasuk font/gambar yang diminta dari dalam stylesheet.
$config = require dirname(__DIR__) . '/_Config/config.php';
$isDevelopment = strtoupper($config['mode_environment'] ?? 'PRODUCTION') === 'DEVELOPMENT';
require __DIR__ . '/CacheHeaders.php';
$path = $_GET['asset'] ?? '';
$root = realpath(dirname(__DIR__));
$file = is_string($path) ? realpath($root . '/' . $path) : false;
$types = [
    'css' => 'text/css; charset=utf-8', 'js' => 'application/javascript; charset=utf-8',
    'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
    'gif' => 'image/gif', 'webp' => 'image/webp', 'avif' => 'image/avif',
    'svg' => 'image/svg+xml', 'ico' => 'image/x-icon',
    'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf', 'otf' => 'font/otf',
    'webmanifest' => 'application/manifest+json',
];
$allowed = false;
foreach (['assets', 'node_modules'] as $directory) {
    $prefix = $root . DIRECTORY_SEPARATOR . $directory . DIRECTORY_SEPARATOR;
    if ($file !== false && strpos($file, $prefix) === 0) $allowed = true;
}
$extension = $file === false ? '' : strtolower(pathinfo($file, PATHINFO_EXTENSION));
if (!$allowed || !is_file($file) || !isset($types[$extension])) {
    http_response_code(404);
    exit;
}
header('Content-Type: ' . $types[$extension]);
header('X-Content-Type-Options: nosniff');
if (!$isDevelopment) {
    $etag = '"' . hash_file('sha256', $file) . '"';
    header('ETag: ' . $etag);
    $tags = array_map('trim', explode(',', $_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
    if (in_array($etag, $tags, true) || in_array('*', $tags, true)) {
        http_response_code(304);
        exit;
    }
}
header('Content-Length: ' . filesize($file));
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') readfile($file);
