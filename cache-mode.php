<?php
declare(strict_types=1);

$config = require __DIR__ . '/_Config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

echo json_encode([
    'mode_environment' => strtoupper((string) ($config['mode_environment'] ?? 'PRODUCTION')),
], JSON_THROW_ON_ERROR);