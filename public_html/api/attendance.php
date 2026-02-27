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

        // 대리출석 감지: 같은 fingerprint + 같은 IP로 다른 학생이 이미 출석했는지 확인
        // (같은 기종이라도 IP가 다르면 다른 장소/기기로 판단하여 허용)
        $clientIP = getClientIP();
        $proxyWarning = null;
        if ($fingerprint) {
            $stmt = $db->prepare('
                SELECT qa.student_id, s.name as attended_name, s.phone_last4 as attended_phone_last4
                FROM junior_qr_attendance qa
                JOIN junior_students s ON qa.student_id = s.id
                WHERE qa.qr_session_id = ? AND qa.fingerprint = ? AND qa.ip_address = ? AND qa.student_id != ? AND qa.status != "removed"
                LIMIT 1
            ');
            $stmt->execute([$qrSession['id'], $fingerprint, $clientIP, $studentId]);
            $existingAttendance = $stmt->fetch();

            if ($existingAttendance) {
                // 형제자매 예외 확인 (phone_last4 + 성 일치)
                $stmt = $db->prepare('SELECT phone_last4, SUBSTRING(name, 1, 1) as surname FROM junior_students WHERE id = ?');
                $stmt->execute([$studentId]);
                $attemptStudent = $stmt->fetch();

                $existingSurname = mb_substr($existingAttendance['attended_name'], 0, 1);
                $isSibling = $attemptStudent
                    && $attemptStudent['phone_last4']
                    && $attemptStudent['phone_last4'] === $existingAttendance['attended_phone_last4']
                    && $attemptStudent['surname'] === $existingSurname;

                if (!$isSibling) {
                    // 대리출석 의심 로그 기록 (코치가 확인 가능)
                    $stmt = $db->prepare('
                        INSERT INTO junior_qr_log (qr_session_id, event_type, student_id, detail, ip_address)
                        VALUES (?, "warn_proxy", ?, ?, ?)
                    ');
                    $stmt->execute([
                        $qrSession['id'], $studentId,
                        json_encode([
                            'attempted_student' => $studentName,
                            'existing_student_id' => (int)$existingAttendance['student_id'],
                            'existing_student_name' => $existingAttendance['attended_name'],
                            'fingerprint' => $fingerprint
                        ], JSON_UNESCAPED_UNICODE),
                        $clientIP
                    ]);

                    // 출석은 허용하되 경고 메시지 전달
                    $proxyWarning = '⚠️ 다른 친구 핸드폰으로 출석한 것 같아! 다음부터는 꼭 본인 핸드폰으로 출석해줘. 코치 선생님도 확인할 수 있어!';
                }
            }
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

        // 출석 기록 (UNIQUE 인덱스로 동시 요청 시 중복 방지)
        $stmt = $db->prepare('
            INSERT IGNORE INTO junior_qr_attendance
            (qr_session_id, student_id, class_id, is_home_class, status, approved_at, fingerprint, ip_address)
            VALUES (?, ?, ?, ?, "approved", NOW(), ?, ?)
        ');
        $stmt->execute([
            $qrSession['id'], $studentId, $qrSession['class_id'],
            $isHomeClass, $fingerprint, $clientIP
        ]);

        // INSERT IGNORE로 0행이면 동시 요청에 의한 중복 → 이미 출석 처리
        if ($stmt->rowCount() === 0) {
            jsonSuccess(['already' => true, 'student_name' => $studentName], '이미 출석했어!');
            break;
        }

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

        // 열정왕 카드 먼저 지급 (체크리스트 기반 한도 체크이므로 체크리스트 업데이트 전에 실행)
        $cardWarning = null;
        $passionBlocked = false;
        if ($autoPassion) {
            $result = changeReward($studentId, 'passion', 1, 'qr_attendance', 'QR 출석 자동 부여', null, 'auto');
            if (!$result['success']) {
                $passionBlocked = true;
                if ($result['error'] === 'weekly_limit_exceeded') {
                    $cardWarning = '열정왕 카드는 이번 주 다 받았어!';
                }
            }
        }

        // 카드가 한도 등으로 차단된 경우 zoom_attendance도 올리지 않음
        // (zoom_attendance = 카드 사용량 기준이므로 실제 지급과 동기화 필요)
        if ($autoZoom && !$passionBlocked) {
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

        $responseData = [
            'student_name'  => $studentName,
            'is_home_class' => $isHomeClass,
            'already'       => false,
        ];
        if ($cardWarning) {
            $responseData['card_warning'] = $cardWarning;
        }
        if ($proxyWarning) {
            $responseData['proxy_warning'] = $proxyWarning;
        }

        // 메시지 우선순위: 대리출석 경고 > 카드 경고 > 기본
        $message = '출석 완료!';
        if ($proxyWarning) {
            $message = $proxyWarning;
        } elseif ($cardWarning) {
            $message = '출석 완료! 열정왕 카드는 이번 주 다 받았어.';
        }
        jsonSuccess($responseData, $message);
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
