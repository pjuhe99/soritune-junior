<?php
/**
 * QR 이미지 생성 엔드포인트
 * GET /qr/generate.php?code=xxx
 */
require_once __DIR__ . '/config.php';

$code = trim($_GET['code'] ?? '');
if (!$code) {
    http_response_code(400);
    die('Missing code');
}

$renderer = new QRRenderer();
$url = getQRScanURL($code);

// base64 이미지 반환
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'image'   => $renderer->generateBase64($url, 300),
    'url'     => $url,
], JSON_UNESCAPED_SLASHES);
