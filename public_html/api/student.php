<?php
/**
 * 학생 API
 * QR 로그인, 자동 로그인, 마이페이지, 카드 등
 */
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json; charset=utf-8');

$action = getAction();
$method = getMethod();

switch ($action) {
    // QR 기반 로그인 (반 선택 + 학생 선택)
    case 'qr_login':
        if ($method !== 'POST') jsonError('POST만 허용됩니다', 405);
        $input = getJsonInput();
        $classId = (int)($input['class_id'] ?? 0);
        $studentId = (int)($input['student_id'] ?? 0);
        $fingerprint = trim($input['fingerprint'] ?? '');

        if (!$classId || !$studentId) jsonError('반과 이름을 골라줘!');

        $db = getDB();

        // 학생 확인
        $stmt = $db->prepare('
            SELECT s.id, s.name, c.id as class_id, c.display_name as class_name
            FROM junior_students s
            JOIN junior_class_students cs ON s.id = cs.student_id
            JOIN junior_classes c ON cs.class_id = c.id
            WHERE s.id = ? AND cs.class_id = ? AND s.is_active = 1 AND cs.is_active = 1
        ');
        $stmt->execute([$studentId, $classId]);
        $student = $stmt->fetch();

        if (!$student) jsonError('🔧 잠깐 문제가 생겼어. 다시 해보자!');

        // QR 출석용 임시 세션만 생성 (핑거프린트 저장 안 함)
        loginStudent($student['id'], $student['name'], $student['class_id'], $student['class_name']);

        jsonSuccess([
            'student' => [
                'id'         => $student['id'],
                'name'       => $student['name'],
                'class_id'   => $student['class_id'],
                'class_name' => $student['class_name'],
            ]
        ], '반가워!');
        break;

    // 디바이스 핑거프린트 자동 로그인
    case 'auto_login':
        if ($method !== 'POST') jsonError('POST만 허용됩니다', 405);
        $input = getJsonInput();
        $fingerprint = trim($input['fingerprint'] ?? '');

        if (!$fingerprint) jsonError('핑거프린트가 없습니다');

        $db = getDB();

        // 이 디바이스에 연결된 학생들 조회
        $stmt = $db->prepare('
            SELECT DISTINCT s.id, s.name, c.id as class_id, c.display_name as class_name, cs.is_primary
            FROM junior_shared_devices sd
            JOIN junior_students s ON sd.student_id = s.id
            LEFT JOIN junior_class_students cs ON s.id = cs.student_id AND cs.is_primary = 1 AND cs.is_active = 1
            LEFT JOIN junior_classes c ON cs.class_id = c.id
            WHERE sd.fingerprint = ? AND sd.is_active = 1 AND s.is_active = 1
            ORDER BY s.name
        ');
        $stmt->execute([$fingerprint]);
        $students = $stmt->fetchAll();

        if (empty($students)) {
            jsonSuccess(['found' => false, 'students' => []], '등록된 디바이스가 아닙니다');
        }

        // 핑거프린트 마지막 사용 시간 업데이트
        $stmt = $db->prepare('UPDATE junior_device_fingerprints SET last_used_at = NOW() WHERE fingerprint = ?');
        $stmt->execute([$fingerprint]);

        // 항상 학생 선택을 거치도록 (자동 로그인 비활성화)
        jsonSuccess([
            'found' => true,
            'auto_login' => false,
            'students' => array_map(fn($s) => [
                'id'         => $s['id'],
                'name'       => $s['name'],
                'class_id'   => $s['class_id'],
                'class_name' => $s['class_name'],
            ], $students)
        ]);
        break;

    // 형제 선택 (공유 기기)
    case 'choose_student':
        if ($method !== 'POST') jsonError('POST만 허용됩니다', 405);
        $input = getJsonInput();
        $studentId = (int)($input['student_id'] ?? 0);

        if (!$studentId) jsonError('누구인지 골라줘!');

        $db = getDB();
        $stmt = $db->prepare('
            SELECT s.id, s.name, c.id as class_id, c.display_name as class_name
            FROM junior_students s
            LEFT JOIN junior_class_students cs ON s.id = cs.student_id AND cs.is_primary = 1 AND cs.is_active = 1
            LEFT JOIN junior_classes c ON cs.class_id = c.id
            WHERE s.id = ? AND s.is_active = 1
        ');
        $stmt->execute([$studentId]);
        $student = $stmt->fetch();

        if (!$student) jsonError('🔧 잠깐 문제가 생겼어. 다시 해보자!');

        loginStudent($student['id'], $student['name'], $student['class_id'], $student['class_name']);

        jsonSuccess([
            'student' => [
                'id'         => $student['id'],
                'name'       => $student['name'],
                'class_id'   => $student['class_id'],
                'class_name' => $student['class_name'],
            ]
        ]);
        break;

    // 동명이인 해결
    case 'resolve_name':
        if ($method !== 'POST') jsonError('POST만 허용됩니다', 405);
        $input = getJsonInput();
        $classId = (int)($input['class_id'] ?? 0);
        $name = trim($input['name'] ?? '');
        $phoneLast4 = trim($input['phone_last4'] ?? '');

        if (!$classId || !$name || !$phoneLast4) jsonError('모두 입력해줘!');

        $db = getDB();
        $stmt = $db->prepare('
            SELECT s.id, s.name, c.id as class_id, c.display_name as class_name
            FROM junior_students s
            JOIN junior_class_students cs ON s.id = cs.student_id
            JOIN junior_classes c ON cs.class_id = c.id
            WHERE cs.class_id = ? AND s.name = ? AND s.phone_last4 = ?
            AND s.is_active = 1 AND cs.is_active = 1
            LIMIT 1
        ');
        $stmt->execute([$classId, $name, $phoneLast4]);
        $student = $stmt->fetch();

        if (!$student) jsonError('이름이나 번호를 다시 확인해봐!');

        jsonSuccess(['student' => [
            'id'         => $student['id'],
            'name'       => $student['name'],
            'class_id'   => $student['class_id'],
            'class_name' => $student['class_name'],
        ]]);
        break;

    // 마이페이지 전체 데이터
    case 'my_page':
        $session = requireStudent();
        $studentId = $session['student_id'];
        $db = getDB();

        // 학생 기본 정보 + 본반 정보
        $stmt = $db->prepare('
            SELECT s.id, s.name, s.grade,
                   c.id as class_id, c.display_name as class_name, c.coach_name,
                   cs.is_primary,
                   s.ace_current_level, s.bravo_current_level
            FROM junior_students s
            LEFT JOIN junior_class_students cs ON s.id = cs.student_id AND cs.is_primary = 1 AND cs.is_active = 1
            LEFT JOIN junior_classes c ON cs.class_id = c.id
            WHERE s.id = ?
        ');
        $stmt->execute([$studentId]);
        $info = $stmt->fetch();

        // 카드 보유 현황
        $rewards = getStudentRewards($studentId);
        $totalCoins = getStudentTotalCoins($studentId);

        // 반 내 랭킹 (Dense Ranking)
        $classRank = null;
        if ($info['class_id']) {
            $stmt = $db->prepare('
                SELECT s.id,
                       COALESCE(SUM(sr.quantity * rt.coin_value), 0) + COALESCE(s.coin_offset, 0) as total_coins
                FROM junior_students s
                JOIN junior_class_students cs ON s.id = cs.student_id AND cs.is_primary = 1 AND cs.is_active = 1
                LEFT JOIN junior_student_rewards sr ON s.id = sr.student_id
                LEFT JOIN junior_reward_types rt ON sr.reward_type_id = rt.id
                WHERE cs.class_id = ? AND s.is_active = 1
                GROUP BY s.id
                ORDER BY total_coins DESC
            ');
            $stmt->execute([$info['class_id']]);
            $classStudents = $stmt->fetchAll();

            $rank = 0;
            $prevCoins = -1;
            foreach ($classStudents as $cs) {
                if ($cs['total_coins'] != $prevCoins) {
                    $rank++;
                    $prevCoins = $cs['total_coins'];
                }
                if ($cs['id'] == $studentId) {
                    $classRank = $rank;
                    break;
                }
            }
        }

        // 전체 랭킹
        $stmt = $db->prepare('
            SELECT s.id,
                   COALESCE(SUM(sr.quantity * rt.coin_value), 0) + COALESCE(s.coin_offset, 0) as total_coins
            FROM junior_students s
            LEFT JOIN junior_student_rewards sr ON s.id = sr.student_id
            LEFT JOIN junior_reward_types rt ON sr.reward_type_id = rt.id
            WHERE s.is_active = 1
            GROUP BY s.id
            ORDER BY total_coins DESC
        ');
        $stmt->execute();
        $allStudents = $stmt->fetchAll();

        $overallRank = null;
        $rank = 0;
        $prevCoins = -1;
        foreach ($allStudents as $as) {
            if ($as['total_coins'] != $prevCoins) {
                $rank++;
                $prevCoins = $as['total_coins'];
            }
            if ($as['id'] == $studentId) {
                $overallRank = $rank;
                break;
            }
        }

        jsonSuccess([
            'student' => [
                'id'         => $info['id'],
                'name'       => $info['name'],
                'grade'      => $info['grade'],
                'class_id'   => $info['class_id'],
                'class_name' => $info['class_name'],
                'coach_name' => $info['coach_name'],
                'ace_current_level'   => $info['ace_current_level'] ? (int)$info['ace_current_level'] : null,
                'bravo_current_level' => $info['bravo_current_level'] ? (int)$info['bravo_current_level'] : null,
            ],
            'total_coins'  => $totalCoins,
            'class_rank'   => $classRank,
            'overall_rank' => $overallRank,
            'rewards'      => $rewards,
        ]);
        break;

    // 카드별 획득 히스토리 (체크리스트/제출 기반)
    case 'card_detail':
        $session = requireStudent();
        $code = trim($_GET['code'] ?? '');
        if (!$code) jsonError('카드 코드가 필요합니다');

        $db = getDB();
        $studentId = $session['student_id'];
        $history = [];

        // card_code → checklist_field 역매핑
        $cardToField = array_flip(CHECKLIST_CARD_MAP);

        if ($code === 'steady') {
            // 꾸준왕: 주간 sound_homework 충족 시 금요일(week_end) 날짜
            $stmt = $db->prepare('
                SELECT wc.week_end AS created_at, 1 AS change_amount
                FROM junior_weekly_calendar wc
                JOIN junior_daily_checklist dc
                    ON dc.student_id = ? AND dc.check_date BETWEEN wc.week_start AND wc.week_end
                    AND dc.sound_homework = 1
                GROUP BY wc.week_start, wc.week_end, wc.required_count
                HAVING COUNT(*) >= wc.required_count
                ORDER BY wc.week_end DESC
            ');
            $stmt->execute([$studentId]);
            $history = $stmt->fetchAll();

        } elseif ($code === 'ace') {
            // 도전왕: ACE/BRAVO 제출 완료 날짜
            $stmt = $db->prepare('
                SELECT DATE(submitted_at) AS created_at, 1 AS change_amount
                FROM junior_ace_submissions
                WHERE student_id = ? AND status IN (\'submitted\', \'evaluated\') AND submitted_at IS NOT NULL
                UNION ALL
                SELECT DATE(submitted_at) AS created_at, 1 AS change_amount
                FROM junior_bravo_submissions
                WHERE student_id = ? AND status IN (\'submitted\', \'confirmed\') AND submitted_at IS NOT NULL
                ORDER BY created_at DESC
            ');
            $stmt->execute([$studentId, $studentId]);
            $history = $stmt->fetchAll();

        } elseif (isset($cardToField[$code])) {
            // 체크리스트 기반 카드: 해당 필드 > 0인 날짜
            $field = $cardToField[$code];
            $stmt = $db->prepare("
                SELECT check_date AS created_at, `$field` AS change_amount
                FROM junior_daily_checklist
                WHERE student_id = ? AND `$field` > 0
                ORDER BY check_date DESC
            ");
            $stmt->execute([$studentId]);
            $history = $stmt->fetchAll();

        } else {
            // 알 수 없는 카드: 기존 reward_log 폴백
            $stmt = $db->prepare('
                SELECT rl.change_amount, rl.created_at
                FROM junior_reward_log rl
                JOIN junior_reward_types rt ON rl.reward_type_id = rt.id
                WHERE rl.student_id = ? AND rt.code = ?
                ORDER BY rl.created_at DESC
                LIMIT 50
            ');
            $stmt->execute([$studentId, $code]);
            $history = $stmt->fetchAll();
        }

        jsonSuccess(['history' => $history]);
        break;

    // 이름으로 학생 찾기 (보안 로그인)
    case 'find_by_name':
        if ($method !== 'POST') jsonError('POST만 허용됩니다', 405);
        $input = getJsonInput();
        $classId = (int)($input['class_id'] ?? 0);
        $name = trim($input['name'] ?? '');

        if (!$classId || !$name) jsonError('반과 이름을 알려줘!');

        $db = getDB();
        $stmt = $db->prepare('
            SELECT s.id, s.name, c.id as class_id, c.display_name as class_name
            FROM junior_students s
            JOIN junior_class_students cs ON s.id = cs.student_id AND cs.is_primary = 1 AND cs.is_active = 1
            JOIN junior_classes c ON cs.class_id = c.id
            WHERE cs.class_id = ? AND s.name = ? AND s.is_active = 1
        ');
        $stmt->execute([$classId, $name]);
        $students = $stmt->fetchAll();

        if (count($students) === 0) {
            jsonError('이름을 다시 확인해봐!');
        } elseif (count($students) === 1) {
            jsonSuccess(['unique' => true, 'student_id' => $students[0]['id']]);
        } else {
            jsonSuccess(['unique' => false, 'count' => count($students)]);
        }
        break;

    // 반 목록
    case 'classes':
        $db = getDB();
        $stmt = $db->prepare('
            SELECT id, name, display_name, coach_name, logo_image, color
            FROM junior_classes
            WHERE is_active = 1
            ORDER BY sort_order
        ');
        $stmt->execute();
        jsonSuccess(['classes' => $stmt->fetchAll()]);
        break;

    // 반별 학생 목록
    case 'students':
        $classId = (int)($_GET['class_id'] ?? 0);
        if (!$classId) jsonError('반을 골라줘!');

        $db = getDB();
        $stmt = $db->prepare('
            SELECT s.id, s.name, cs.is_primary
            FROM junior_students s
            JOIN junior_class_students cs ON s.id = cs.student_id
            WHERE cs.class_id = ? AND s.is_active = 1 AND cs.is_active = 1
            ORDER BY s.name
        ');
        $stmt->execute([$classId]);
        $students = $stmt->fetchAll();

        // 동명이인 체크
        $nameCounts = [];
        foreach ($students as $s) {
            $nameCounts[$s['name']] = ($nameCounts[$s['name']] ?? 0) + 1;
        }

        $result = array_map(function($s) use ($nameCounts) {
            return [
                'id'            => $s['id'],
                'name'          => $s['name'],
                'is_primary'    => $s['is_primary'],
                'has_duplicate' => ($nameCounts[$s['name']] > 1),
            ];
        }, $students);

        jsonSuccess(['students' => $result]);
        break;

    // 세션 확인
    case 'check_session':
        $session = getStudentSession();
        if ($session) {
            jsonSuccess(['logged_in' => true, 'student' => $session]);
        } else {
            jsonSuccess(['logged_in' => false]);
        }
        break;

    // 형제 조회 (phone_last4 + 성씨 기반)
    case 'get_siblings':
        $session = getStudentSession();
        if (!$session) jsonError('먼저 로그인해줘!', 401);
        $studentId = $session['student_id'];

        $db = getDB();
        $stmt = $db->prepare('SELECT phone_last4, SUBSTRING(name, 1, 1) as surname FROM junior_students WHERE id = ? AND is_active = 1');
        $stmt->execute([$studentId]);
        $me = $stmt->fetch();

        if (!$me || !$me['phone_last4']) {
            jsonSuccess(['siblings' => [], 'current_student_id' => $studentId]);
            break;
        }

        $stmt = $db->prepare('
            SELECT s.id, s.name, c.id as class_id, c.display_name as class_name
            FROM junior_students s
            LEFT JOIN junior_class_students cs ON s.id = cs.student_id AND cs.is_primary = 1 AND cs.is_active = 1
            LEFT JOIN junior_classes c ON cs.class_id = c.id
            WHERE s.phone_last4 = ? AND SUBSTRING(s.name, 1, 1) = ? AND s.is_active = 1
            ORDER BY s.name
        ');
        $stmt->execute([$me['phone_last4'], $me['surname']]);
        $siblings = $stmt->fetchAll();

        if (count($siblings) <= 1) {
            jsonSuccess(['siblings' => [], 'current_student_id' => $studentId]);
            break;
        }

        jsonSuccess([
            'siblings' => array_map(fn($s) => [
                'id'         => (int)$s['id'],
                'name'       => $s['name'],
                'class_id'   => $s['class_id'],
                'class_name' => $s['class_name'],
            ], $siblings),
            'current_student_id' => $studentId,
        ]);
        break;

    // 로그아웃 (핑거프린트 비활성화 포함)
    case 'logout':
        if ($method === 'POST') {
            $input = getJsonInput();
            $fingerprint = trim($input['fingerprint'] ?? '');

            if ($fingerprint) {
                $db = getDB();
                // 이 디바이스의 핑거프린트 비활성화 (자동 로그인 방지)
                $stmt = $db->prepare('
                    UPDATE junior_shared_devices
                    SET is_active = 0
                    WHERE fingerprint = ?
                ');
                $stmt->execute([$fingerprint]);

                $stmt = $db->prepare('
                    DELETE FROM junior_device_fingerprints
                    WHERE fingerprint = ?
                ');
                $stmt->execute([$fingerprint]);
            }
        }

        logoutStudent();
        jsonSuccess([], '다음에 또 만나!');
        break;

    // 보안 로그인 (이름 + 전화번호 뒷자리 → 세션 생성 + 핑거프린트 저장)
    case 'secure_login':
        if ($method !== 'POST') jsonError('POST만 허용됩니다', 405);
        $input = getJsonInput();
        $classId = (int)($input['class_id'] ?? 0);
        $name = trim($input['name'] ?? '');
        $phoneLast4 = trim($input['phone_last4'] ?? '');
        $fingerprint = trim($input['fingerprint'] ?? '');

        if (!$classId || !$name || !$phoneLast4) jsonError('모두 입력해줘!');
        if (strlen($phoneLast4) !== 4 || !ctype_digit($phoneLast4)) jsonError('전화번호 뒷 4자리를 다시 확인해봐!');

        $db = getDB();
        $stmt = $db->prepare('
            SELECT s.id, s.name, s.phone_last4, c.id as class_id, c.display_name as class_name
            FROM junior_students s
            JOIN junior_class_students cs ON s.id = cs.student_id AND cs.is_active = 1
            JOIN junior_classes c ON cs.class_id = c.id
            WHERE cs.class_id = ? AND s.name = ? AND s.phone_last4 = ?
            AND s.is_active = 1
            LIMIT 1
        ');
        $stmt->execute([$classId, $name, $phoneLast4]);
        $student = $stmt->fetch();

        if (!$student) jsonError('이름이나 번호를 다시 확인해봐!');

        // 핑거프린트 저장 (다음 접속부터 학생 선택 목록에 표시)
        if ($fingerprint) {
            $stmt = $db->prepare('
                INSERT INTO junior_device_fingerprints (student_id, fingerprint, device_info, last_used_at)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE student_id = VALUES(student_id), last_used_at = NOW()
            ');
            $stmt->execute([$student['id'], $fingerprint, '{}']);

            $stmt = $db->prepare('INSERT IGNORE INTO junior_shared_devices (fingerprint, student_id) VALUES (?, ?)');
            $stmt->execute([$fingerprint, $student['id']]);
        }

        loginStudent($student['id'], $student['name'], $student['class_id'], $student['class_name']);

        jsonSuccess([
            'student' => [
                'id'         => $student['id'],
                'name'       => $student['name'],
                'class_id'   => $student['class_id'],
                'class_name' => $student['class_name'],
            ]
        ], '반가워!');
        break;

    // 반별 학생 목록 (QR 출석용)
    case 'class_students':
        $classId = (int)($_GET['class_id'] ?? 0);
        if (!$classId) jsonError('반을 선택해 주세요');

        $db = getDB();
        $stmt = $db->prepare('
            SELECT s.id, s.name
            FROM junior_students s
            JOIN junior_class_students cs ON s.id = cs.student_id AND cs.class_id = ? AND cs.is_active = 1
            WHERE s.is_active = 1
            ORDER BY s.name
        ');
        $stmt->execute([$classId]);
        jsonSuccess(['students' => $stmt->fetchAll()]);
        break;

    default:
        jsonError('앗, 뭔가 이상해! 다시 해보자.', 404);
}
