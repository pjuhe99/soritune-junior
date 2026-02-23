<?php
/**
 * QR 세션 CRUD
 */
require_once __DIR__ . '/config.php';

/**
 * QR 세션 생성
 */
function createQRSession(int $classId, int $createdBy, string $sessionType = 'basic', ?int $expiryMinutes = null): array {
    $db = getDB();

    // 기존 활성 세션이 있으면 닫기
    $stmt = $db->prepare('UPDATE junior_qr_sessions SET status = "closed", closed_at = NOW() WHERE class_id = ? AND status = "active"');
    $stmt->execute([$classId]);

    $sessionCode = generateQRSessionCode();
    $expiry = $expiryMinutes ?? getSetting('qr_expiry_minutes', QR_DEFAULT_EXPIRY_MINUTES);

    $stmt = $db->prepare('
        INSERT INTO junior_qr_sessions (session_code, class_id, session_type, created_by, status, expires_at)
        VALUES (?, ?, ?, ?, "active", DATE_ADD(NOW(), INTERVAL ? MINUTE))
    ');
    $stmt->execute([$sessionCode, $classId, $sessionType, $createdBy, $expiry]);
    $sessionId = $db->lastInsertId();

    // QR 이미지 생성
    $renderer = new QRRenderer();
    $url = getQRScanURL($sessionCode);
    $qrImage = $renderer->generateBase64($url, 300);

    // QR 로그
    $stmt = $db->prepare('
        INSERT INTO junior_qr_log (qr_session_id, event_type, admin_id, detail, ip_address)
        VALUES (?, "session_created", ?, ?, ?)
    ');
    $stmt->execute([$sessionId, $createdBy, json_encode(['class_id' => $classId, 'type' => $sessionType]), getClientIP()]);

    return [
        'session_id'   => $sessionId,
        'session_code' => $sessionCode,
        'qr_image'     => $qrImage,
        'scan_url'     => $url,
        'expires_at'   => date('Y-m-d H:i:s', time() + $expiry * 60),
    ];
}

/**
 * QR 세션 검증
 */
function verifyQRSession(string $sessionCode): ?array {
    $db = getDB();
    $stmt = $db->prepare('
        SELECT qs.*, c.display_name as class_name
        FROM junior_qr_sessions qs
        JOIN junior_classes c ON qs.class_id = c.id
        WHERE qs.session_code = ?
    ');
    $stmt->execute([$sessionCode]);
    $session = $stmt->fetch();

    if (!$session) return null;

    // 만료 확인
    if ($session['status'] === 'active' && strtotime($session['expires_at']) < time()) {
        $stmt = $db->prepare('UPDATE junior_qr_sessions SET status = "expired" WHERE id = ?');
        $stmt->execute([$session['id']]);
        $session['status'] = 'expired';
    }

    return $session;
}

/**
 * QR 세션 닫기
 */
function closeQRSession(int $sessionId, int $adminId): bool {
    $db = getDB();
    $stmt = $db->prepare('UPDATE junior_qr_sessions SET status = "closed", closed_at = NOW() WHERE id = ? AND status = "active"');
    $stmt->execute([$sessionId]);

    if ($stmt->rowCount() > 0) {
        $stmt = $db->prepare('
            INSERT INTO junior_qr_log (qr_session_id, event_type, admin_id, ip_address)
            VALUES (?, "session_closed", ?, ?)
        ');
        $stmt->execute([$sessionId, $adminId, getClientIP()]);
        return true;
    }
    return false;
}
