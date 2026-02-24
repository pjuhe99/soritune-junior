<?php
/**
 * 코치 API
 * 로그인, 대시보드, 체크리스트, QR, 학생 프로필, 카드 수정
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../qr/session.php';

header('Content-Type: application/json; charset=utf-8');

$action = getAction();
$method = getMethod();

switch ($action) {
    // 코치 로그인
    case 'login':
        if ($method !== 'POST') jsonError('POST만 허용됩니다', 405);
        $input = getJsonInput();
        $loginId = trim($input['login_id'] ?? '');
        $password = trim($input['password'] ?? '');
        $fingerprint = trim($input['fingerprint'] ?? '');
        $deviceInfo = $input['device_info'] ?? null;

        if (!$loginId || !$password) jsonError('아이디와 비밀번호를 입력해 주세요');

        $db = getDB();
        $stmt = $db->prepare('SELECT * FROM junior_admins WHERE login_id = ? AND is_active = 1');
        $stmt->execute([$loginId]);
        $admin = $stmt->fetch();

        if (!$admin || !$admin['password_hash'] || !password_verify($password, $admin['password_hash'])) {
            jsonError('아이디 또는 비밀번호가 올바르지 않습니다');
        }

        loginAdmin($admin['id'], $admin['name'], $admin['role']);

        // 핑거프린트 저장
        if ($fingerprint) {
            $deviceInfoJson = $deviceInfo ? (is_string($deviceInfo) ? $deviceInfo : json_encode($deviceInfo, JSON_UNESCAPED_UNICODE)) : null;
            $stmt = $db->prepare('
                INSERT INTO junior_admin_fingerprints (admin_id, fingerprint, device_info, is_active, last_used_at)
                VALUES (?, ?, ?, 1, NOW())
                ON DUPLICATE KEY UPDATE is_active = 1, last_used_at = NOW(), device_info = VALUES(device_info)
            ');
            $stmt->execute([$admin['id'], $fingerprint, $deviceInfoJson]);
        }

        // 담당 반 정보
        $stmt = $db->prepare('
            SELECT c.id, c.display_name
            FROM junior_admin_classes ac
            JOIN junior_classes c ON ac.class_id = c.id
            WHERE ac.admin_id = ? AND ac.is_active = 1
        ');
        $stmt->execute([$admin['id']]);
        $classes = $stmt->fetchAll();

        jsonSuccess([
            'admin' => [
                'id'   => $admin['id'],
                'name' => $admin['name'],
                'role' => $admin['role'],
            ],
            'classes' => $classes,
        ], '로그인 되었습니다');
        break;

    // 핑거프린트 자동 로그인
    case 'auto_login':
        if ($method !== 'POST') jsonError('POST만 허용됩니다', 405);
        $input = getJsonInput();
        $fingerprint = trim($input['fingerprint'] ?? '');

        if (!$fingerprint) jsonError('핑거프린트가 필요합니다');

        $db = getDB();
        $stmt = $db->prepare('
            SELECT af.admin_id, a.name, a.role, a.is_active
            FROM junior_admin_fingerprints af
            JOIN junior_admins a ON af.admin_id = a.id
            WHERE af.fingerprint = ? AND af.is_active = 1 AND a.is_active = 1
            ORDER BY af.last_used_at DESC
            LIMIT 1
        ');
        $stmt->execute([$fingerprint]);
        $match = $stmt->fetch();

        if (!$match) {
            jsonSuccess(['logged_in' => false]);
            break;
        }

        // last_used_at 갱신
        $stmt = $db->prepare('UPDATE junior_admin_fingerprints SET last_used_at = NOW() WHERE fingerprint = ? AND admin_id = ?');
        $stmt->execute([$fingerprint, $match['admin_id']]);

        // 세션 생성
        loginAdmin($match['admin_id'], $match['name'], $match['role']);

        // 담당 반 정보
        $stmt = $db->prepare('
            SELECT c.id, c.display_name
            FROM junior_admin_classes ac
            JOIN junior_classes c ON ac.class_id = c.id
            WHERE ac.admin_id = ? AND ac.is_active = 1
        ');
        $stmt->execute([$match['admin_id']]);
        $classes = $stmt->fetchAll();

        jsonSuccess([
            'logged_in' => true,
            'admin' => [
                'id'   => $match['admin_id'],
                'name' => $match['name'],
                'role' => $match['role'],
            ],
            'classes' => $classes,
        ], '자동 로그인 되었습니다');
        break;

    // 코치 로그아웃 (핑거프린트 비활성화)
    case 'logout':
        if ($method === 'POST') {
            $input = getJsonInput();
            $fingerprint = trim($input['fingerprint'] ?? '');
        } else {
            $fingerprint = '';
        }

        $session = getAdminSession();

        // 핑거프린트 비활성화
        if ($session && $fingerprint) {
            $db = getDB();
            $stmt = $db->prepare('UPDATE junior_admin_fingerprints SET is_active = 0 WHERE admin_id = ? AND fingerprint = ?');
            $stmt->execute([$session['admin_id'], $fingerprint]);
        }

        logoutAdmin();
        jsonSuccess([], '로그아웃 되었습니다');
        break;

    // 비밀번호 변경
    case 'change_password':
        if ($method !== 'POST') jsonError('POST만 허용됩니다', 405);
        $session = requireAdmin();
        $input = getJsonInput();
        $currentPw = trim($input['current_password'] ?? '');
        $newPw = trim($input['new_password'] ?? '');
        $confirmPw = trim($input['confirm_password'] ?? '');

        if (!$currentPw || !$newPw || !$confirmPw) {
            jsonError('모든 항목을 입력해 주세요');
        }
        if ($newPw !== $confirmPw) {
            jsonError('새 비밀번호가 일치하지 않습니다');
        }
        if (mb_strlen($newPw) < 4) {
            jsonError('새 비밀번호는 4자 이상이어야 합니다');
        }

        $db = getDB();
        $stmt = $db->prepare('SELECT password_hash FROM junior_admins WHERE id = ?');
        $stmt->execute([$session['admin_id']]);
        $admin = $stmt->fetch();

        if (!$admin || !password_verify($currentPw, $admin['password_hash'])) {
            jsonError('현재 비밀번호가 올바르지 않습니다');
        }

        $newHash = password_hash($newPw, PASSWORD_DEFAULT);
        $stmt = $db->prepare('UPDATE junior_admins SET password_hash = ? WHERE id = ?');
        $stmt->execute([$newHash, $session['admin_id']]);

        jsonSuccess([], '비밀번호가 변경되었습니다');
        break;

    // 관리쌤 전화번호 뒷4자리 로그인
    case 'teacher_login':
        if ($method !== 'POST') jsonError('POST만 허용됩니다', 405);
        $input = getJsonInput();
        $phoneLast4 = trim($input['phone_last4'] ?? '');
        $fingerprint = trim($input['fingerprint'] ?? '');
        $deviceInfo = $input['device_info'] ?? null;

        if (!$phoneLast4 || strlen($phoneLast4) !== 4 || !ctype_digit($phoneLast4)) {
            jsonError('전화번호 뒷 4자리를 정확히 입력해 주세요');
        }

        $db = getDB();
        $stmt = $db->prepare('
            SELECT * FROM junior_admins
            WHERE role = "admin_teacher" AND is_active = 1
            AND RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(phone, "-", ""), " ", ""), "–", ""), "—", ""), 4) = ?
        ');
        $stmt->execute([$phoneLast4]);
        $matches = $stmt->fetchAll();

        if (empty($matches)) {
            jsonError('등록된 번호가 없습니다. 관리자에게 문의해 주세요.');
        }
        if (count($matches) > 1) {
            jsonError('여러 계정이 일치합니다. 관리자에게 문의해 주세요.');
        }

        $admin = $matches[0];
        loginAdmin($admin['id'], $admin['name'], $admin['role']);

        // 핑거프린트 저장
        if ($fingerprint) {
            $deviceInfoJson = $deviceInfo ? (is_string($deviceInfo) ? $deviceInfo : json_encode($deviceInfo, JSON_UNESCAPED_UNICODE)) : null;
            $stmt = $db->prepare('
                INSERT INTO junior_admin_fingerprints (admin_id, fingerprint, device_info, is_active, last_used_at)
                VALUES (?, ?, ?, 1, NOW())
                ON DUPLICATE KEY UPDATE is_active = 1, last_used_at = NOW(), device_info = VALUES(device_info)
            ');
            $stmt->execute([$admin['id'], $fingerprint, $deviceInfoJson]);
        }

        // 담당 반 정보
        $stmt = $db->prepare('
            SELECT c.id, c.display_name
            FROM junior_admin_classes ac
            JOIN junior_classes c ON ac.class_id = c.id
            WHERE ac.admin_id = ? AND ac.is_active = 1
        ');
        $stmt->execute([$admin['id']]);
        $classes = $stmt->fetchAll();

        jsonSuccess([
            'admin' => [
                'id'   => $admin['id'],
                'name' => $admin['name'],
                'role' => $admin['role'],
            ],
            'classes' => $classes,
        ], $admin['name'] . ' 관리쌤 환영합니다!');
        break;

    // 세션 확인
    case 'check_session':
        $session = getAdminSession();
        if ($session) {
            $db = getDB();
            $stmt = $db->prepare('
                SELECT c.id, c.display_name
                FROM junior_admin_classes ac
                JOIN junior_classes c ON ac.class_id = c.id
                WHERE ac.admin_id = ? AND ac.is_active = 1
            ');
            $stmt->execute([$session['admin_id']]);
            $classes = $stmt->fetchAll();

            jsonSuccess(['logged_in' => true, 'admin' => $session, 'classes' => $classes]);
        } else {
            jsonSuccess(['logged_in' => false]);
        }
        break;

    // 대시보드 - 내 반 현황
    case 'dashboard':
        $admin = requireAdmin(['coach', 'admin_teacher']);
        if ($admin['admin_role'] === 'admin_teacher') verifyClassAccess($admin['admin_id'], (int)($_GET['class_id'] ?? 0));
        $classId = (int)($_GET['class_id'] ?? 0);
        $date = trim($_GET['date'] ?? date('Y-m-d'));

        if (!$classId) jsonError('반을 선택해 주세요');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');

        $db = getDB();

        // 반 학생 목록 + 코인
        $stmt = $db->prepare('
            SELECT s.id, s.name, s.grade,
                   COALESCE(SUM(sr.quantity * rt.coin_value), 0) as total_coins
            FROM junior_students s
            JOIN junior_class_students cs ON s.id = cs.student_id AND cs.is_active = 1
            LEFT JOIN junior_student_rewards sr ON s.id = sr.student_id
            LEFT JOIN junior_reward_types rt ON sr.reward_type_id = rt.id
            WHERE cs.class_id = ? AND s.is_active = 1
            GROUP BY s.id
            ORDER BY total_coins DESC, s.name ASC
        ');
        $stmt->execute([$classId]);
        $students = $stmt->fetchAll();

        // 반 정보
        $stmt = $db->prepare('SELECT id, display_name, coach_name, color FROM junior_classes WHERE id = ?');
        $stmt->execute([$classId]);
        $classInfo = $stmt->fetch();

        // 해당 날짜 과제 완료율 + 항목별 합산
        $totalStudents = count($students);
        $completedCount = 0;
        $completionRate = 0;
        $itemStats = ['zoom' => 0, 'posture' => 0, 'homework' => 0, 'mission' => 0, 'leader' => 0];

        if ($totalStudents > 0) {
            $stmt = $db->prepare('
                SELECT COUNT(DISTINCT student_id) as completed_count,
                       COALESCE(SUM(zoom_attendance),0) as zoom,
                       COALESCE(SUM(posture_king),0) as posture,
                       COALESCE(SUM(sound_homework),0) as homework,
                       COALESCE(SUM(band_mission),0) as mission,
                       COALESCE(SUM(leader_king),0) as leader
                FROM junior_daily_checklist
                WHERE class_id = ? AND check_date = ?
                AND (zoom_attendance > 0 OR posture_king > 0 OR sound_homework > 0 OR band_mission > 0 OR leader_king > 0)
            ');
            $stmt->execute([$classId, $date]);
            $row = $stmt->fetch();
            $completedCount = (int)$row['completed_count'];
            $completionRate = round(($completedCount / $totalStudents) * 100);
            $itemStats = [
                'zoom'     => (int)$row['zoom'],
                'posture'  => (int)$row['posture'],
                'homework' => (int)$row['homework'],
                'mission'  => (int)$row['mission'],
                'leader'   => (int)$row['leader'],
            ];
        }

        // 최근 7일 추세 (미니 차트용) - 선택된 날짜 기준 이전 7일 (당일 제외)
        $trendEnd   = date('Y-m-d', strtotime($date . ' -1 day'));
        $trendStart = date('Y-m-d', strtotime($date . ' -7 days'));
        $stmt = $db->prepare('
            SELECT dc.check_date, COUNT(DISTINCT dc.student_id) as cnt
            FROM junior_daily_checklist dc
            WHERE dc.class_id = ? AND dc.check_date >= ? AND dc.check_date <= ?
            AND (dc.zoom_attendance > 0 OR dc.posture_king > 0 OR dc.sound_homework > 0 OR dc.band_mission > 0 OR dc.leader_king > 0)
            GROUP BY dc.check_date
            ORDER BY dc.check_date ASC
        ');
        $stmt->execute([$classId, $trendStart, $trendEnd]);
        $trendRows = $stmt->fetchAll();
        $trendMap = [];
        foreach ($trendRows as $r) {
            $trendMap[$r['check_date']] = (int)$r['cnt'];
        }
        // 7일 모든 날짜를 채워서 빈 날짜 없이 반환
        $trend = [];
        for ($i = 7; $i >= 1; $i--) {
            $d = date('Y-m-d', strtotime($date . " -{$i} days"));
            $cnt = $trendMap[$d] ?? 0;
            $trend[] = [
                'date' => $d,
                'rate' => $totalStudents > 0 ? round(($cnt / $totalStudents) * 100) : 0,
            ];
        }

        jsonSuccess([
            'class'            => $classInfo,
            'students'         => $students,
            'count'            => count($students),
            'completion_rate'  => $completionRate,
            'completed_count'  => $completedCount,
            'total_count'      => $totalStudents,
            'date'             => $date,
            'items'            => $itemStats,
            'trend'            => $trend,
        ]);
        break;

    // 체크리스트 로드
    case 'checklist_load':
        $admin = requireAdmin(['coach', 'admin_teacher']);
        $classId = (int)($_GET['class_id'] ?? 0);
        $date = trim($_GET['date'] ?? date('Y-m-d'));

        if (!$classId) jsonError('반을 선택해 주세요');
        if ($admin['admin_role'] === 'admin_teacher') verifyClassAccess($admin['admin_id'], $classId);

        $db = getDB();

        // 반 학생 목록
        $stmt = $db->prepare('
            SELECT s.id, s.name
            FROM junior_students s
            JOIN junior_class_students cs ON s.id = cs.student_id AND cs.is_active = 1
            WHERE cs.class_id = ? AND s.is_active = 1
            ORDER BY s.name
        ');
        $stmt->execute([$classId]);
        $students = $stmt->fetchAll();

        // 해당 날짜 체크리스트
        if ($admin['admin_role'] === 'admin_teacher') {
            // 관리쌤: 반의 모든 체크리스트 (코치 무관)
            $stmt = $db->prepare('
                SELECT student_id, zoom_attendance, posture_king, sound_homework, band_mission, leader_king, reboot_card
                FROM junior_daily_checklist
                WHERE class_id = ? AND check_date = ?
            ');
            $stmt->execute([$classId, $date]);
        } else {
            // 코치: 본인이 작성한 것만
            $stmt = $db->prepare('
                SELECT student_id, zoom_attendance, posture_king, sound_homework, band_mission, leader_king, reboot_card
                FROM junior_daily_checklist
                WHERE class_id = ? AND check_date = ? AND coach_id = ?
            ');
            $stmt->execute([$classId, $date, $admin['admin_id']]);
        }
        $checks = [];
        while ($row = $stmt->fetch()) {
            $checks[$row['student_id']] = $row;
        }

        // 학생별 체크 상태 병합
        $result = array_map(function($s) use ($checks) {
            $check = $checks[$s['id']] ?? null;
            return [
                'id'              => $s['id'],
                'name'            => $s['name'],
                'zoom_attendance' => $check ? (int)$check['zoom_attendance'] : 0,
                'posture_king'    => $check ? (int)$check['posture_king'] : 0,
                'sound_homework'  => $check ? (int)$check['sound_homework'] : 0,
                'band_mission'    => $check ? (int)$check['band_mission'] : 0,
                'leader_king'     => $check ? (int)$check['leader_king'] : 0,
                'reboot_card'     => $check ? (int)$check['reboot_card'] : 0,
            ];
        }, $students);

        jsonSuccess(['students' => $result, 'date' => $date]);
        break;

    // 체크리스트 일괄 저장
    case 'checklist_save':
        if ($method !== 'POST') jsonError('POST만 허용됩니다', 405);
        $admin = requireAdmin(['coach', 'admin_teacher']);
        $input = getJsonInput();

        $classId = (int)($input['class_id'] ?? 0);
        $date = trim($input['date'] ?? date('Y-m-d'));
        $items = $input['items'] ?? [];

        if (!$classId || empty($items)) jsonError('데이터가 부족합니다');
        if ($admin['admin_role'] === 'admin_teacher') verifyClassAccess($admin['admin_id'], $classId);

        $db = getDB();

        // 기존 체크리스트 조회 (비교용 — 사전검증에서도 사용)
        if ($admin['admin_role'] === 'admin_teacher') {
            $stmt = $db->prepare('
                SELECT student_id, zoom_attendance, posture_king, sound_homework, band_mission, leader_king, reboot_card
                FROM junior_daily_checklist
                WHERE class_id = ? AND check_date = ?
            ');
            $stmt->execute([$classId, $date]);
        } else {
            $stmt = $db->prepare('
                SELECT student_id, zoom_attendance, posture_king, sound_homework, band_mission, leader_king, reboot_card
                FROM junior_daily_checklist
                WHERE class_id = ? AND check_date = ? AND coach_id = ?
            ');
            $stmt->execute([$classId, $date, $admin['admin_id']]);
        }
        $existing = [];
        while ($row = $stmt->fetch()) {
            $existing[$row['student_id']] = $row;
        }

        // 주간 카드 한도 사전검증 (숫자형 필드: zoom_attendance → passion, posture_king → posture)
        $limitErrors = [];
        $numCheckFields = ['zoom_attendance', 'posture_king'];
        foreach ($items as $item) {
            $studentId = (int)$item['student_id'];
            $prev = $existing[$studentId] ?? null;

            foreach ($numCheckFields as $checkField) {
                $cardCode = CHECKLIST_CARD_MAP[$checkField];
                $prevVal = $prev ? (int)$prev[$checkField] : 0;
                $newVal = (int)($item[$checkField] ?? 0);
                $diff = $newVal - $prevVal;

                if ($diff > 0) {
                    $usage = getWeeklyCardUsage($studentId, $cardCode);
                    if ($usage['limit'] !== null && $diff > $usage['remaining']) {
                        $typeName = CARD_TYPES[$cardCode]['name'] ?? $cardCode;
                        $limitErrors[] = [
                            'student_id' => $studentId,
                            'name'       => $item['name'] ?? '',
                            'card'       => $typeName,
                            'field'      => $checkField,
                            'requested'  => $diff,
                            'remaining'  => $usage['remaining'],
                            'limit'      => $usage['limit'],
                            'used'       => $usage['used'],
                        ];
                    }
                }
            }
        }

        if (!empty($limitErrors)) {
            $msgs = array_map(function($e) {
                $name = $e['name'] ?: "학생#{$e['student_id']}";
                return "{$name}: {$e['card']} 요청 {$e['requested']}장, 남은 한도 {$e['remaining']}장";
            }, $limitErrors);
            jsonError('주간 카드 한도를 초과합니다 — ' . implode(', ', $msgs), 400, ['limit_errors' => $limitErrors]);
        }

        $db->beginTransaction();

        try {
            foreach ($items as $item) {
                $studentId = (int)$item['student_id'];
                $fields = [
                    'zoom_attendance' => (int)($item['zoom_attendance'] ?? 0),
                    'posture_king'    => (int)($item['posture_king'] ?? 0),
                    'sound_homework'  => (int)($item['sound_homework'] ?? 0),
                    'band_mission'    => (int)($item['band_mission'] ?? 0),
                    'leader_king'     => (int)($item['leader_king'] ?? 0),
                    'reboot_card'     => (int)($item['reboot_card'] ?? 0),
                ];

                $prev = $existing[$studentId] ?? null;

                // UPSERT
                $stmt = $db->prepare('
                    INSERT INTO junior_daily_checklist
                    (student_id, class_id, check_date, coach_id, zoom_attendance, posture_king, sound_homework, band_mission, leader_king, reboot_card)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                    zoom_attendance = VALUES(zoom_attendance),
                    posture_king = VALUES(posture_king),
                    sound_homework = VALUES(sound_homework),
                    band_mission = VALUES(band_mission),
                    leader_king = VALUES(leader_king),
                    reboot_card = VALUES(reboot_card),
                    updated_at = NOW()
                ');
                $stmt->execute([
                    $studentId, $classId, $date, $admin['admin_id'],
                    $fields['zoom_attendance'], $fields['posture_king'],
                    $fields['sound_homework'], $fields['band_mission'], $fields['leader_king'],
                    $fields['reboot_card'],
                ]);

                // 체크 변경 시 카드 자동 부여/차감
                $givenByType = $admin['admin_role'] === 'admin_teacher' ? 'admin' : $admin['admin_role'];
                foreach (CHECKLIST_CARD_MAP as $checkField => $cardCode) {
                    if ($checkField === 'sound_homework') continue; // 꾸준왕은 주간 판정으로 별도 처리

                    $prevVal = $prev ? (int)$prev[$checkField] : 0;
                    $newVal = $fields[$checkField];

                    if (in_array($checkField, $numCheckFields, true)) {
                        // 숫자형 필드: 증감분만큼 카드 지급/차감
                        $diff = $newVal - $prevVal;
                        if ($diff !== 0) {
                            $result = changeReward($studentId, $cardCode, $diff, 'checklist',
                                "체크리스트: {$checkField} {$prevVal}→{$newVal} ({$date})",
                                $admin['admin_id'], $givenByType);
                            if (!$result['success']) {
                                throw new Exception($result['message'] ?? '카드 지급 실패');
                            }
                        }
                    } else {
                        // 불린형 필드: 0/1 토글
                        if ($newVal === 1 && $prevVal === 0) {
                            $result = changeReward($studentId, $cardCode, 1, 'checklist',
                                "체크리스트: {$checkField} ({$date})",
                                $admin['admin_id'], $givenByType);
                            if (!$result['success'] && $result['error'] === 'weekly_limit_exceeded') {
                                throw new Exception($result['message'] ?? '주간 한도 초과');
                            }
                        } elseif ($newVal === 0 && $prevVal === 1) {
                            changeReward($studentId, $cardCode, -1, 'checklist',
                                "체크리스트 해제: {$checkField} ({$date})",
                                $admin['admin_id'], $givenByType);
                        }
                    }
                }

                // 꾸준왕 주간 판정
                evaluateSteadyForWeek($studentId, $date, $admin['admin_id'], $givenByType);
            }

            $db->commit();
            jsonSuccess([], '체크리스트가 저장되었습니다');
        } catch (Exception $e) {
            $db->rollBack();
            jsonError('저장 중 오류가 발생했습니다: ' . $e->getMessage(), 500);
        }
        break;

    // QR 출석 세션 생성
    case 'create_qr':
        if ($method !== 'POST') jsonError('POST만 허용됩니다', 405);
        $admin = requireAdmin(['coach']);
        $input = getJsonInput();
        $classId = (int)($input['class_id'] ?? 0);
        $sessionType = in_array($input['session_type'] ?? '', ['basic', 'zoom_special']) ? $input['session_type'] : 'basic';

        if (!$classId) jsonError('반을 선택해 주세요');

        $result = createQRSession($classId, $admin['admin_id'], $sessionType);
        jsonSuccess($result, 'QR 세션이 생성되었습니다');
        break;

    // QR 세션 종료
    case 'close_qr':
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

    // QR 출석자 목록
    case 'qr_attendees':
        $admin = requireAdmin(['coach']);
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
        jsonSuccess(['attendees' => $stmt->fetchAll()]);
        break;

    // 출석 승인/보류/제거
    case 'qr_approve':
        if ($method !== 'POST') jsonError('POST만 허용됩니다', 405);
        $admin = requireAdmin(['coach']);
        $input = getJsonInput();
        $attendanceId = (int)($input['attendance_id'] ?? 0);
        $status = $input['status'] ?? '';

        if (!$attendanceId || !in_array($status, ['approved', 'hold', 'removed'])) {
            jsonError('올바른 데이터를 입력해 주세요');
        }

        $db = getDB();
        $stmt = $db->prepare('
            UPDATE junior_qr_attendance
            SET status = ?, approved_at = NOW(), approved_by = ?
            WHERE id = ?
        ');
        $stmt->execute([$status, $admin['admin_id'], $attendanceId]);

        // QR 로그
        $stmt = $db->prepare('
            SELECT qr_session_id, student_id FROM junior_qr_attendance WHERE id = ?
        ');
        $stmt->execute([$attendanceId]);
        $att = $stmt->fetch();
        if ($att) {
            $stmt = $db->prepare('
                INSERT INTO junior_qr_log (qr_session_id, event_type, student_id, admin_id, detail, ip_address)
                VALUES (?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $att['qr_session_id'], 'status_changed', $att['student_id'], $admin['admin_id'],
                json_encode(['new_status' => $status]), getClientIP()
            ]);
        }

        $labels = ['approved' => '승인', 'hold' => '보류', 'removed' => '제거'];
        jsonSuccess([], "출석 상태가 '{$labels[$status]}'으로 변경되었습니다");
        break;

    // 학생 프로필 (시스템 항목 포함)
    case 'student_profile':
        $admin = requireAdmin(['coach', 'admin_teacher']);
        $studentId = (int)($_GET['student_id'] ?? 0);

        if (!$studentId) jsonError('학생 ID가 필요합니다');

        $db = getDB();

        // 학생 기본 정보
        $stmt = $db->prepare('
            SELECT s.id, s.name, s.grade, s.phone_last4,
                   c.display_name as class_name, c.coach_name
            FROM junior_students s
            LEFT JOIN junior_class_students cs ON s.id = cs.student_id AND cs.is_primary = 1 AND cs.is_active = 1
            LEFT JOIN junior_classes c ON cs.class_id = c.id
            WHERE s.id = ?
        ');
        $stmt->execute([$studentId]);
        $student = $stmt->fetch();
        if (!$student) jsonError('학생을 찾을 수 없습니다');

        // 카드 보유 현황
        $rewards = getStudentRewards($studentId);
        $totalCoins = getStudentTotalCoins($studentId);

        // 디바이스 정보
        $stmt = $db->prepare('
            SELECT fingerprint, device_info, last_used_at
            FROM junior_device_fingerprints
            WHERE student_id = ?
            ORDER BY last_used_at DESC LIMIT 5
        ');
        $stmt->execute([$studentId]);
        $devices = $stmt->fetchAll();

        // 최근 감사 로그
        $stmt = $db->prepare('
            SELECT field_name, old_value, new_value, reason, changed_by_name, created_at
            FROM junior_edit_audit_log
            WHERE table_name = "junior_student_rewards" AND record_id = ?
            ORDER BY created_at DESC LIMIT 20
        ');
        $stmt->execute([$studentId]);
        $auditLog = $stmt->fetchAll();

        jsonSuccess([
            'student'     => $student,
            'rewards'     => $rewards,
            'total_coins' => $totalCoins,
            'devices'     => $devices,
            'audit_log'   => $auditLog,
        ]);
        break;

    // 카드 수동 수정 (감사 로그 필수)
    case 'edit_reward':
        if ($method !== 'POST') jsonError('POST만 허용됩니다', 405);
        $admin = requireAdmin(['coach', 'admin_teacher']);
        $input = getJsonInput();

        $studentId = (int)($input['student_id'] ?? 0);
        $rewardCode = trim($input['reward_code'] ?? '');
        $changeAmount = (int)($input['change_amount'] ?? 0);
        $reason = trim($input['reason'] ?? '');

        if (!$studentId || !$rewardCode || $changeAmount === 0) {
            jsonError('필수 항목을 모두 입력해 주세요');
        }
        if (!$reason) {
            jsonError('수정 사유를 입력해 주세요 (감사 로그)');
        }

        $db = getDB();

        // 현재 수량 확인
        $stmt = $db->prepare('
            SELECT sr.quantity, rt.name_ko
            FROM junior_student_rewards sr
            JOIN junior_reward_types rt ON sr.reward_type_id = rt.id
            WHERE sr.student_id = ? AND rt.code = ?
        ');
        $stmt->execute([$studentId, $rewardCode]);
        $current = $stmt->fetch();
        $oldQty = $current ? (int)$current['quantity'] : 0;

        // 수량 변경
        $logRole = $admin['admin_role'] === 'admin_teacher' ? 'admin' : $admin['admin_role'];
        $result = changeReward($studentId, $rewardCode, $changeAmount, 'manual', $reason, $admin['admin_id'], $logRole);
        if (!$result['success']) {
            if ($result['error'] === 'weekly_limit_exceeded') {
                jsonError($result['message'], 400, [
                    'remaining' => $result['remaining'],
                    'limit'     => $result['limit'],
                ]);
            }
            jsonError('카드 종류를 찾을 수 없습니다');
        }

        // 감사 로그
        $newQty = max(0, $oldQty + $changeAmount);
        auditLog(
            'junior_student_rewards', $studentId, 'update',
            $rewardCode, (string)$oldQty, (string)$newQty,
            $reason, $admin['admin_id'], $logRole, $admin['admin_name']
        );

        jsonSuccess([
            'old_quantity' => $oldQty,
            'new_quantity' => $newQty,
        ], '카드가 수정되었습니다');
        break;

    // QR 출석 전체 현황 (반 전체 학생 + 출석 상태)
    case 'qr_full_status':
        $admin = requireAdmin(['coach']);
        $sessionId = (int)($_GET['session_id'] ?? 0);
        $classId = (int)($_GET['class_id'] ?? 0);

        if (!$sessionId || !$classId) jsonError('세션 ID와 반 ID가 필요합니다');

        $db = getDB();

        // 반 전체 학생
        $stmt = $db->prepare('
            SELECT s.id, s.name, s.grade
            FROM junior_students s
            JOIN junior_class_students cs ON s.id = cs.student_id AND cs.is_active = 1
            WHERE cs.class_id = ? AND s.is_active = 1
            ORDER BY s.name
        ');
        $stmt->execute([$classId]);
        $allStudents = $stmt->fetchAll();

        // 출석 기록
        $stmt = $db->prepare('
            SELECT qa.student_id, qa.status, qa.scanned_at, qa.id as attendance_id,
                   s.name as student_name, qa.is_home_class,
                   c.display_name as home_class_name
            FROM junior_qr_attendance qa
            JOIN junior_students s ON qa.student_id = s.id
            LEFT JOIN junior_class_students cs2 ON s.id = cs2.student_id AND cs2.is_primary = 1 AND cs2.is_active = 1
            LEFT JOIN junior_classes c ON cs2.class_id = c.id
            WHERE qa.qr_session_id = ?
            ORDER BY qa.scanned_at DESC
        ');
        $stmt->execute([$sessionId]);
        $attendanceList = $stmt->fetchAll();

        $attendanceMap = [];
        $otherClassAttendees = [];
        foreach ($attendanceList as $att) {
            if (isset($attendanceMap[$att['student_id']])) continue;
            $attendanceMap[$att['student_id']] = $att;
            // 반 학생이 아닌 출석자 = 타반
            $isInClass = false;
            foreach ($allStudents as $cs) {
                if ($cs['id'] == $att['student_id']) { $isInClass = true; break; }
            }
            if (!$isInClass && $att['status'] !== 'removed') {
                $otherClassAttendees[] = $att;
            }
        }

        // 이 세션에서 바른자세왕 지급된 학생 목록
        $postureSourceDetail = "QR 바른자세왕 (session:{$sessionId})";
        $stmt = $db->prepare('
            SELECT rl.student_id FROM junior_reward_log rl
            JOIN junior_reward_types rt ON rl.reward_type_id = rt.id
            WHERE rt.code = ? AND rl.source = ? AND rl.source_detail = ? AND rl.change_amount > 0
        ');
        $stmt->execute(['posture', 'qr_attendance', $postureSourceDetail]);
        $postureGivenSet = array_flip(array_column($stmt->fetchAll(), 'student_id'));

        // 반 학생 리스트 + 출석 상태 병합
        $students = array_map(function($s) use ($attendanceMap, $postureGivenSet) {
            $att = $attendanceMap[$s['id']] ?? null;
            return [
                'id'            => $s['id'],
                'name'          => $s['name'],
                'grade'         => $s['grade'],
                'attended'      => $att !== null && $att['status'] !== 'removed',
                'attendance_id' => $att['attendance_id'] ?? null,
                'status'        => $att['status'] ?? null,
                'scanned_at'    => $att['scanned_at'] ?? null,
                'posture_given' => isset($postureGivenSet[$s['id']]),
            ];
        }, $allStudents);

        $attendedCount = count(array_filter($students, fn($s) => $s['attended']));

        // 타반 출석자에도 posture_given 추가
        $otherClassWithPosture = array_map(function($att) use ($postureGivenSet) {
            $att['posture_given'] = isset($postureGivenSet[$att['student_id']]);
            return $att;
        }, $otherClassAttendees);

        jsonSuccess([
            'students'    => $students,
            'other_class' => $otherClassWithPosture,
            'total'       => count($allStudents),
            'attended'    => $attendedCount,
        ]);
        break;

    // QR 출석 수동 추가/제거 (코치)
    case 'qr_manual_attendance':
        if ($method !== 'POST') jsonError('POST만 허용됩니다', 405);
        $admin = requireAdmin(['coach']);
        $input = getJsonInput();

        $sessionId = (int)($input['session_id'] ?? 0);
        $studentId = (int)($input['student_id'] ?? 0);
        $type = $input['type'] ?? '';
        $reason = trim($input['reason'] ?? '');

        if (!$sessionId || !$studentId || !in_array($type, ['add', 'remove'])) {
            jsonError('올바른 데이터를 입력해 주세요');
        }

        $db = getDB();

        // 세션 정보
        $stmt = $db->prepare('SELECT class_id FROM junior_qr_sessions WHERE id = ?');
        $stmt->execute([$sessionId]);
        $qrSession = $stmt->fetch();
        if (!$qrSession) jsonError('세션을 찾을 수 없습니다');

        // 학생 이름 조회
        $stmt = $db->prepare('SELECT name FROM junior_students WHERE id = ?');
        $stmt->execute([$studentId]);
        $studentName = $stmt->fetchColumn() ?: '학생';

        if ($type === 'add') {
            // 중복 확인
            $stmt = $db->prepare('SELECT id FROM junior_qr_attendance WHERE qr_session_id = ? AND student_id = ? AND status != "removed"');
            $stmt->execute([$sessionId, $studentId]);
            if ($stmt->fetch()) jsonError('이미 출석이 기록되어 있습니다');

            // 본반 확인
            $stmt = $db->prepare('SELECT is_primary FROM junior_class_students WHERE class_id = ? AND student_id = ? AND is_active = 1');
            $stmt->execute([$qrSession['class_id'], $studentId]);
            $cs = $stmt->fetch();
            $isHomeClass = $cs ? (int)$cs['is_primary'] : 0;

            // 출석 추가
            $stmt = $db->prepare('
                INSERT INTO junior_qr_attendance
                (qr_session_id, student_id, class_id, is_home_class, status, approved_at, approved_by, ip_address)
                VALUES (?, ?, ?, ?, "approved", NOW(), ?, ?)
            ');
            $stmt->execute([$sessionId, $studentId, $qrSession['class_id'], $isHomeClass, $admin['admin_id'], getClientIP()]);

            // QR 로그
            $stmt = $db->prepare('INSERT INTO junior_qr_log (qr_session_id, event_type, student_id, admin_id, detail, ip_address) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$sessionId, 'manual_add', $studentId, $admin['admin_id'],
                json_encode(['reason' => $reason ?: '수동 추가'], JSON_UNESCAPED_UNICODE), getClientIP()]);

            // 감사 로그
            auditLog('junior_qr_attendance', $studentId, 'create', 'manual_attendance',
                null, 'approved', $reason ?: '코치 수동 출석 추가',
                $admin['admin_id'], 'coach', $admin['admin_name']);

            jsonSuccess([], $studentName . ' 학생의 출석이 추가되었습니다');
        } else {
            // 제거
            $stmt = $db->prepare('UPDATE junior_qr_attendance SET status = "removed", approved_by = ? WHERE qr_session_id = ? AND student_id = ? AND status != "removed"');
            $stmt->execute([$admin['admin_id'], $sessionId, $studentId]);

            // QR 로그
            $stmt = $db->prepare('INSERT INTO junior_qr_log (qr_session_id, event_type, student_id, admin_id, detail, ip_address) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$sessionId, 'manual_remove', $studentId, $admin['admin_id'],
                json_encode(['reason' => $reason ?: '수동 제거'], JSON_UNESCAPED_UNICODE), getClientIP()]);

            // 감사 로그
            auditLog('junior_qr_attendance', $studentId, 'update', 'manual_attendance',
                'approved', 'removed', $reason ?: '코치 수동 출석 제거',
                $admin['admin_id'], 'coach', $admin['admin_name']);

            jsonSuccess([], $studentName . ' 학생의 출석이 제거되었습니다');
        }
        break;

    // QR 출석 화면에서 바른자세왕 카드 지급 (세션당 1회)
    case 'give_posture_card':
        if ($method !== 'POST') jsonError('POST만 허용됩니다', 405);
        $admin = requireAdmin(['coach']);
        $input = getJsonInput();
        $studentId = (int)($input['student_id'] ?? 0);
        $sessionId = (int)($input['session_id'] ?? 0);
        if (!$studentId) jsonError('학생을 선택해 주세요');
        if (!$sessionId) jsonError('세션 정보가 필요합니다');

        // 이 세션에서 이미 지급했는지 확인
        $db = getDB();
        $sourceDetail = "QR 바른자세왕 (session:{$sessionId})";
        $stmt = $db->prepare('SELECT id FROM junior_reward_types WHERE code = ?');
        $stmt->execute(['posture']);
        $postureTypeId = (int)$stmt->fetchColumn();

        $stmt = $db->prepare('
            SELECT COUNT(*) FROM junior_reward_log
            WHERE student_id = ? AND reward_type_id = ? AND source = ? AND source_detail = ? AND change_amount > 0
        ');
        $stmt->execute([$studentId, $postureTypeId, 'qr_attendance', $sourceDetail]);
        if ((int)$stmt->fetchColumn() > 0) {
            jsonError('이 세션에서 이미 바른자세왕 카드를 지급했습니다');
        }

        $result = changeReward($studentId, 'posture', 1, 'qr_attendance',
            $sourceDetail, $admin['admin_id'], 'coach');

        if (!$result['success'] && $result['error'] === 'weekly_limit_exceeded') {
            jsonError($result['message'] ?? '바른자세왕 주간 한도(5장) 초과');
        }
        if (!$result['success']) {
            jsonError($result['message'] ?? '카드 지급 실패');
        }

        $usage = getWeeklyCardUsage($studentId, 'posture');
        jsonSuccess(['remaining' => $usage['remaining'], 'limit' => $usage['limit']], '바른자세왕 카드가 지급되었습니다');
        break;

    // 전체 반 과제율 랭킹 (코치/관리쌤/부모 모두 접근 가능)
    case 'class_assignment_ranking':
        $admin = requireAdmin(); // 모든 관리자 역할 허용
        $db = getDB();
        $date = trim($_GET['date'] ?? date('Y-m-d'));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');

        // 전체 반 목록 + 과제 완료율
        $stmt = $db->prepare('
            SELECT c.id as class_id, c.display_name as class_name, c.coach_name, c.color, c.logo_image,
                   (SELECT COUNT(*) FROM junior_class_students cs2
                    WHERE cs2.class_id = c.id AND cs2.is_active = 1) as total_students,
                   COUNT(DISTINCT dc.student_id) as checked_count,
                   COALESCE(SUM(dc.zoom_attendance), 0) as zoom_done,
                   COALESCE(SUM(dc.posture_king), 0) as posture_done,
                   COALESCE(SUM(dc.sound_homework), 0) as homework_done,
                   COALESCE(SUM(dc.band_mission), 0) as mission_done,
                   COALESCE(SUM(dc.leader_king), 0) as leader_done
            FROM junior_classes c
            LEFT JOIN junior_daily_checklist dc ON c.id = dc.class_id AND dc.check_date = ?
            WHERE c.is_active = 1
            GROUP BY c.id
            ORDER BY c.sort_order
        ');
        $stmt->execute([$date]);
        $classes = $stmt->fetchAll();

        // 완료율 계산 + 정렬
        $ranked = [];
        foreach ($classes as &$cls) {
            $total = (int)$cls['total_students'];
            $checked = (int)$cls['checked_count'];
            $cls['completion_rate'] = $total > 0 ? round(($checked / $total) * 100) : 0;
            $ranked[] = $cls;
        }
        unset($cls);

        // 완료율 내림차순 정렬
        usort($ranked, function($a, $b) {
            return $b['completion_rate'] - $a['completion_rate'];
        });

        // 순위 부여 (동률 처리)
        $rank = 0;
        $prevRate = -1;
        foreach ($ranked as &$cls) {
            if ($cls['completion_rate'] !== $prevRate) {
                $rank++;
                $prevRate = $cls['completion_rate'];
            }
            $cls['rank'] = $rank;
        }
        unset($cls);

        jsonSuccess([
            'date'    => $date,
            'classes' => $ranked,
        ]);
        break;

    // 주간 카드 남은 한도 조회
    case 'weekly_card_remaining':
        $admin = requireAdmin(['coach', 'admin_teacher']);
        if ($admin['admin_role'] === 'admin_teacher') verifyClassAccess($admin['admin_id'], (int)($_GET['class_id'] ?? 0));
        $classId = (int)($_GET['class_id'] ?? 0);
        if (!$classId) jsonError('반을 선택해 주세요');

        $db = getDB();

        // 반 소속 학생 조회
        $stmt = $db->prepare('
            SELECT s.id, s.name
            FROM junior_students s
            JOIN junior_class_students cs ON s.id = cs.student_id AND cs.is_active = 1
            WHERE cs.class_id = ? AND s.is_active = 1
            ORDER BY s.name
        ');
        $stmt->execute([$classId]);
        $students = $stmt->fetchAll();

        // 주간 한도가 설정된 카드 종류 조회
        $stmt = $db->prepare('SELECT code, name_ko, weekly_limit FROM junior_reward_types WHERE weekly_limit IS NOT NULL AND is_active = 1');
        $stmt->execute();
        $limitedTypes = $stmt->fetchAll();

        $result = [];
        foreach ($students as $student) {
            $studentLimits = [];
            foreach ($limitedTypes as $type) {
                $usage = getWeeklyCardUsage($student['id'], $type['code']);
                $studentLimits[$type['code']] = [
                    'used'      => $usage['used'],
                    'limit'     => $usage['limit'],
                    'remaining' => $usage['remaining'],
                ];
            }
            $result[] = [
                'student_id' => (int)$student['id'],
                'name'       => $student['name'],
                'limits'     => $studentLimits,
            ];
        }

        jsonSuccess(['students' => $result, 'limited_types' => $limitedTypes]);
        break;

    // ============================================
    // 소리과제 현황 리포트 (단일 반)
    // ============================================
    case 'homework_report':
        requireAdmin(['coach', 'admin_teacher']);
        $classId = (int)($_GET['class_id'] ?? 0);
        if (!$classId) jsonError('반을 선택해 주세요');

        $db = getDB();
        $today = date('Y-m-d');
        $dayNames = ['일','월','화','수','목','금','토'];

        // 반 정보 + 학생 수
        $stmt = $db->prepare('
            SELECT c.id, c.display_name, c.coach_name, c.color,
                   (SELECT COUNT(*) FROM junior_class_students cs WHERE cs.class_id = c.id AND cs.is_active = 1) as student_count
            FROM junior_classes c WHERE c.id = ?
        ');
        $stmt->execute([$classId]);
        $classInfo = $stmt->fetch();
        if (!$classInfo) jsonError('반을 찾을 수 없습니다');

        $total = (int)$classInfo['student_count'];

        // 주간 캘린더 (시작된 주만)
        $stmt = $db->prepare('SELECT week_start, week_end, required_count FROM junior_weekly_calendar WHERE week_start <= ? ORDER BY week_start');
        $stmt->execute([$today]);
        $weeks = $stmt->fetchAll();

        // 해당 반의 sound_homework 집계 (날짜별)
        $firstDate = $weeks[0]['week_start'] ?? $today;
        $stmt = $db->prepare('
            SELECT check_date, SUM(sound_homework) as submitted
            FROM junior_daily_checklist
            WHERE class_id = ? AND check_date >= ?
            GROUP BY check_date
        ');
        $stmt->execute([$classId, $firstDate]);
        $hwByDate = [];
        foreach ($stmt->fetchAll() as $r) {
            $hwByDate[$r['check_date']] = (int)$r['submitted'];
        }

        // 기간별 달성률 계산
        $buildPeriod = function($type) use ($weeks, $today, $dayNames, $total, $hwByDate) {
            $periods = [];
            if ($type === 'weekly') {
                foreach ($weeks as $w) {
                    $dates = [];
                    $datesUntilToday = [];
                    $d = $w['week_start'];
                    while ($d <= $w['week_end']) {
                        $dates[] = $d;
                        if ($d <= $today) $datesUntilToday[] = $d;
                        $d = date('Y-m-d', strtotime($d . ' +1 day'));
                    }
                    $s = (int)date('n', strtotime($w['week_start'])) . '/' . (int)date('j', strtotime($w['week_start']));
                    $e = (int)date('n', strtotime($w['week_end'])) . '/' . (int)date('j', strtotime($w['week_end']));
                    $req = (int)$w['required_count'];
                    $submitted = 0;
                    foreach ($dates as $d) $submitted += $hwByDate[$d] ?? 0;
                    $possible = $total * $req;
                    $isCurrent = ($today >= $w['week_start'] && $today <= $w['week_end']);
                    $entry = [
                        'label' => $s . '~' . $e, 'submitted' => $submitted, 'possible' => $possible,
                        'rate' => $possible > 0 ? round($submitted / $possible * 100, 1) : 0,
                        'required' => $req, 'isCurrent' => $isCurrent,
                    ];
                    // 진행 중인 기간: 오늘까지 기준 값도 추가
                    if ($isCurrent && count($datesUntilToday) < count($dates)) {
                        $elapsedDays = count($datesUntilToday);
                        $possibleSoFar = $total * $elapsedDays;
                        $entry['elapsed_days'] = $elapsedDays;
                        $entry['total_days'] = count($dates);
                        $entry['possible_so_far'] = $possibleSoFar;
                        $entry['rate_so_far'] = $possibleSoFar > 0 ? round($submitted / $possibleSoFar * 100, 1) : 0;
                    }
                    $periods[] = $entry;
                }
            } elseif ($type === 'monthly') {
                $monthGroups = [];
                foreach ($weeks as $w) { $monthGroups[date('Y-m', strtotime($w['week_start']))][] = $w; }
                foreach ($monthGroups as $mk => $mWeeks) {
                    $totalReq = 0; $submitted = 0; $isCurrent = false;
                    $allDates = []; $datesUntilToday = [];
                    foreach ($mWeeks as $w) {
                        $totalReq += (int)$w['required_count'];
                        if ($today >= $w['week_start'] && $today <= $w['week_end']) $isCurrent = true;
                        $d = $w['week_start'];
                        while ($d <= $w['week_end']) {
                            $allDates[] = $d;
                            if ($d <= $today) $datesUntilToday[] = $d;
                            $submitted += $hwByDate[$d] ?? 0;
                            $d = date('Y-m-d', strtotime($d . ' +1 day'));
                        }
                    }
                    if (date('Y-m') === $mk) $isCurrent = true;
                    $possible = $total * $totalReq;
                    $entry = [
                        'label' => (int)date('n', strtotime($mk . '-01')) . '월', 'submitted' => $submitted, 'possible' => $possible,
                        'rate' => $possible > 0 ? round($submitted / $possible * 100, 1) : 0,
                        'required' => $totalReq, 'isCurrent' => $isCurrent,
                    ];
                    if ($isCurrent && count($datesUntilToday) < count($allDates)) {
                        $elapsedDays = count($datesUntilToday);
                        $possibleSoFar = $total * $elapsedDays;
                        $entry['elapsed_days'] = $elapsedDays;
                        $entry['total_days'] = count($allDates);
                        $entry['possible_so_far'] = $possibleSoFar;
                        $entry['rate_so_far'] = $possibleSoFar > 0 ? round($submitted / $possibleSoFar * 100, 1) : 0;
                    }
                    $periods[] = $entry;
                }
            } elseif ($type === 'daily') {
                foreach ($weeks as $w) {
                    $d = $w['week_start'];
                    while ($d <= $w['week_end'] && $d <= $today) {
                        $ts = strtotime($d);
                        $submitted = $hwByDate[$d] ?? 0;
                        $periods[] = [
                            'label' => (int)date('n', $ts) . '/' . (int)date('j', $ts) . '(' . $dayNames[date('w', $ts)] . ')',
                            'submitted' => $submitted, 'possible' => $total,
                            'rate' => $total > 0 ? round($submitted / $total * 100, 1) : 0,
                            'required' => 1, 'isCurrent' => ($d === $today),
                        ];
                        $d = date('Y-m-d', strtotime($d . ' +1 day'));
                    }
                }
            }
            return $periods;
        };

        $result = [
            'classInfo' => [
                'id' => (int)$classInfo['id'],
                'name' => $classInfo['display_name'],
                'coach' => $classInfo['coach_name'] ?? '',
                'color' => $classInfo['color'] ?? '#FF7E17',
                'students' => $total,
            ],
        ];
        foreach (['weekly', 'monthly', 'daily'] as $type) {
            $periods = $buildPeriod($type);
            $result[$type] = [
                'labels' => array_map(fn($p) => $p['label'], $periods),
                'data'   => $periods,
            ];
        }

        jsonSuccess($result);
        break;

    // 소리과제 특별관리 학생 (단일 반)
    case 'homework_alerts':
        requireAdmin(['coach', 'admin_teacher']);
        $classId = (int)($_GET['class_id'] ?? 0);
        if (!$classId) jsonError('반을 선택해 주세요');

        $db = getDB();
        $today = date('Y-m-d');

        // 해당 반 학생 + 마지막 소리과제 제출일
        $stmt = $db->prepare("
            SELECT s.id, s.name, cs.class_id, c.display_name as class_name,
                   (SELECT MAX(dc.check_date) FROM junior_daily_checklist dc
                    WHERE dc.student_id = s.id AND dc.sound_homework = 1) as last_hw_date
            FROM junior_students s
            JOIN junior_class_students cs ON s.id = cs.student_id AND cs.is_active = 1
            JOIN junior_classes c ON cs.class_id = c.id
            WHERE cs.class_id = ? AND s.is_active = 1
            ORDER BY s.name
        ");
        $stmt->execute([$classId]);
        $students = $stmt->fetchAll();

        $alerts = [];
        $todayTs = strtotime($today);
        foreach ($students as $s) {
            if ($s['last_hw_date']) {
                $daysSince = (int)(($todayTs - strtotime($s['last_hw_date'])) / 86400);
            } else {
                $daysSince = null;
            }

            if ($daysSince === null || $daysSince >= 3) {
                if ($daysSince === null || $daysSince >= 10) {
                    $level = 'red';
                } elseif ($daysSince >= 5) {
                    $level = 'orange';
                } else {
                    $level = 'yellow';
                }
                $alerts[] = [
                    'student_id' => (int)$s['id'],
                    'name'       => $s['name'],
                    'class_name' => $s['class_name'],
                    'class_id'   => (int)$s['class_id'],
                    'last_hw_date' => $s['last_hw_date'],
                    'days_since' => $daysSince,
                    'level'      => $level,
                ];
            }
        }

        // 심각도순 정렬
        usort($alerts, function($a, $b) {
            $aD = $a['days_since'] ?? 9999;
            $bD = $b['days_since'] ?? 9999;
            if ($aD !== $bD) return $bD - $aD;
            return strcmp($a['name'], $b['name']);
        });

        jsonSuccess(['alerts' => $alerts]);
        break;

    // ============================================
    // 메시지 기능 (코치 ↔ 학부모 1:1)
    // ============================================

    // 내 반 대화 스레드 목록
    case 'msg_threads':
        $admin = requireAdmin(['coach']);
        $classId = (int)($_GET['class_id'] ?? 0);
        $db = getDB();

        // 코치 담당 반 ID 목록
        $stmt = $db->prepare('SELECT class_id FROM junior_admin_classes WHERE admin_id = ? AND is_active = 1');
        $stmt->execute([$admin['admin_id']]);
        $myClassIds = array_column($stmt->fetchAll(), 'class_id');
        if (empty($myClassIds)) jsonSuccess(['threads' => []]);

        // 특정 반 필터
        if ($classId && !in_array($classId, $myClassIds)) jsonError('접근 권한이 없습니다', 403);
        $filterIds = $classId ? [$classId] : $myClassIds;
        $placeholders = implode(',', array_fill(0, count($filterIds), '?'));

        $stmt = $db->prepare("
            SELECT t.id as thread_id, t.student_id, t.class_id, t.parent_phone, t.last_message_at,
                   s.name as student_name, c.display_name as class_name,
                   (SELECT body FROM junior_messages WHERE thread_id = t.id AND is_deleted = 0 ORDER BY created_at DESC LIMIT 1) as last_message,
                   (SELECT COUNT(*) FROM junior_messages m
                    WHERE m.thread_id = t.id AND m.is_deleted = 0 AND m.sender_type = 'parent'
                    AND m.created_at > COALESCE(
                        (SELECT last_read_at FROM junior_message_reads
                         WHERE thread_id = t.id AND reader_type = 'coach' AND reader_id = ?),
                        '1970-01-01'
                    )) as unread_count
            FROM junior_message_threads t
            JOIN junior_students s ON t.student_id = s.id
            JOIN junior_classes c ON t.class_id = c.id
            WHERE t.class_id IN ($placeholders) AND t.is_active = 1
            ORDER BY t.last_message_at DESC
        ");
        $stmt->execute(array_merge([$admin['admin_id']], $filterIds));
        jsonSuccess(['threads' => $stmt->fetchAll()]);
        break;

    // 스레드 메시지 조회
    case 'msg_thread_detail':
        $admin = requireAdmin(['coach']);
        $threadId = (int)($_GET['thread_id'] ?? 0);
        $beforeId = (int)($_GET['before_id'] ?? 0);
        $limit = min(50, max(10, (int)($_GET['limit'] ?? 30)));
        if (!$threadId) jsonError('스레드 ID가 필요합니다');

        $db = getDB();
        $thread = verifyThreadAccessForCoach($admin['admin_id'], $threadId);

        $sql = 'SELECT id, sender_type, sender_name, body, image_path, created_at
                FROM junior_messages WHERE thread_id = ? AND is_deleted = 0';
        $params = [$threadId];
        if ($beforeId) { $sql .= ' AND id < ?'; $params[] = $beforeId; }
        $sql .= ' ORDER BY created_at DESC LIMIT ?';
        $params[] = $limit;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $messages = array_reverse($stmt->fetchAll()); // 오래된 것부터

        // 읽음 갱신
        $stmt = $db->prepare('
            INSERT INTO junior_message_reads (thread_id, reader_type, reader_id, last_read_at)
            VALUES (?, "coach", ?, NOW())
            ON DUPLICATE KEY UPDATE last_read_at = NOW()
        ');
        $stmt->execute([$threadId, $admin['admin_id']]);

        // 스레드 정보
        $stmt = $db->prepare('SELECT s.name as student_name, c.display_name as class_name
                              FROM junior_message_threads t
                              JOIN junior_students s ON t.student_id = s.id
                              JOIN junior_classes c ON t.class_id = c.id WHERE t.id = ?');
        $stmt->execute([$threadId]);
        $threadInfo = $stmt->fetch();

        jsonSuccess(['messages' => $messages, 'thread' => $threadInfo]);
        break;

    // 메시지 전송 (코치)
    case 'msg_send':
        if ($method !== 'POST') jsonError('POST만 허용됩니다', 405);
        $admin = requireAdmin(['coach']);

        // FormData 지원 (이미지 첨부)
        $threadId = (int)($_POST['thread_id'] ?? 0);
        $body = trim($_POST['body'] ?? '');
        if (!$threadId) jsonError('스레드 ID가 필요합니다');
        if (!$body && empty($_FILES['image'])) jsonError('메시지를 입력해 주세요');

        $db = getDB();
        $thread = verifyThreadAccessForCoach($admin['admin_id'], $threadId);

        // 이미지 업로드
        $imagePath = null;
        if (!empty($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $imagePath = uploadImage($_FILES['image'], MSG_UPLOAD_DIR, (string)$threadId);
        }

        $stmt = $db->prepare('
            INSERT INTO junior_messages (thread_id, sender_type, sender_id, sender_name, body, image_path)
            VALUES (?, "coach", ?, ?, ?, ?)
        ');
        $stmt->execute([$threadId, $admin['admin_id'], $admin['admin_name'], $body ?: '', $imagePath]);
        $msgId = (int)$db->lastInsertId();

        // 스레드 마지막 메시지 시간 갱신
        $stmt = $db->prepare('UPDATE junior_message_threads SET last_message_at = NOW() WHERE id = ?');
        $stmt->execute([$threadId]);

        // 읽음 갱신 (본인 메시지이므로)
        $stmt = $db->prepare('
            INSERT INTO junior_message_reads (thread_id, reader_type, reader_id, last_read_at)
            VALUES (?, "coach", ?, NOW())
            ON DUPLICATE KEY UPDATE last_read_at = NOW()
        ');
        $stmt->execute([$threadId, $admin['admin_id']]);

        jsonSuccess([
            'message' => [
                'id' => $msgId,
                'sender_type' => 'coach',
                'sender_name' => $admin['admin_name'],
                'body' => $body ?: '',
                'image_path' => $imagePath,
                'created_at' => date('Y-m-d H:i:s'),
            ]
        ], '메시지를 보냈습니다');
        break;

    // 스레드 읽음 처리
    case 'msg_mark_read':
        if ($method !== 'POST') jsonError('POST만 허용됩니다', 405);
        $admin = requireAdmin(['coach']);
        $input = getJsonInput();
        $threadId = (int)($input['thread_id'] ?? 0);
        if (!$threadId) jsonError('스레드 ID가 필요합니다');

        $db = getDB();
        verifyThreadAccessForCoach($admin['admin_id'], $threadId);

        $stmt = $db->prepare('
            INSERT INTO junior_message_reads (thread_id, reader_type, reader_id, last_read_at)
            VALUES (?, "coach", ?, NOW())
            ON DUPLICATE KEY UPDATE last_read_at = NOW()
        ');
        $stmt->execute([$threadId, $admin['admin_id']]);
        jsonSuccess([], '읽음 처리 되었습니다');
        break;

    // 안 읽은 메시지 총 수
    case 'msg_unread_total':
        $admin = requireAdmin(['coach']);
        $db = getDB();

        $stmt = $db->prepare('SELECT class_id FROM junior_admin_classes WHERE admin_id = ? AND is_active = 1');
        $stmt->execute([$admin['admin_id']]);
        $myClassIds = array_column($stmt->fetchAll(), 'class_id');
        if (empty($myClassIds)) jsonSuccess(['unread_messages' => 0]);

        $placeholders = implode(',', array_fill(0, count($myClassIds), '?'));
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(sub.cnt), 0) as total_unread FROM (
                SELECT (SELECT COUNT(*) FROM junior_messages m
                        WHERE m.thread_id = t.id AND m.is_deleted = 0 AND m.sender_type = 'parent'
                        AND m.created_at > COALESCE(
                            (SELECT last_read_at FROM junior_message_reads
                             WHERE thread_id = t.id AND reader_type = 'coach' AND reader_id = ?),
                            '1970-01-01'
                        )) as cnt
                FROM junior_message_threads t
                WHERE t.class_id IN ($placeholders) AND t.is_active = 1
            ) sub
        ");
        $stmt->execute(array_merge([$admin['admin_id']], $myClassIds));
        $total = (int)$stmt->fetchColumn();
        jsonSuccess(['unread_messages' => $total]);
        break;

    // ============================================
    // 공지사항 기능 (코치 → 학부모)
    // ============================================

    // 공지 작성
    case 'ann_create':
        if ($method !== 'POST') jsonError('POST만 허용됩니다', 405);
        $admin = requireAdmin(['coach']);

        $classId = (int)($_POST['class_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['body'] ?? '');
        $isPinned = (int)($_POST['is_pinned'] ?? 0);

        if (!$classId) jsonError('반을 선택해 주세요');
        if (!$title) jsonError('제목을 입력해 주세요');
        if (!$body) jsonError('내용을 입력해 주세요');

        $db = getDB();
        verifyClassAccess($admin['admin_id'], $classId);

        $imagePath = null;
        if (!empty($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $imagePath = uploadImage($_FILES['image'], ANN_UPLOAD_DIR, (string)$classId);
        }

        $stmt = $db->prepare('
            INSERT INTO junior_announcements (class_id, author_id, author_name, title, body, image_path, is_pinned)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([$classId, $admin['admin_id'], $admin['admin_name'], $title, $body, $imagePath, $isPinned]);
        jsonSuccess(['id' => (int)$db->lastInsertId()], '공지가 등록되었습니다');
        break;

    // 공지 목록
    case 'ann_list':
        $admin = requireAdmin(['coach']);
        $classId = (int)($_GET['class_id'] ?? 0);
        if (!$classId) jsonError('반을 선택해 주세요');

        $db = getDB();
        verifyClassAccess($admin['admin_id'], $classId);

        // 해당 반 학생 수 (읽음률 계산용)
        $stmt = $db->prepare('
            SELECT COUNT(DISTINCT s.phone) as parent_count
            FROM junior_students s
            JOIN junior_class_students cs ON s.id = cs.student_id AND cs.is_active = 1
            WHERE cs.class_id = ? AND s.is_active = 1 AND s.phone IS NOT NULL AND s.phone != ""
        ');
        $stmt->execute([$classId]);
        $parentCount = (int)$stmt->fetchColumn();

        $stmt = $db->prepare('
            SELECT a.*,
                   (SELECT COUNT(*) FROM junior_announcement_reads ar WHERE ar.announcement_id = a.id) as read_count
            FROM junior_announcements a
            WHERE a.class_id = ? AND a.is_active = 1
            ORDER BY a.is_pinned DESC, a.created_at DESC
        ');
        $stmt->execute([$classId]);
        $announcements = $stmt->fetchAll();

        foreach ($announcements as &$ann) {
            $ann['parent_count'] = $parentCount;
        }

        jsonSuccess(['announcements' => $announcements, 'parent_count' => $parentCount]);
        break;

    // 공지 삭제 (soft delete)
    case 'ann_delete':
        if ($method !== 'POST') jsonError('POST만 허용됩니다', 405);
        $admin = requireAdmin(['coach']);
        $input = getJsonInput();
        $annId = (int)($input['announcement_id'] ?? 0);
        if (!$annId) jsonError('공지 ID가 필요합니다');

        $db = getDB();
        // 권한 확인: 본인이 작성한 공지인지
        $stmt = $db->prepare('SELECT class_id, author_id FROM junior_announcements WHERE id = ? AND is_active = 1');
        $stmt->execute([$annId]);
        $ann = $stmt->fetch();
        if (!$ann) jsonError('공지를 찾을 수 없습니다');
        verifyClassAccess($admin['admin_id'], $ann['class_id']);

        $stmt = $db->prepare('UPDATE junior_announcements SET is_active = 0 WHERE id = ?');
        $stmt->execute([$annId]);
        jsonSuccess([], '공지가 삭제되었습니다');
        break;

    // 메시지 이미지 서빙
    case 'msg_image':
        $admin = requireAdmin(['coach']);
        $path = trim($_GET['path'] ?? '');
        if (!$path) jsonError('경로가 필요합니다');
        // 경로 조작 방지
        if (str_contains($path, '..') || str_starts_with($path, '/')) jsonError('잘못된 경로', 400);
        $fullPath = MSG_UPLOAD_DIR . '/' . $path;
        if (!file_exists($fullPath)) jsonError('파일을 찾을 수 없습니다', 404);
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        header('Content-Type: ' . $finfo->file($fullPath));
        header('Cache-Control: private, max-age=86400');
        readfile($fullPath);
        exit;

    // 공지 이미지 서빙
    case 'ann_image':
        $admin = requireAdmin(['coach']);
        $path = trim($_GET['path'] ?? '');
        if (!$path) jsonError('경로가 필요합니다');
        if (str_contains($path, '..') || str_starts_with($path, '/')) jsonError('잘못된 경로', 400);
        $fullPath = ANN_UPLOAD_DIR . '/' . $path;
        if (!file_exists($fullPath)) jsonError('파일을 찾을 수 없습니다', 404);
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        header('Content-Type: ' . $finfo->file($fullPath));
        header('Cache-Control: private, max-age=86400');
        readfile($fullPath);
        exit;

    // 공지 읽음 현황
    case 'ann_read_status':
        $admin = requireAdmin(['coach']);
        $annId = (int)($_GET['announcement_id'] ?? 0);
        if (!$annId) jsonError('공지 ID가 필요합니다');

        $db = getDB();
        $stmt = $db->prepare('SELECT class_id FROM junior_announcements WHERE id = ? AND is_active = 1');
        $stmt->execute([$annId]);
        $ann = $stmt->fetch();
        if (!$ann) jsonError('공지를 찾을 수 없습니다');
        verifyClassAccess($admin['admin_id'], $ann['class_id']);

        // 읽은 학부모
        $stmt = $db->prepare('SELECT parent_phone, read_at FROM junior_announcement_reads WHERE announcement_id = ? ORDER BY read_at DESC');
        $stmt->execute([$annId]);
        $readers = $stmt->fetchAll();

        // 전체 학부모 (해당 반)
        $stmt = $db->prepare('
            SELECT DISTINCT s.phone, s.name as student_name
            FROM junior_students s
            JOIN junior_class_students cs ON s.id = cs.student_id AND cs.is_active = 1
            WHERE cs.class_id = ? AND s.is_active = 1 AND s.phone IS NOT NULL AND s.phone != ""
        ');
        $stmt->execute([$ann['class_id']]);
        $allParents = $stmt->fetchAll();

        $readPhones = array_column($readers, 'parent_phone');
        $unread = array_filter($allParents, fn($p) => !in_array($p['phone'], $readPhones));

        jsonSuccess([
            'read' => $readers,
            'unread' => array_values($unread),
            'total' => count($allParents),
            'read_count' => count($readers),
        ]);
        break;

    default:
        jsonError('알 수 없는 요청입니다', 404);
}

/**
 * 관리쌤 반 접근 권한 검증
 */
function verifyClassAccess(int $adminId, int $classId): void {
    if (!$classId) return; // classId 0은 다른 곳에서 처리
    $db = getDB();
    $stmt = $db->prepare('SELECT 1 FROM junior_admin_classes WHERE admin_id = ? AND class_id = ? AND is_active = 1');
    $stmt->execute([$adminId, $classId]);
    if (!$stmt->fetch()) jsonError('접근 권한이 없습니다', 403);
}

/**
 * 코치의 메시지 스레드 접근 권한 검증
 */
function verifyThreadAccessForCoach(int $adminId, int $threadId): array {
    $db = getDB();
    $stmt = $db->prepare('
        SELECT t.* FROM junior_message_threads t
        JOIN junior_admin_classes ac ON t.class_id = ac.class_id
        WHERE t.id = ? AND ac.admin_id = ? AND ac.is_active = 1
    ');
    $stmt->execute([$threadId, $adminId]);
    $thread = $stmt->fetch();
    if (!$thread) jsonError('접근 권한이 없습니다', 403);
    return $thread;
}
