<?php
/**
 * QR 모듈 독립 API
 * 재사용 가능한 QR 세션 관리
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/session.php';
require_once dirname(__DIR__) . '/auth.php';

header('Content-Type: application/json; charset=utf-8');

$action = getAction();
$method = getMethod();

switch ($action) {
    // QR 세션 생성
    case 'create':
        if ($method !== 'POST') jsonError('POST만 허용됩니다', 405);
        $admin = requireAdmin(['coach']);
        $input = getJsonInput();

        $classId = (int)($input['class_id'] ?? 0);
        $sessionType = in_array($input['session_type'] ?? '', ['basic', 'zoom_special']) ? $input['session_type'] : 'basic';

        if (!$classId) jsonError('반을 선택해 주세요');

        $result = createQRSession($classId, $admin['admin_id'], $sessionType);
        jsonSuccess($result, 'QR 세션이 생성되었습니다');
        break;

    // 세션 유효성 확인
    case 'verify':
        $code = trim($_GET['code'] ?? '');
        if (!$code) jsonError('세션 코드가 필요합니다');

        $session = verifyQRSession($code);
        if (!$session) jsonError('세션을 찾을 수 없습니다', 404);

        jsonSuccess([
            'session' => [
                'id'           => $session['id'],
                'status'       => $session['status'],
                'class_id'     => $session['class_id'],
                'class_name'   => $session['class_name'],
                'session_type' => $session['session_type'],
                'expires_at'   => $session['expires_at'],
            ]
        ]);
        break;

    // 세션 종료
    case 'close':
        if ($method !== 'POST') jsonError('POST만 허용됩니다', 405);
        $admin = requireAdmin(['coach']);
        $input = getJsonInput();
        $sessionId = (int)($input['session_id'] ?? 0);

        if (!$sessionId) jsonError('세션 ID가 필요합니다');

        if (closeQRSession($sessionId, $admin['admin_id'])) {
            jsonSuccess([], 'QR 세션이 종료되었습니다');
        } else {
            jsonError('활성 세션을 찾을 수 없습니다');
        }
        break;

    // 세션 상태
    case 'status':
        $admin = requireAdmin(['coach']);
        $classId = (int)($_GET['class_id'] ?? 0);

        if (!$classId) jsonError('반 ID가 필요합니다');

        $db = getDB();
        $stmt = $db->prepare('
            SELECT qs.*, c.display_name as class_name
            FROM junior_qr_sessions qs
            JOIN junior_classes c ON qs.class_id = c.id
            WHERE qs.class_id = ? AND qs.status = "active"
            ORDER BY qs.created_at DESC LIMIT 1
        ');
        $stmt->execute([$classId]);
        $session = $stmt->fetch();

        if ($session && strtotime($session['expires_at']) < time()) {
            $stmt = $db->prepare('UPDATE junior_qr_sessions SET status = "expired" WHERE id = ?');
            $stmt->execute([$session['id']]);
            $session = null;
        }

        if ($session) {
            // QR 이미지 생성
            $renderer = new QRRenderer();
            $url = getQRScanURL($session['session_code']);
            $qrImage = $renderer->generateBase64($url, 300);

            jsonSuccess([
                'active' => true,
                'session' => [
                    'id'           => $session['id'],
                    'session_code' => $session['session_code'],
                    'class_name'   => $session['class_name'],
                    'session_type' => $session['session_type'],
                    'expires_at'   => $session['expires_at'],
                    'created_at'   => $session['created_at'],
                ],
                'qr_image' => $qrImage,
                'scan_url' => $url,
            ]);
        } else {
            jsonSuccess(['active' => false]);
        }
        break;

    default:
        jsonError('알 수 없는 요청입니다', 404);
}
