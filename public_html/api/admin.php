<?php
/**
 * 관리쌤/부모 API
 * - login: ID+비밀번호 로그인 (관리쌤)
 * - phone_login: 전화번호 로그인 (부모) — 세션 기반, DB 계정 생성 없음
 * - auto_login: 핑거프린트 자동 로그인 (관리쌤: admin_fingerprints, 부모: parent_fingerprints)
 * - logout: 핑거프린트 비활성화 + 세션 종료
 * - check_session, my_students, student_dashboard
 */
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json; charset=utf-8');

$action = getAction();
$method = getMethod();

switch ($action) {
    // 관리쌤 ID+비밀번호 로그인
    case 'login':
        if ($method !== 'POST') jsonError('POST만 허용됩니다', 405);
        $input = getJsonInput();
        $loginId = trim($input['login_id'] ?? '');
        $password = trim($input['password'] ?? '');
        $fingerprint = trim($input['fingerprint'] ?? '');
        $deviceInfo = $input['device_info'] ?? null;

        if (!$loginId || !$password) jsonError('아이디와 비밀번호를 입력해 주세요');

        $db = getDB();
        $stmt = $db->prepare('SELECT * FROM junior_admins WHERE login_id = ? AND role IN ("admin_teacher", "parent") AND is_active = 1');
        $stmt->execute([$loginId]);
        $admin = $stmt->fetch();

        if (!$admin || !$admin['password_hash'] || !password_verify($password, $admin['password_hash'])) {
            jsonError('아이디 또는 비밀번호가 올바르지 않습니다');
        }

        loginAdmin($admin['id'], $admin['name'], $admin['role']);

        // 핑거프린트 저장
        if ($fingerprint) {
            saveFingerprint($db, $admin['id'], $fingerprint, $deviceInfo);
        }

        jsonSuccess([
            'admin' => [
                'id'   => $admin['id'],
                'name' => $admin['name'],
                'role' => $admin['role'],
            ]
        ], '로그인 되었습니다');
        break;

    // 부모 전화번호 로그인 (세션 기반, DB 계정 생성 없음)
    case 'phone_login':
        if ($method !== 'POST') jsonError('POST만 허용됩니다', 405);
        $input = getJsonInput();
        $phone = trim($input['phone'] ?? '');
        $fingerprint = trim($input['fingerprint'] ?? '');
        $deviceInfo = $input['device_info'] ?? null;

        if (!$phone) jsonError('전화번호를 입력해 주세요');

        // 전화번호 정규화 (하이픈, 공백 제거)
        $phoneClean = preg_replace('/[\s\-–—]/', '', $phone);

        $db = getDB();

        // 학생 테이블에서 전화번호 매칭
        if (strlen($phoneClean) <= 4) {
            // 뒷 4자리로 검색
            $stmt = $db->prepare('SELECT id, name, phone, phone_last4 FROM junior_students WHERE phone_last4 = ? AND is_active = 1');
            $stmt->execute([$phoneClean]);
        } else {
            // 전체 번호로 검색 (하이픈 등 제거해서 비교)
            $stmt = $db->prepare('SELECT id, name, phone, phone_last4 FROM junior_students WHERE REPLACE(REPLACE(REPLACE(REPLACE(phone, "-", ""), " ", ""), "–", ""), "—", "") = ? AND is_active = 1');
            $stmt->execute([$phoneClean]);
        }
        $matchedStudents = $stmt->fetchAll();

        if (empty($matchedStudents)) {
            jsonError('등록된 번호가 없습니다. 소리튠영어에 문의해 주세요.');
        }

        // 세션 기반 로그인 (junior_admins 계정 생성 없음)
        $studentIds = array_map('intval', array_column($matchedStudents, 'id'));
        $parentName = $matchedStudents[0]['name'] . ' 부모님';
        loginParent($phoneClean, $parentName, $studentIds);

        // 핑거프린트 저장 (전화번호 기반)
        if ($fingerprint) {
            saveParentFingerprint($db, $phoneClean, $fingerprint, $deviceInfo);
        }

        jsonSuccess([
            'admin' => [
                'id'   => 0,
                'name' => $parentName,
                'role' => 'parent',
            ],
            'matched_students' => count($matchedStudents),
        ], '로그인 되었습니다');
        break;

    // 핑거프린트 자동 로그인
    case 'auto_login':
        if ($method !== 'POST') jsonError('POST만 허용됩니다', 405);
        $input = getJsonInput();
        $fingerprint = trim($input['fingerprint'] ?? '');

        if (!$fingerprint) jsonError('핑거프린트가 필요합니다');

        $db = getDB();

        // 1차: 관리쌤 핑거프린트 체크
        $stmt = $db->prepare('
            SELECT af.admin_id, a.name, a.role, a.is_active
            FROM junior_admin_fingerprints af
            JOIN junior_admins a ON af.admin_id = a.id
            WHERE af.fingerprint = ? AND af.is_active = 1 AND a.is_active = 1
            AND a.role = "admin_teacher"
            ORDER BY af.last_used_at DESC
            LIMIT 1
        ');
        $stmt->execute([$fingerprint]);
        $match = $stmt->fetch();

        if ($match) {
            // last_used_at 갱신
            $stmt = $db->prepare('UPDATE junior_admin_fingerprints SET last_used_at = NOW() WHERE fingerprint = ? AND admin_id = ?');
            $stmt->execute([$fingerprint, $match['admin_id']]);

            loginAdmin($match['admin_id'], $match['name'], $match['role']);

            jsonSuccess([
                'logged_in' => true,
                'admin' => [
                    'id'   => $match['admin_id'],
                    'name' => $match['name'],
                    'role' => $match['role'],
                ]
            ], '자동 로그인 되었습니다');
            break;
        }

        // 2차: 부모 핑거프린트 체크 (전화번호 기반)
        $stmt = $db->prepare('
            SELECT phone FROM junior_parent_fingerprints
            WHERE fingerprint = ? AND is_active = 1
            ORDER BY last_used_at DESC LIMIT 1
        ');
        $stmt->execute([$fingerprint]);
        $parentFp = $stmt->fetch();

        if ($parentFp) {
            $phone = $parentFp['phone'];

            // 해당 전화번호로 학생 검색
            if (strlen($phone) <= 4) {
                $stmt = $db->prepare('SELECT id, name FROM junior_students WHERE phone_last4 = ? AND is_active = 1');
            } else {
                $stmt = $db->prepare('SELECT id, name FROM junior_students WHERE REPLACE(REPLACE(REPLACE(REPLACE(phone, "-", ""), " ", ""), "–", ""), "—", "") = ? AND is_active = 1');
            }
            $stmt->execute([$phone]);
            $students = $stmt->fetchAll();

            if (!empty($students)) {
                $studentIds = array_map('intval', array_column($students, 'id'));
                $parentName = $students[0]['name'] . ' 부모님';

                // last_used_at 갱신
                $stmt = $db->prepare('UPDATE junior_parent_fingerprints SET last_used_at = NOW() WHERE fingerprint = ? AND phone = ?');
                $stmt->execute([$fingerprint, $phone]);

                loginParent($phone, $parentName, $studentIds);

                jsonSuccess([
                    'logged_in' => true,
                    'admin' => [
                        'id'   => 0,
                        'name' => $parentName,
                        'role' => 'parent',
                    ]
                ], '자동 로그인 되었습니다');
                break;
            }
        }

        jsonSuccess(['logged_in' => false]);
        break;

    // 로그아웃 (핑거프린트 비활성화)
    case 'logout':
        if ($method !== 'POST') jsonError('POST만 허용됩니다', 405);
        $input = getJsonInput();
        $fingerprint = trim($input['fingerprint'] ?? '');

        $session = getAdminSession();

        // 핑거프린트 비활성화
        if ($session && $fingerprint) {
            $db = getDB();
            if ($session['admin_role'] === 'parent') {
                // 부모: 전화번호 기반 핑거프린트
                $phone = $session['parent_phone'] ?? '';
                if ($phone) {
                    $stmt = $db->prepare('UPDATE junior_parent_fingerprints SET is_active = 0 WHERE phone = ? AND fingerprint = ?');
                    $stmt->execute([$phone, $fingerprint]);
                }
            } else {
                // 관리쌤/코치: admin_id 기반 핑거프린트
                $stmt = $db->prepare('UPDATE junior_admin_fingerprints SET is_active = 0 WHERE admin_id = ? AND fingerprint = ?');
                $stmt->execute([$session['admin_id'], $fingerprint]);
            }
        }

        logoutAdmin();
        jsonSuccess([], '로그아웃 되었습니다');
        break;

    // 세션 확인
    case 'check_session':
        $session = getAdminSession();
        if ($session && in_array($session['admin_role'], ['admin_teacher', 'parent'])) {
            jsonSuccess(['logged_in' => true, 'admin' => $session]);
        } else {
            jsonSuccess(['logged_in' => false]);
        }
        break;

    // 연결된 학생들
    case 'my_students':
        $admin = requireAdmin(['admin_teacher', 'parent']);
        $db = getDB();

        if ($admin['admin_role'] === 'parent') {
            // 부모: 세션에 저장된 학생 ID로 직접 조회
            $studentIds = $admin['parent_student_ids'] ?? [];
            if (empty($studentIds)) {
                jsonSuccess(['students' => []]);
                break;
            }
            $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
            $stmt = $db->prepare("
                SELECT s.id, s.name, s.grade, c.display_name as class_name
                FROM junior_students s
                LEFT JOIN junior_class_students cs ON s.id = cs.student_id AND cs.is_primary = 1 AND cs.is_active = 1
                LEFT JOIN junior_classes c ON cs.class_id = c.id
                WHERE s.id IN ($placeholders) AND s.is_active = 1
            ");
            $stmt->execute($studentIds);
            $students = $stmt->fetchAll();

            // 각 학생의 코인 계산
            foreach ($students as &$s) {
                $s['total_coins'] = getStudentTotalCoins($s['id']);
            }

            jsonSuccess(['students' => $students]);
            break;
        } else {
            // 관리쌤: 담당 반 전체
            $stmt = $db->prepare('
                SELECT s.id, s.name, s.grade, c.display_name as class_name,
                       COALESCE(SUM(sr.quantity * rt.coin_value), 0) as total_coins
                FROM junior_admin_classes ac
                JOIN junior_class_students cs ON ac.class_id = cs.class_id AND cs.is_active = 1
                JOIN junior_students s ON cs.student_id = s.id
                LEFT JOIN junior_classes c ON cs.class_id = c.id
                LEFT JOIN junior_student_rewards sr ON s.id = sr.student_id
                LEFT JOIN junior_reward_types rt ON sr.reward_type_id = rt.id
                WHERE ac.admin_id = ? AND ac.is_active = 1 AND s.is_active = 1
                GROUP BY s.id
                ORDER BY c.display_name, s.name
            ');
            $stmt->execute([$admin['admin_id']]);
        }

        jsonSuccess(['students' => $stmt->fetchAll()]);
        break;

    // 학생 대시보드 조회
    case 'student_dashboard':
        $admin = requireAdmin(['admin_teacher', 'parent']);
        $studentId = (int)($_GET['student_id'] ?? 0);

        if (!$studentId) jsonError('학생 ID가 필요합니다');

        $db = getDB();

        // 권한 확인 (부모는 세션 학생 ID, 관리쌤은 담당 반만)
        if ($admin['admin_role'] === 'parent') {
            $parentStudentIds = $admin['parent_student_ids'] ?? [];
            if (!in_array($studentId, $parentStudentIds)) {
                jsonError('접근 권한이 없습니다', 403);
            }
            // skip DB check - session has the authority
            $accessOk = true;
        } else {
            $stmt = $db->prepare('
                SELECT 1 FROM junior_admin_classes ac
                JOIN junior_class_students cs ON ac.class_id = cs.class_id
                WHERE ac.admin_id = ? AND cs.student_id = ? AND ac.is_active = 1 AND cs.is_active = 1
            ');
            $stmt->execute([$admin['admin_id'], $studentId]);
            if (!$stmt->fetch()) jsonError('접근 권한이 없습니다', 403);
        }

        // 학생 정보
        $stmt = $db->prepare('
            SELECT s.id, s.name, s.grade,
                   c.display_name as class_name, c.coach_name
            FROM junior_students s
            LEFT JOIN junior_class_students cs ON s.id = cs.student_id AND cs.is_primary = 1 AND cs.is_active = 1
            LEFT JOIN junior_classes c ON cs.class_id = c.id
            WHERE s.id = ?
        ');
        $stmt->execute([$studentId]);
        $student = $stmt->fetch();

        $rewards = getStudentRewards($studentId);
        $totalCoins = getStudentTotalCoins($studentId);

        // 최근 체크리스트 (7일)
        $stmt = $db->prepare('
            SELECT check_date, zoom_attendance, posture_king, sound_homework, band_mission, leader_king
            FROM junior_daily_checklist
            WHERE student_id = ?
            ORDER BY check_date DESC LIMIT 7
        ');
        $stmt->execute([$studentId]);
        $checklists = $stmt->fetchAll();

        // ACE 성장 리포트 (발송된 것만)
        $stmt = $db->prepare('
            SELECT ace_level, result, report_token, report_sent_at
            FROM junior_ace_evaluations
            WHERE student_id = ?
            ORDER BY ace_level, created_at DESC
        ');
        $stmt->execute([$studentId]);
        $aceReports = $stmt->fetchAll();

        // BRAVO 성장 리포트 (report_token이 있는 confirmed 건)
        $stmt = $db->prepare('
            SELECT bravo_level, coach_result, report_token, confirmed_at
            FROM junior_bravo_submissions
            WHERE student_id = ? AND status = ? AND report_token IS NOT NULL
            ORDER BY bravo_level, created_at DESC
        ');
        $stmt->execute([$studentId, 'confirmed']);
        $bravoReports = $stmt->fetchAll();

        jsonSuccess([
            'student'       => $student,
            'rewards'       => $rewards,
            'total_coins'   => $totalCoins,
            'checklists'    => $checklists,
            'ace_reports'   => $aceReports,
            'bravo_reports' => $bravoReports,
        ]);
        break;

    // 관리쌤 대시보드 (담당 반 목록 + 과제율)
    case 'teacher_dashboard':
        $admin = requireAdmin(['admin_teacher']);
        $db = getDB();
        $today = date('Y-m-d');

        // 담당 반 목록
        $stmt = $db->prepare('
            SELECT c.id, c.display_name, c.coach_name, c.color,
                   COUNT(DISTINCT cs.student_id) as total_students
            FROM junior_admin_classes ac
            JOIN junior_classes c ON ac.class_id = c.id
            LEFT JOIN junior_class_students cs ON c.id = cs.class_id AND cs.is_active = 1
            WHERE ac.admin_id = ? AND ac.is_active = 1 AND c.is_active = 1
            GROUP BY c.id
            ORDER BY c.sort_order
        ');
        $stmt->execute([$admin['admin_id']]);
        $classes = $stmt->fetchAll();

        // 각 반의 오늘 과제 완료율 계산
        foreach ($classes as &$class) {
            $classId = $class['id'];
            $totalStudents = (int)$class['total_students'];

            if ($totalStudents > 0) {
                // 오늘 체크리스트 완료한 학생 수
                $stmt = $db->prepare('
                    SELECT COUNT(DISTINCT student_id) as completed_count
                    FROM junior_daily_checklist
                    WHERE class_id = ? AND check_date = ?
                    AND (zoom_attendance > 0 OR posture_king > 0 OR sound_homework > 0 OR band_mission > 0 OR leader_king > 0)
                ');
                $stmt->execute([$classId, $today]);
                $completedCount = (int)$stmt->fetchColumn();

                $class['completion_rate'] = $totalStudents > 0 ? round(($completedCount / $totalStudents) * 100) : 0;
                $class['completed_students'] = $completedCount;
            } else {
                $class['completion_rate'] = 0;
                $class['completed_students'] = 0;
            }
        }

        jsonSuccess(['classes' => $classes, 'date' => $today]);
        break;

    // 관리쌤용 반 상세 (생활미션 체크 + 학생별 카드 현황)
    case 'teacher_class_detail':
        $admin = requireAdmin(['admin_teacher']);
        $classId = (int)($_GET['class_id'] ?? 0);
        $date = trim($_GET['date'] ?? date('Y-m-d'));

        if (!$classId) jsonError('반 ID가 필요합니다');

        $db = getDB();

        // 권한 확인
        $stmt = $db->prepare('SELECT 1 FROM junior_admin_classes WHERE admin_id = ? AND class_id = ? AND is_active = 1');
        $stmt->execute([$admin['admin_id'], $classId]);
        if (!$stmt->fetch()) jsonError('접근 권한이 없습니다', 403);

        // 반 정보
        $stmt = $db->prepare('SELECT id, display_name, coach_name, color FROM junior_classes WHERE id = ?');
        $stmt->execute([$classId]);
        $classInfo = $stmt->fetch();

        // 학생 목록 + 체크리스트 + 카드 현황
        $stmt = $db->prepare('
            SELECT s.id, s.name, s.grade,
                   dc.zoom_attendance, dc.posture_king, dc.sound_homework, dc.band_mission, dc.leader_king, dc.reboot_card
            FROM junior_students s
            JOIN junior_class_students cs ON s.id = cs.student_id AND cs.is_active = 1
            LEFT JOIN junior_daily_checklist dc ON s.id = dc.student_id AND dc.check_date = ? AND dc.class_id = ?
            WHERE cs.class_id = ? AND s.is_active = 1
            ORDER BY s.name
        ');
        $stmt->execute([$date, $classId, $classId]);
        $students = $stmt->fetchAll();

        // 각 학생의 카드 현황 조회
        foreach ($students as &$student) {
            $rewards = getStudentRewards($student['id']);
            $totalCoins = getStudentTotalCoins($student['id']);
            $student['rewards'] = $rewards;
            $student['total_coins'] = $totalCoins;

            // 체크리스트 값 기본값 설정
            $student['zoom_attendance'] = (int)($student['zoom_attendance'] ?? 0);
            $student['posture_king'] = (int)($student['posture_king'] ?? 0);
            $student['sound_homework'] = (int)($student['sound_homework'] ?? 0);
            $student['band_mission'] = (int)($student['band_mission'] ?? 0);
            $student['leader_king'] = (int)($student['leader_king'] ?? 0);
            $student['reboot_card'] = (int)($student['reboot_card'] ?? 0);
        }

        jsonSuccess([
            'class' => $classInfo,
            'students' => $students,
            'date' => $date,
        ]);
        break;

    // 관리쌤용 체크리스트 저장
    case 'teacher_save_checklist':
        if ($method !== 'POST') jsonError('POST만 허용됩니다', 405);
        $admin = requireAdmin(['admin_teacher']);
        $input = getJsonInput();

        $classId = (int)($input['class_id'] ?? 0);
        $date = trim($input['date'] ?? date('Y-m-d'));
        $items = $input['items'] ?? [];

        if (!$classId || empty($items)) jsonError('데이터가 부족합니다');

        $db = getDB();

        // 권한 확인
        $stmt = $db->prepare('SELECT 1 FROM junior_admin_classes WHERE admin_id = ? AND class_id = ? AND is_active = 1');
        $stmt->execute([$admin['admin_id'], $classId]);
        if (!$stmt->fetch()) jsonError('접근 권한이 없습니다', 403);

        $db->beginTransaction();

        try {
            // 기존 체크리스트 조회
            $stmt = $db->prepare('
                SELECT student_id, zoom_attendance, posture_king, sound_homework, band_mission, leader_king, reboot_card
                FROM junior_daily_checklist
                WHERE class_id = ? AND check_date = ?
            ');
            $stmt->execute([$classId, $date]);
            $existing = [];
            while ($row = $stmt->fetch()) {
                $existing[$row['student_id']] = $row;
            }

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
                    coach_id = VALUES(coach_id),
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
                foreach (CHECKLIST_CARD_MAP as $checkField => $cardCode) {
                    if ($checkField === 'sound_homework') continue; // 꾸준왕은 주간 판정으로 별도 처리

                    $prevVal = $prev ? (int)$prev[$checkField] : 0;
                    $newVal = $fields[$checkField];

                    if ($newVal === 1 && $prevVal === 0) {
                        $result = changeReward($studentId, $cardCode, 1, 'checklist',
                            "체크리스트: {$checkField} ({$date})",
                            $admin['admin_id'], 'admin_teacher');
                        if (!$result['success'] && $result['error'] === 'weekly_limit_exceeded') {
                            throw new Exception($result['message'] ?? '주간 카드 한도 초과');
                        }
                    } elseif ($newVal === 0 && $prevVal === 1) {
                        changeReward($studentId, $cardCode, -1, 'checklist',
                            "체크리스트 해제: {$checkField} ({$date})",
                            $admin['admin_id'], 'admin_teacher');
                    }
                }

                // 꾸준왕 주간 판정
                evaluateSteadyForWeek($studentId, $date, $admin['admin_id'], 'admin_teacher');
            }

            $db->commit();
            jsonSuccess([], '체크리스트가 저장되었습니다');
        } catch (Exception $e) {
            $db->rollBack();
            jsonError('저장 중 오류가 발생했습니다: ' . $e->getMessage(), 500);
        }
        break;

    // ============================================
    // 메시지 기능 (학부모 ↔ 코치 1:1)
    // ============================================

    // 아직 대화를 시작하지 않은 코치 목록 (새 대화 시작용)
    case 'msg_available_coaches':
        $admin = requireAdmin(['parent']);
        $parentPhone = $admin['parent_phone'];
        $parentStudentIds = $admin['parent_student_ids'] ?? [];
        if (empty($parentStudentIds)) jsonSuccess(['available' => []]);

        $db = getDB();
        $placeholders = implode(',', array_fill(0, count($parentStudentIds), '?'));

        // 내 아이들의 반·코치 정보 (primary 반 기준)
        $stmt = $db->prepare("
            SELECT s.id as student_id, s.name as student_name, cs.class_id,
                   c.display_name as class_name, c.coach_name
            FROM junior_students s
            JOIN junior_class_students cs ON s.id = cs.student_id AND cs.is_primary = 1 AND cs.is_active = 1
            JOIN junior_classes c ON cs.class_id = c.id
            WHERE s.id IN ($placeholders) AND s.is_active = 1
        ");
        $stmt->execute($parentStudentIds);
        $studentClasses = $stmt->fetchAll();

        // 이미 스레드가 있는 (student_id, class_id) 쌍 조회
        $stmt = $db->prepare("
            SELECT student_id, class_id FROM junior_message_threads
            WHERE parent_phone = ? AND is_active = 1
        ");
        $stmt->execute([$parentPhone]);
        $existingPairs = [];
        foreach ($stmt->fetchAll() as $row) {
            $existingPairs[] = $row['student_id'] . '_' . $row['class_id'];
        }

        // 스레드가 없는 것만 필터
        $available = [];
        foreach ($studentClasses as $sc) {
            $key = $sc['student_id'] . '_' . $sc['class_id'];
            if (!in_array($key, $existingPairs)) {
                $available[] = $sc;
            }
        }

        jsonSuccess(['available' => $available]);
        break;

    // 내 아이들 대화 스레드 목록
    case 'msg_threads':
        $admin = requireAdmin(['parent']);
        $parentPhone = $admin['parent_phone'];
        $db = getDB();

        $stmt = $db->prepare("
            SELECT t.id as thread_id, t.student_id, t.class_id, t.last_message_at,
                   s.name as student_name, c.display_name as class_name, c.coach_name,
                   (SELECT body FROM junior_messages WHERE thread_id = t.id AND is_deleted = 0 ORDER BY created_at DESC LIMIT 1) as last_message,
                   (SELECT COUNT(*) FROM junior_messages m
                    WHERE m.thread_id = t.id AND m.is_deleted = 0 AND m.sender_type = 'coach'
                    AND m.created_at > COALESCE(
                        (SELECT last_read_at FROM junior_message_reads
                         WHERE thread_id = t.id AND reader_type = 'parent' AND reader_phone = ?),
                        '1970-01-01'
                    )) as unread_count
            FROM junior_message_threads t
            JOIN junior_students s ON t.student_id = s.id
            JOIN junior_classes c ON t.class_id = c.id
            WHERE t.parent_phone = ? AND t.is_active = 1
            ORDER BY t.last_message_at DESC
        ");
        $stmt->execute([$parentPhone, $parentPhone]);
        jsonSuccess(['threads' => $stmt->fetchAll()]);
        break;

    // 스레드 메시지 조회
    case 'msg_thread_detail':
        $admin = requireAdmin(['parent']);
        $threadId = (int)($_GET['thread_id'] ?? 0);
        $beforeId = (int)($_GET['before_id'] ?? 0);
        $limit = min(50, max(10, (int)($_GET['limit'] ?? 30)));
        if (!$threadId) jsonError('스레드 ID가 필요합니다');

        $db = getDB();
        $thread = verifyThreadAccessForParent($admin, $threadId);

        $sql = 'SELECT id, sender_type, sender_name, body, image_path, created_at
                FROM junior_messages WHERE thread_id = ? AND is_deleted = 0';
        $params = [$threadId];
        if ($beforeId) { $sql .= ' AND id < ?'; $params[] = $beforeId; }
        $sql .= ' ORDER BY created_at DESC LIMIT ?';
        $params[] = $limit;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $messages = array_reverse($stmt->fetchAll());

        // 읽음 갱신
        $stmt = $db->prepare('
            INSERT INTO junior_message_reads (thread_id, reader_type, reader_phone, last_read_at)
            VALUES (?, "parent", ?, NOW())
            ON DUPLICATE KEY UPDATE last_read_at = NOW()
        ');
        $stmt->execute([$threadId, $admin['parent_phone']]);

        // 스레드 정보
        $stmt = $db->prepare('SELECT s.name as student_name, c.display_name as class_name, c.coach_name
                              FROM junior_message_threads t
                              JOIN junior_students s ON t.student_id = s.id
                              JOIN junior_classes c ON t.class_id = c.id WHERE t.id = ?');
        $stmt->execute([$threadId]);
        $threadInfo = $stmt->fetch();

        jsonSuccess(['messages' => $messages, 'thread' => $threadInfo]);
        break;

    // 메시지 전송 (학부모) — student_id 기반으로 스레드 자동 생성
    case 'msg_send':
        if ($method !== 'POST') jsonError('POST만 허용됩니다', 405);
        $admin = requireAdmin(['parent']);
        $parentPhone = $admin['parent_phone'];

        $studentId = (int)($_POST['student_id'] ?? 0);
        $body = trim($_POST['body'] ?? '');
        if (!$studentId) jsonError('학생 ID가 필요합니다');
        if (!$body && empty($_FILES['image'])) jsonError('메시지를 입력해 주세요');

        // 권한: 자기 아이인지
        $parentStudentIds = $admin['parent_student_ids'] ?? [];
        if (!in_array($studentId, $parentStudentIds)) jsonError('접근 권한이 없습니다', 403);

        $db = getDB();

        // 학생의 주 반 조회
        $stmt = $db->prepare('SELECT class_id FROM junior_class_students WHERE student_id = ? AND is_primary = 1 AND is_active = 1');
        $stmt->execute([$studentId]);
        $classId = (int)$stmt->fetchColumn();
        if (!$classId) jsonError('학생의 반 정보를 찾을 수 없습니다');

        // 스레드 찾기 또는 생성
        $stmt = $db->prepare('SELECT id FROM junior_message_threads WHERE student_id = ? AND class_id = ? AND parent_phone = ?');
        $stmt->execute([$studentId, $classId, $parentPhone]);
        $threadId = (int)$stmt->fetchColumn();

        if (!$threadId) {
            $stmt = $db->prepare('INSERT INTO junior_message_threads (student_id, class_id, parent_phone, last_message_at) VALUES (?, ?, ?, NOW())');
            $stmt->execute([$studentId, $classId, $parentPhone]);
            $threadId = (int)$db->lastInsertId();
        }

        // 이미지 업로드
        $imagePath = null;
        if (!empty($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $imagePath = uploadImage($_FILES['image'], MSG_UPLOAD_DIR, (string)$threadId);
        }

        $stmt = $db->prepare('
            INSERT INTO junior_messages (thread_id, sender_type, sender_phone, sender_name, body, image_path)
            VALUES (?, "parent", ?, ?, ?, ?)
        ');
        $stmt->execute([$threadId, $parentPhone, $admin['admin_name'], $body ?: '', $imagePath]);
        $msgId = (int)$db->lastInsertId();

        // 스레드 마지막 메시지 시간 갱신
        $stmt = $db->prepare('UPDATE junior_message_threads SET last_message_at = NOW() WHERE id = ?');
        $stmt->execute([$threadId]);

        // 읽음 갱신 (본인)
        $stmt = $db->prepare('
            INSERT INTO junior_message_reads (thread_id, reader_type, reader_phone, last_read_at)
            VALUES (?, "parent", ?, NOW())
            ON DUPLICATE KEY UPDATE last_read_at = NOW()
        ');
        $stmt->execute([$threadId, $parentPhone]);

        jsonSuccess([
            'message' => [
                'id' => $msgId,
                'sender_type' => 'parent',
                'sender_name' => $admin['admin_name'],
                'body' => $body ?: '',
                'image_path' => $imagePath,
                'created_at' => date('Y-m-d H:i:s'),
            ],
            'thread_id' => $threadId,
        ], '메시지를 보냈습니다');
        break;

    // 스레드 읽음 처리
    case 'msg_mark_read':
        if ($method !== 'POST') jsonError('POST만 허용됩니다', 405);
        $admin = requireAdmin(['parent']);
        $input = getJsonInput();
        $threadId = (int)($input['thread_id'] ?? 0);
        if (!$threadId) jsonError('스레드 ID가 필요합니다');

        $db = getDB();
        verifyThreadAccessForParent($admin, $threadId);

        $stmt = $db->prepare('
            INSERT INTO junior_message_reads (thread_id, reader_type, reader_phone, last_read_at)
            VALUES (?, "parent", ?, NOW())
            ON DUPLICATE KEY UPDATE last_read_at = NOW()
        ');
        $stmt->execute([$threadId, $admin['parent_phone']]);
        jsonSuccess([], '읽음 처리 되었습니다');
        break;

    // 안 읽은 메시지 + 공지 총 수
    case 'msg_unread_total':
        $admin = requireAdmin(['parent']);
        $parentPhone = $admin['parent_phone'];
        $parentStudentIds = $admin['parent_student_ids'] ?? [];
        $db = getDB();

        // 메시지 안 읽은 수
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(sub.cnt), 0) as total_unread FROM (
                SELECT (SELECT COUNT(*) FROM junior_messages m
                        WHERE m.thread_id = t.id AND m.is_deleted = 0 AND m.sender_type = 'coach'
                        AND m.created_at > COALESCE(
                            (SELECT last_read_at FROM junior_message_reads
                             WHERE thread_id = t.id AND reader_type = 'parent' AND reader_phone = ?),
                            '1970-01-01'
                        )) as cnt
                FROM junior_message_threads t
                WHERE t.parent_phone = ? AND t.is_active = 1
            ) sub
        ");
        $stmt->execute([$parentPhone, $parentPhone]);
        $unreadMsg = (int)$stmt->fetchColumn();

        // 공지 안 읽은 수
        $unreadAnn = 0;
        if (!empty($parentStudentIds)) {
            $placeholders = implode(',', array_fill(0, count($parentStudentIds), '?'));
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM junior_announcements a
                JOIN junior_class_students cs ON a.class_id = cs.class_id AND cs.is_active = 1
                WHERE cs.student_id IN ($placeholders)
                AND a.is_active = 1
                AND NOT EXISTS (SELECT 1 FROM junior_announcement_reads ar WHERE ar.announcement_id = a.id AND ar.parent_phone = ?)
            ");
            $stmt->execute(array_merge($parentStudentIds, [$parentPhone]));
            $unreadAnn = (int)$stmt->fetchColumn();
        }

        jsonSuccess(['unread_messages' => $unreadMsg, 'unread_announcements' => $unreadAnn]);
        break;

    // ============================================
    // 공지사항 (학부모 측 — 읽기 전용)
    // ============================================

    // 메시지 이미지 서빙
    case 'msg_image':
        $admin = requireAdmin(['parent']);
        $path = trim($_GET['path'] ?? '');
        if (!$path) jsonError('경로가 필요합니다');
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
        $admin = requireAdmin(['parent']);
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

    // 공지 목록 (내 아이 반)
    case 'announcements':
        $admin = requireAdmin(['parent']);
        $parentPhone = $admin['parent_phone'];
        $studentId = (int)($_GET['student_id'] ?? 0);
        $parentStudentIds = $admin['parent_student_ids'] ?? [];

        if ($studentId && !in_array($studentId, $parentStudentIds)) jsonError('접근 권한이 없습니다', 403);
        $filterIds = $studentId ? [$studentId] : $parentStudentIds;
        if (empty($filterIds)) jsonSuccess(['announcements' => []]);

        $db = getDB();
        $placeholders = implode(',', array_fill(0, count($filterIds), '?'));
        $stmt = $db->prepare("
            SELECT DISTINCT a.*, c.display_name as class_name,
                   CASE WHEN ar.id IS NOT NULL THEN 1 ELSE 0 END as is_read
            FROM junior_announcements a
            JOIN junior_classes c ON a.class_id = c.id
            JOIN junior_class_students cs ON a.class_id = cs.class_id AND cs.is_active = 1
            LEFT JOIN junior_announcement_reads ar ON ar.announcement_id = a.id AND ar.parent_phone = ?
            WHERE cs.student_id IN ($placeholders) AND a.is_active = 1
            ORDER BY a.is_pinned DESC, a.created_at DESC
        ");
        $stmt->execute(array_merge([$parentPhone], $filterIds));
        jsonSuccess(['announcements' => $stmt->fetchAll()]);
        break;

    // 공지 상세 + 읽음 처리
    case 'announcement_detail':
        $admin = requireAdmin(['parent']);
        $parentPhone = $admin['parent_phone'];
        $annId = (int)($_GET['announcement_id'] ?? 0);
        if (!$annId) jsonError('공지 ID가 필요합니다');

        $db = getDB();
        // 권한: 내 아이 반의 공지인지
        $parentStudentIds = $admin['parent_student_ids'] ?? [];
        if (empty($parentStudentIds)) jsonError('접근 권한이 없습니다', 403);

        $placeholders = implode(',', array_fill(0, count($parentStudentIds), '?'));
        $stmt = $db->prepare("
            SELECT a.*, c.display_name as class_name
            FROM junior_announcements a
            JOIN junior_classes c ON a.class_id = c.id
            JOIN junior_class_students cs ON a.class_id = cs.class_id AND cs.is_active = 1
            WHERE a.id = ? AND cs.student_id IN ($placeholders) AND a.is_active = 1
            LIMIT 1
        ");
        $stmt->execute(array_merge([$annId], $parentStudentIds));
        $ann = $stmt->fetch();
        if (!$ann) jsonError('공지를 찾을 수 없습니다');

        // 읽음 기록
        $stmt = $db->prepare('
            INSERT IGNORE INTO junior_announcement_reads (announcement_id, parent_phone)
            VALUES (?, ?)
        ');
        $stmt->execute([$annId, $parentPhone]);

        jsonSuccess(['announcement' => $ann]);
        break;

    default:
        jsonError('알 수 없는 요청입니다', 404);
}

/**
 * 핑거프린트 저장/갱신 헬퍼
 */
/**
 * 학부모의 메시지 스레드 접근 권한 검증
 */
function verifyThreadAccessForParent(array $admin, int $threadId): array {
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM junior_message_threads WHERE id = ? AND parent_phone = ?');
    $stmt->execute([$threadId, $admin['parent_phone']]);
    $thread = $stmt->fetch();
    if (!$thread) jsonError('접근 권한이 없습니다', 403);

    // 추가: 학생이 내 아이인지
    $parentStudentIds = $admin['parent_student_ids'] ?? [];
    if (!in_array((int)$thread['student_id'], $parentStudentIds)) {
        jsonError('접근 권한이 없습니다', 403);
    }
    return $thread;
}

function saveFingerprint(PDO $db, int $adminId, string $fingerprint, $deviceInfo = null): void {
    $deviceInfoJson = $deviceInfo ? (is_string($deviceInfo) ? $deviceInfo : json_encode($deviceInfo, JSON_UNESCAPED_UNICODE)) : null;

    $stmt = $db->prepare('
        INSERT INTO junior_admin_fingerprints (admin_id, fingerprint, device_info, is_active, last_used_at)
        VALUES (?, ?, ?, 1, NOW())
        ON DUPLICATE KEY UPDATE is_active = 1, last_used_at = NOW(), device_info = VALUES(device_info)
    ');
    $stmt->execute([$adminId, $fingerprint, $deviceInfoJson]);
}
