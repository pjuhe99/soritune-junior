<?php
/**
 * QR 모듈 설정
 */

require_once dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/lib/QRRenderer.php';

// QR 모듈 상수
define('QR_BASE_URL', 'https://j.soritune.com/qr/scan.php');
define('QR_CODE_LENGTH', 24);  // bin2hex(random_bytes(12)) = 24자 hex
define('QR_DEFAULT_EXPIRY_MINUTES', 120);

/**
 * QR 세션 코드 생성 (96비트 엔트로피)
 */
function generateQRSessionCode(): string {
    return bin2hex(random_bytes(12));
}

/**
 * QR URL 생성
 */
function getQRScanURL(string $sessionCode): string {
    return QR_BASE_URL . '?code=' . $sessionCode;
}
