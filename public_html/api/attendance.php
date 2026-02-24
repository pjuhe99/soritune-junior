<?php
/**
 * QR 출석 기록 API
 * 학생이 QR 스캔 시 출석 처리
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../qr/session.php';

header('Content-Type: application/json; charset=utf-8');

$action = getAction();
$method = getMethod();

switch ($action) {
    // 출석 기록
    case 'record':
        if ($method !== 'POST') jsonError('POST만 허용됩니다', 405);

        $input = getJsonInput();
        $sessionCode = trim($input['session_code'] ?? '');
        $fingerprint = trim($input['fingerprint'] ?? '');
        $directStudentId = (int)($input['student_id'] ?? 0);

        if (!$sessionCode) jsonError('세션 코드가 필요합니다');

        // QR 스캔에서 직접 student_id를 전달하거나, 기존 세션에서 가져오기
        if ($directStudentId) {
            $db = getDB();
            $stmt = $db->prepare('SELECT id, name FROM junior_students WHERE id = ? AND is_active = 1');
            $stmt->execute([$directStudentId]);
            $directStudent = $stmt->fetch();
            if (!$directStudent) jsonError('학생 정보를 찾을 수 없습니다');
            $studentId = $directStudent['id'];
            $studentName = $directStudent['name'];
        } else {
            $session = getStudentSession();
            if (!$session) {
                jsonError('먼저 로그인해줘!', 401);
            }
            $studentId = $session['student_id'];
            $studentName = $session['student_name'];
        }

        // QR 세션 검증
        $qrSession = verifyQRSession($sessionCode);
        if (!$qrSession) jsonError('🔧 잠깐 문제가 생겼어. 다시 해보자!');
        if ($qrSession['status'] !== 'active') jsonError('⏰ 시간이 지났어. 다시 해보자!');

        $db = getDB();

        // 중복 출석 확인
        $stmt = $db->prepare('
            SELECT id FROM junior_qr_attendance
            WHERE qr_session_id = ? AND student_id = ? AND status != "removed"
        ');
        $stmt->execute([$qrSession['id'], $studentId]);
        if ($stmt->fetch()) {
            jsonSuccess(['already' => true, 'student_name' => $studentName], '이미 출석했어!');
            break;
        }

        // 본반/타반 확인
        $stmt = $db->prepare('
            SELECT cs.is_primary
            FROM junior_class_students cs
            WHERE cs.class_id = ? AND cs.student_id = ? AND cs.is_active = 1
        ');
        $stmt->execute([$qrSession['class_id'], $studentId]);
        $classInfo = $stmt->fetch();
        $isHomeClass = $classInfo ? (int)$classInfo['is_primary'] : 0;

        // 출석 기록
        $stmt = $db->prepare('
            INSERT INTO junior_qr_attendance
            (qr_session_id, student_id, class_id, is_home_class, status, approved_at, fingerprint, ip_address)
            VALUES (?, ?, ?, ?, "approved", NOW(), ?, ?)
        ');
        $stmt->execute([
            $qrSession['id'], $studentId, $qrSession['class_id'],
            $isHomeClass, $fingerprint, getClientIP()
        ]);

        // QR 로그
        $stmt = $db->prepare('
            INSERT INTO junior_qr_log (qr_session_id, event_type, student_id, detail, ip_address)
            VALUES (?, "scanned", ?, ?, ?)
        ');
        $stmt->execute([
            $qrSession['id'], $studentId,
            json_encode(['is_home_class' => $isHomeClass], JSON_UNESCAPED_UNICODE),
            getClientIP()
        ]);

        // 자동화: 줌출석 체크 + 열정왕 카드
        $today = date('Y-m-d');
        $autoZoom = getSetting('attendance_auto_zoom', true);
        $autoPassion = getSetting('attendance_auto_passion', true);

        if ($autoZoom) {
            // 학생의 본반 + 담당 코치 조회
            $stmt = $db->prepare('
                SELECT cs.class_id, ac.admin_id as coach_id
                FROM junior_class_students cs
                LEFT JOIN junior_admin_classes ac ON cs.class_id = ac.class_id
                WHERE cs.student_id = ? AND cs.is_active = 1 AND cs.is_primary = 1
                LIMIT 1
            ');
            $stmt->execute([$studentId]);
            $homeClass = $stmt->fetch();

            $checkClassId = $homeClass ? (int)$homeClass['class_id'] : (int)$qrSession['class_id'];
            $checkCoachId = $homeClass ? (int)$homeClass['coach_id'] : (int)$qrSession['created_by'];

            // coach_id FK 검증: junior_admins에 존재하는지 확인
            if ($checkCoachId > 0) {
                $coachCheck = $db->prepare('SELECT id FROM junior_admins WHERE id = ?');
                $coachCheck->execute([$checkCoachId]);
                if (!$coachCheck->fetch()) {
                    $checkCoachId = null;
                }
            } else {
                $checkCoachId = null;
            }

            // 오늘의 체크리스트에 줌출석 +1
            $stmt = $db->prepare('
                SELECT id FROM junior_daily_checklist
                WHERE student_id = ? AND check_date = ? AND class_id = ?
            ');
            $stmt->execute([$studentId, $today, $checkClassId]);
            $checklist = $stmt->fetch();

            if ($checklist) {
                $stmt = $db->prepare('UPDATE junior_daily_checklist SET zoom_attendance = zoom_attendance + 1 WHERE id = ?');
                $stmt->execute([$checklist['id']]);
            } else {
                $stmt = $db->prepare('
                    INSERT INTO junior_daily_checklist (student_id, class_id, check_date, coach_id, zoom_attendance)
                    VALUES (?, ?, ?, ?, 1)
                ');
                $stmt->execute([$studentId, $checkClassId, $today, $checkCoachId]);
            }
        }

        $cardWarning = null;
        if ($autoPassion) {
            // 열정왕 카드 자동 부여 (주간 한도 체크)
            $result = changeReward($studentId, 'passion', 1, 'qr_attendance', 'QR 출석 자동 부여', null, 'auto');
            if (!$result['success'] && $result['error'] === 'weekly_limit_exceeded') {
                $cardWarning = '열정왕 카드는 이번 주 다 받았어!';
            }
        }

        $responseData = [
            'student_name'  => $studentName,
            'is_home_class' => $isHomeClass,
            'already'       => false,
        ];
        if ($cardWarning) {
            $responseData['card_warning'] = $cardWarning;
        }
        jsonSuccess($responseData, $cardWarning
            ? '출석 완료! 열정왕 카드는 이번 주 다 받았어.'
            : '출석 완료!');
        break;

    // 세션별 출석자 목록 (코치용)
    case 'attendees':
        $sessionId = (int)($_GET['session_id'] ?? 0);
        if (!$sessionId) jsonError('세션 ID가 필요합니다');

        $db = getDB();
        $stmt = $db->prepare('
            SELECT qa.id, qa.student_id, qa.is_home_class, qa.status, qa.scanned_at,
                   s.name as student_name,
                   c.display_name as home_class_name
            FROM junior_qr_attendance qa
            JOIN junior_students s ON qa.student_id = s.id
            LEFT JOIN junior_class_students cs ON s.id = cs.student_id AND cs.is_primary = 1 AND cs.is_active = 1
            LEFT JOIN junior_classes c ON cs.class_id = c.id
            WHERE qa.qr_session_id = ?
            ORDER BY qa.scanned_at DESC
        ');
        $stmt->execute([$sessionId]);
        $attendees = $stmt->fetchAll();

        jsonSuccess(['attendees' => $attendees]);
        break;

    default:
        jsonError('앗, 뭔가 이상해! 다시 해보자.', 404);
}
