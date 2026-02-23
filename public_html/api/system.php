<?php
/**
 * 시스템관리자 API
 * 회원 CRUD, 반 관리, 설정, 감사 로그, 학생 일괄 등록
 */
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json; charset=utf-8');

$action = getAction();
$method = getMethod();

switch ($action) {
    // 로그인
    case 'login':
        if ($method !== 'POST') jsonError('POST만 허용됩니다', 405);
        $input = getJsonInput();
        $loginId = trim($input['login_id'] ?? '');
        $password = trim($input['password'] ?? '');

        if (!$loginId || !$password) jsonError('아이디와 비밀번호를 입력해 주세요');

        $db = getDB();
        $stmt = $db->prepare('SELECT * FROM junior_system_admins WHERE login_id = ? AND is_active = 1');
        $stmt->execute([$loginId]);
        $admin = $stmt->fetch();

        if (!$admin || !password_verify($password, $admin['password_hash'])) {
            jsonError('아이디 또는 비밀번호가 올바르지 않습니다');
        }

        loginSystem($admin['id'], $admin['name'], $admin['role']);

        jsonSuccess([
            'admin' => [
                'id'   => $admin['id'],
                'name' => $admin['name'],
                'role' => $admin['role'],
            ]
        ], '로그인 되었습니다');
        break;

    // 로그아웃
    case 'logout':
        logoutSystem();
        jsonSuccess([], '로그아웃 되었습니다');
        break;

    // 세션 확인
    case 'check_session':
        $session = getSystemSession();
        if ($session) {
            jsonSuccess(['logged_in' => true, 'admin' => $session]);
        } else {
            jsonSuccess(['logged_in' => false]);
        }
        break;

    // IP 자동 로그인 가능 여부 확인
    case 'check_ip_auto_login':
        $clientIp = getClientIP();
        $allowed = isWhitelistedIP($clientIp);
        jsonSuccess(['allowed' => $allowed, 'ip' => $clientIp]);
        break;

    // IP 자동 로그인 실행
    case 'ip_auto_login':
        if ($method !== 'POST') jsonError('POST만 허용됩니다', 405);
        $clientIp = getClientIP();
        if (!isWhitelistedIP($clientIp)) {
            jsonError('허용되지 않은 IP입니다');
        }

        $db = getDB();
        $stmt = $db->prepare('SELECT * FROM junior_system_admins WHERE role = ? AND is_active = 1 ORDER BY id LIMIT 1');
        $stmt->execute(['super_admin']);
        $admin = $stmt->fetch();

        if (!$admin) jsonError('관리자 계정이 없습니다');

        loginSystem($admin['id'], $admin['name'], $admin['role']);

        jsonSuccess([
            'admin' => [
                'id'   => $admin['id'],
                'name' => $admin['name'],
                'role' => $admin['role'],
            ]
        ], '자동 로그인 되었습니다');
        break;

    // ============================================
    // 회원 관리
    // ============================================

    // 학생 목록
    case 'students':
        $admin = requireSystem();
        $db = getDB();

        if ($method === 'GET') {
            $search = trim($_GET['search'] ?? '');
            $classId = (int)($_GET['class_id'] ?? 0);
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = 50;
            $offset = ($page - 1) * $limit;

            $where = ['s.is_active = 1'];
            $params = [];

            if ($search) {
                $where[] = 's.name LIKE ?';
                $params[] = "%{$search}%";
            }
            if ($classId) {
                $where[] = 'cs.class_id = ?';
                $params[] = $classId;
            }

            $whereStr = implode(' AND ', $where);

            $stmt = $db->prepare("
                SELECT s.id, s.name, s.phone, s.phone_last4, s.grade, s.soritune_id,
                       cs.class_id, c.display_name as class_name, s.created_at
                FROM junior_students s
                LEFT JOIN junior_class_students cs ON s.id = cs.student_id AND cs.is_primary = 1 AND cs.is_active = 1
                LEFT JOIN junior_classes c ON cs.class_id = c.id
                WHERE {$whereStr}
                ORDER BY s.name
                LIMIT {$limit} OFFSET {$offset}
            ");
            $stmt->execute($params);
            $students = $stmt->fetchAll();

            // Total count
            $stmt = $db->prepare("
                SELECT COUNT(DISTINCT s.id)
                FROM junior_students s
                LEFT JOIN junior_class_students cs ON s.id = cs.student_id AND cs.is_primary = 1 AND cs.is_active = 1
                WHERE {$whereStr}
            ");
            $stmt->execute($params);
            $total = (int)$stmt->fetchColumn();

            jsonSuccess(['students' => $students, 'total' => $total, 'page' => $page]);
        }

        if ($method === 'POST') {
            $input = getJsonInput();
            $subAction = $input['sub_action'] ?? 'create';

            if ($subAction === 'create') {
                $name = trim($input['name'] ?? '');
                $phone = trim($input['phone'] ?? '');
                $grade = trim($input['grade'] ?? '');
                $classId = (int)($input['class_id'] ?? 0);

                if (!$name) jsonError('학생 이름을 입력해 주세요');

                $phoneLast4 = $phone ? substr(preg_replace('/\D/', '', $phone), -4) : null;

                $stmt = $db->prepare('INSERT INTO junior_students (name, phone, phone_last4, grade) VALUES (?, ?, ?, ?)');
                $stmt->execute([$name, $phone ?: null, $phoneLast4, $grade ?: null]);
                $studentId = $db->lastInsertId();

                if ($classId) {
                    $stmt = $db->prepare('INSERT INTO junior_class_students (class_id, student_id, is_primary) VALUES (?, ?, 1)');
                    $stmt->execute([$classId, $studentId]);
                }

                // 보상 초기화
                $stmt = $db->prepare('SELECT id FROM junior_reward_types WHERE is_active = 1');
                $stmt->execute();
                $types = $stmt->fetchAll();
                foreach ($types as $t) {
                    $db->prepare('INSERT IGNORE INTO junior_student_rewards (student_id, reward_type_id, quantity) VALUES (?, ?, 0)')
                        ->execute([$studentId, $t['id']]);
                }

                auditLog('junior_students', $studentId, 'create', null, null, $name, '학생 생성',
                    $admin['system_id'], 'system_admin', $admin['system_name']);

                jsonSuccess(['id' => $studentId], '학생이 등록되었습니다');
            }

            if ($subAction === 'update') {
                $id = (int)($input['id'] ?? 0);
                $name = trim($input['name'] ?? '');
                $phone = trim($input['phone'] ?? '');
                $grade = trim($input['grade'] ?? '');

                if (!$id || !$name) jsonError('필수 항목을 입력해 주세요');

                $phoneLast4 = $phone ? substr(preg_replace('/\D/', '', $phone), -4) : null;

                $stmt = $db->prepare('UPDATE junior_students SET name = ?, phone = ?, phone_last4 = ?, grade = ? WHERE id = ?');
                $stmt->execute([$name, $phone ?: null, $phoneLast4, $grade ?: null, $id]);

                auditLog('junior_students', $id, 'update', 'name', null, $name, '학생 정보 수정',
                    $admin['system_id'], 'system_admin', $admin['system_name']);

                jsonSuccess([], '수정되었습니다');
            }

            if ($subAction === 'delete') {
                $id = (int)($input['id'] ?? 0);
                if (!$id) jsonError('ID가 필요합니다');

                $stmt = $db->prepare('UPDATE junior_students SET is_active = 0 WHERE id = ?');
                $stmt->execute([$id]);

                auditLog('junior_students', $id, 'delete', null, null, null, '학생 비활성화',
                    $admin['system_id'], 'system_admin', $admin['system_name']);

                jsonSuccess([], '삭제되었습니다');
            }
        }
        break;

    // 관리자 목록
    case 'admins':
        $admin = requireSystem();
        $db = getDB();

        if ($method === 'GET') {
            $role = trim($_GET['role'] ?? '');
            $where = 'is_active = 1';
            $params = [];
            if ($role) {
                $where .= ' AND role = ?';
                $params[] = $role;
            }

            $stmt = $db->prepare("SELECT id, login_id, name, role, phone, email, last_login_at, created_at FROM junior_admins WHERE {$where} ORDER BY name");
            $stmt->execute($params);
            jsonSuccess(['admins' => $stmt->fetchAll()]);
        }

        if ($method === 'POST') {
            $input = getJsonInput();
            $subAction = $input['sub_action'] ?? 'create';

            if ($subAction === 'create') {
                $loginId = trim($input['login_id'] ?? '');
                $password = trim($input['password'] ?? '');
                $name = trim($input['name'] ?? '');
                $role = $input['role'] ?? '';

                if (!$loginId || !$password || !$name || !in_array($role, ['coach', 'admin_teacher', 'parent'])) {
                    jsonError('필수 항목을 모두 입력해 주세요');
                }

                $hash = password_hash($password, PASSWORD_ARGON2ID);
                $stmt = $db->prepare('INSERT INTO junior_admins (login_id, password_hash, name, role, phone, email) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([$loginId, $hash, $name, $role, $input['phone'] ?? null, $input['email'] ?? null]);

                $newId = $db->lastInsertId();

                // 반 연결
                if (!empty($input['class_ids'])) {
                    foreach ($input['class_ids'] as $cid) {
                        $db->prepare('INSERT IGNORE INTO junior_admin_classes (admin_id, class_id) VALUES (?, ?)')
                            ->execute([$newId, (int)$cid]);
                    }
                }

                jsonSuccess(['id' => $newId], '관리자가 등록되었습니다');
            }

            if ($subAction === 'update') {
                $id = (int)($input['id'] ?? 0);
                if (!$id) jsonError('ID가 필요합니다');

                $fields = [];
                $params = [];

                if (!empty($input['name'])) { $fields[] = 'name = ?'; $params[] = $input['name']; }
                if (!empty($input['phone'])) { $fields[] = 'phone = ?'; $params[] = $input['phone']; }
                if (!empty($input['email'])) { $fields[] = 'email = ?'; $params[] = $input['email']; }
                if (!empty($input['password'])) {
                    $fields[] = 'password_hash = ?';
                    $params[] = password_hash($input['password'], PASSWORD_ARGON2ID);
                }

                if (!empty($fields)) {
                    $params[] = $id;
                    $stmt = $db->prepare('UPDATE junior_admins SET ' . implode(', ', $fields) . ' WHERE id = ?');
                    $stmt->execute($params);
                }

                jsonSuccess([], '수정되었습니다');
            }
        }
        break;

    // 시스템관리자 목록
    case 'system_admins':
        $admin = requireSystem(['super_admin']);
        $db = getDB();

        if ($method === 'GET') {
            $stmt = $db->prepare('SELECT id, login_id, name, role, last_login_at, created_at FROM junior_system_admins WHERE is_active = 1 ORDER BY id');
            $stmt->execute();
            jsonSuccess(['admins' => $stmt->fetchAll()]);
        }

        if ($method === 'POST') {
            $input = getJsonInput();
            $loginId = trim($input['login_id'] ?? '');
            $password = trim($input['password'] ?? '');
            $name = trim($input['name'] ?? '');
            $role = $input['role'] ?? 'system_admin';

            if (!$loginId || !$password || !$name) jsonError('필수 항목을 모두 입력해 주세요');

            $hash = password_hash($password, PASSWORD_ARGON2ID);
            $stmt = $db->prepare('INSERT INTO junior_system_admins (login_id, password_hash, name, role) VALUES (?, ?, ?, ?)');
            $stmt->execute([$loginId, $hash, $name, $role]);

            jsonSuccess(['id' => $db->lastInsertId()], '시스템관리자가 등록되었습니다');
        }
        break;

    // ============================================
    // 반 관리
    // ============================================
    case 'classes':
        $admin = requireSystem();
        $db = getDB();

        if ($method === 'GET') {
            $stmt = $db->prepare('
                SELECT c.*, COUNT(cs.id) as student_count
                FROM junior_classes c
                LEFT JOIN junior_class_students cs ON c.id = cs.class_id AND cs.is_active = 1
                WHERE c.is_active = 1
                GROUP BY c.id
                ORDER BY c.sort_order
            ');
            $stmt->execute();
            jsonSuccess(['classes' => $stmt->fetchAll()]);
        }

        if ($method === 'POST') {
            $input = getJsonInput();
            $subAction = $input['sub_action'] ?? 'update';
            $id = (int)($input['id'] ?? 0);

            if (!$id) jsonError('반 ID가 필요합니다');

            $stmt = $db->prepare('UPDATE junior_classes SET coach_name = ?, coach_id = ?, color = ? WHERE id = ?');
            $stmt->execute([
                $input['coach_name'] ?? null,
                $input['coach_id'] ? (int)$input['coach_id'] : null,
                $input['color'] ?? '#FF7E17',
                $id
            ]);

            jsonSuccess([], '반 정보가 수정되었습니다');
        }
        break;

    // 반 상세 (학생 목록 + 연결된 관리자)
    case 'class_detail':
        $admin = requireSystem();
        $db = getDB();
        $classId = (int)($_GET['class_id'] ?? 0);
        if (!$classId) jsonError('반 ID가 필요합니다');

        // 반 기본 정보
        $stmt = $db->prepare('SELECT * FROM junior_classes WHERE id = ? AND is_active = 1');
        $stmt->execute([$classId]);
        $classInfo = $stmt->fetch();
        if (!$classInfo) jsonError('반을 찾을 수 없습니다');

        // 반 학생 목록 (보상 코인 합계 포함)
        $stmt = $db->prepare('
            SELECT s.id, s.name, s.phone, s.phone_last4, s.grade, s.soritune_id,
                   cs.is_primary,
                   COALESCE(SUM(sr.quantity * rt.coin_value), 0) as total_coins
            FROM junior_students s
            JOIN junior_class_students cs ON s.id = cs.student_id AND cs.class_id = ? AND cs.is_active = 1
            LEFT JOIN junior_student_rewards sr ON s.id = sr.student_id
            LEFT JOIN junior_reward_types rt ON sr.reward_type_id = rt.id AND rt.is_active = 1
            WHERE s.is_active = 1
            GROUP BY s.id, cs.is_primary
            ORDER BY s.name
        ');
        $stmt->execute([$classId]);
        $students = $stmt->fetchAll();

        // 연결된 관리자 (코치쌤/관리쌤)
        $stmt = $db->prepare('
            SELECT a.id, a.name, a.role, a.login_id, a.phone
            FROM junior_admins a
            JOIN junior_admin_classes ac ON a.id = ac.admin_id AND ac.class_id = ? AND ac.is_active = 1
            WHERE a.is_active = 1
            ORDER BY a.role, a.name
        ');
        $stmt->execute([$classId]);
        $admins = $stmt->fetchAll();

        jsonSuccess([
            'class' => $classInfo,
            'students' => $students,
            'admins' => $admins,
            'student_count' => count($students),
            'admin_count' => count($admins),
        ]);
        break;

    // 학생 상세 (카드/보상 현황)
    case 'student_detail':
        $admin = requireSystem();
        $db = getDB();
        $studentId = (int)($_GET['student_id'] ?? 0);
        if (!$studentId) jsonError('학생 ID가 필요합니다');

        // 학생 기본 정보
        $stmt = $db->prepare('
            SELECT s.*, c.display_name as class_name, cs.class_id
            FROM junior_students s
            LEFT JOIN junior_class_students cs ON s.id = cs.student_id AND cs.is_primary = 1 AND cs.is_active = 1
            LEFT JOIN junior_classes c ON cs.class_id = c.id
            WHERE s.id = ? AND s.is_active = 1
        ');
        $stmt->execute([$studentId]);
        $student = $stmt->fetch();
        if (!$student) jsonError('학생을 찾을 수 없습니다');

        // 카드별 보상 현황
        $stmt = $db->prepare('
            SELECT rt.code, rt.name_ko, rt.coin_value, rt.color, rt.image_file,
                   COALESCE(sr.quantity, 0) as quantity
            FROM junior_reward_types rt
            LEFT JOIN junior_student_rewards sr ON rt.id = sr.reward_type_id AND sr.student_id = ?
            WHERE rt.is_active = 1
            ORDER BY rt.sort_order
        ');
        $stmt->execute([$studentId]);
        $rewards = $stmt->fetchAll();

        // 총 코인 계산
        $totalCoins = 0;
        foreach ($rewards as $r) {
            $totalCoins += (int)$r['quantity'] * (int)$r['coin_value'];
        }

        // 주간 요약 (최근 4주)
        $stmt = $db->prepare('
            SELECT week_start, week_end, required_days, completed_days, is_steady_king,
                   zoom_total, posture_total, homework_count, mission_count, leader_count
            FROM junior_weekly_summary
            WHERE student_id = ?
            ORDER BY week_start DESC LIMIT 4
        ');
        $stmt->execute([$studentId]);
        $weeklySummary = $stmt->fetchAll();

        jsonSuccess([
            'student' => [
                'id' => $student['id'],
                'name' => $student['name'],
                'phone' => $student['phone'],
                'phone_last4' => $student['phone_last4'],
                'grade' => $student['grade'],
                'soritune_id' => $student['soritune_id'],
                'class_name' => $student['class_name'],
                'class_id' => $student['class_id'],
            ],
            'rewards' => $rewards,
            'total_coins' => $totalCoins,
            'weekly_summary' => $weeklySummary,
        ]);
        break;

    // ============================================
    // 설정
    // ============================================
    case 'settings':
        $admin = requireSystem();
        $db = getDB();

        if ($method === 'GET') {
            $stmt = $db->prepare('
                SELECT s.*, sa.name as updated_by_name
                FROM junior_settings s
                LEFT JOIN junior_system_admins sa ON s.updated_by = sa.id
                ORDER BY s.id
            ');
            $stmt->execute();
            jsonSuccess(['settings' => $stmt->fetchAll()]);
        }

        if ($method === 'POST') {
            $input = getJsonInput();
            $items = $input['items'] ?? [];

            foreach ($items as $item) {
                $key = trim($item['key'] ?? '');
                $value = $item['value'] ?? '';
                if (!$key) continue;

                $stmt = $db->prepare('UPDATE junior_settings SET setting_value = ?, updated_by = ? WHERE setting_key = ?');
                $stmt->execute([$value, $admin['system_id'], $key]);
            }

            jsonSuccess([], '설정이 저장되었습니다');
        }
        break;

    // QR 배경 이미지 업로드
    case 'upload_bg_image':
        $admin = requireSystem();
        if ($method !== 'POST') jsonError('POST만 허용됩니다', 405);

        if (empty($_FILES['bg_image']) || $_FILES['bg_image']['error'] !== UPLOAD_ERR_OK) {
            jsonError('이미지 파일을 선택해 주세요');
        }

        $file = $_FILES['bg_image'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $maxSize) jsonError('파일 크기는 5MB 이하만 가능합니다');

        $allowed = ['image/png', 'image/jpeg', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mimeType, $allowed)) jsonError('PNG, JPG, WebP 이미지만 허용됩니다');

        $ext = match($mimeType) {
            'image/png'  => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            default      => 'png',
        };
        $filename = 'qr_background.' . $ext;
        $uploadDir = dirname(__DIR__) . '/images/og/';
        $uploadPath = $uploadDir . $filename;

        // 기존 파일 삭제
        foreach (glob($uploadDir . 'qr_background.*') as $old) {
            @unlink($old);
        }

        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            jsonError('파일 업로드에 실패했습니다');
        }

        // 설정 업데이트
        $db = getDB();
        $settingValue = 'og/' . $filename;
        $stmt = $db->prepare("UPDATE junior_settings SET setting_value = ?, updated_by = ? WHERE setting_key = 'qr_bg_image'");
        $stmt->execute([$settingValue, $admin['system_id']]);

        auditLog('junior_settings', 3, 'update', 'qr_bg_image', null, $settingValue, 'QR 배경 이미지 업로드', $admin['system_id'], 'system', $admin['system_name']);

        jsonSuccess([
            'path' => $settingValue,
            'url'  => '/images/' . $settingValue,
        ], 'QR 배경 이미지가 업로드되었습니다');
        break;

    // 임시 IP 접속 토큰 생성
    case 'generate_temp_access':
        $admin = requireSystem();
        if ($method !== 'POST') jsonError('POST만 허용됩니다', 405);

        $input = getJsonInput();
        $hours = max(1, min(24, (int)($input['hours'] ?? 1)));

        $token = bin2hex(random_bytes(16)); // 32자 hex
        $db = getDB();

        $stmt = $db->prepare('
            INSERT INTO junior_temp_ip_access (token, created_by, created_by_name, expires_at)
            VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR))
        ');
        $stmt->execute([$token, $admin['system_id'], $admin['system_name'], $hours]);

        $siteUrl = getSetting('site_url', 'https://j.soritune.com');
        $accessUrl = $siteUrl . '/system/temp-access.php?token=' . $token;

        jsonSuccess([
            'token'      => $token,
            'url'        => $accessUrl,
            'hours'      => $hours,
            'expires_at' => date('Y-m-d H:i:s', time() + $hours * 3600),
        ], '임시 접속 링크가 생성되었습니다');
        break;

    // 임시 IP 접속 토큰 사용 (인증 불필요)
    case 'use_temp_access':
        if ($method !== 'POST') jsonError('POST만 허용됩니다', 405);
        $input = getJsonInput();
        $token = trim($input['token'] ?? '');
        if (!$token) jsonError('토큰이 필요합니다');

        $db = getDB();
        $stmt = $db->prepare('
            SELECT * FROM junior_temp_ip_access
            WHERE token = ? AND is_active = 1 AND expires_at > NOW()
        ');
        $stmt->execute([$token]);
        $access = $stmt->fetch();

        if (!$access) jsonError('유효하지 않거나 만료된 링크입니다');
        if ($access['ip_address']) jsonError('이미 사용된 링크입니다');

        $clientIp = getClientIP();

        // IP 등록
        $stmt = $db->prepare('UPDATE junior_temp_ip_access SET ip_address = ?, used_at = NOW() WHERE id = ?');
        $stmt->execute([$clientIp, $access['id']]);

        jsonSuccess([
            'ip'         => $clientIp,
            'expires_at' => $access['expires_at'],
            'created_by' => $access['created_by_name'],
        ], 'IP가 임시 등록되었습니다. ' . $access['expires_at'] . '까지 유효합니다.');
        break;

    // 임시 IP 접속 토큰 목록
    case 'temp_access_list':
        $admin = requireSystem();
        $db = getDB();
        $stmt = $db->prepare('
            SELECT * FROM junior_temp_ip_access
            ORDER BY created_at DESC LIMIT 20
        ');
        $stmt->execute();
        jsonSuccess(['tokens' => $stmt->fetchAll()]);
        break;

    // ============================================
    // 감사 로그
    // ============================================
    case 'audit_log':
        $admin = requireSystem();
        $db = getDB();

        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;
        $table = trim($_GET['table'] ?? '');
        $dateFrom = trim($_GET['date_from'] ?? '');
        $dateTo = trim($_GET['date_to'] ?? '');

        $where = ['1=1'];
        $params = [];

        if ($table) {
            $where[] = 'table_name = ?';
            $params[] = $table;
        }
        if ($dateFrom) {
            $where[] = 'created_at >= ?';
            $params[] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo) {
            $where[] = 'created_at <= ?';
            $params[] = $dateTo . ' 23:59:59';
        }

        $whereStr = implode(' AND ', $where);

        $stmt = $db->prepare("
            SELECT * FROM junior_edit_audit_log
            WHERE {$whereStr}
            ORDER BY created_at DESC
            LIMIT {$limit} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $logs = $stmt->fetchAll();

        $stmt = $db->prepare("SELECT COUNT(*) FROM junior_edit_audit_log WHERE {$whereStr}");
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        jsonSuccess(['logs' => $logs, 'total' => $total, 'page' => $page]);
        break;

    // ============================================
    // 학생 일괄 등록
    // ============================================
    case 'import_students':
        if ($method !== 'POST') jsonError('POST만 허용됩니다', 405);
        $admin = requireSystem();
        $input = getJsonInput();
        $items = $input['students'] ?? [];
        $classId = (int)($input['class_id'] ?? 0);

        if (empty($items)) jsonError('학생 데이터가 없습니다');

        $db = getDB();
        $db->beginTransaction();
        $imported = 0;

        try {
            foreach ($items as $item) {
                $name = trim($item['name'] ?? '');
                $phone = trim($item['phone'] ?? '');
                $grade = trim($item['grade'] ?? '');

                if (!$name) continue;

                $phoneLast4 = $phone ? substr(preg_replace('/\D/', '', $phone), -4) : null;

                $stmt = $db->prepare('INSERT INTO junior_students (name, phone, phone_last4, grade) VALUES (?, ?, ?, ?)');
                $stmt->execute([$name, $phone ?: null, $phoneLast4, $grade ?: null]);
                $studentId = $db->lastInsertId();

                if ($classId) {
                    $db->prepare('INSERT INTO junior_class_students (class_id, student_id, is_primary) VALUES (?, ?, 1)')
                        ->execute([$classId, $studentId]);
                }

                // 보상 초기화
                $stmt = $db->prepare('SELECT id FROM junior_reward_types WHERE is_active = 1');
                $stmt->execute();
                foreach ($stmt->fetchAll() as $t) {
                    $db->prepare('INSERT IGNORE INTO junior_student_rewards (student_id, reward_type_id, quantity) VALUES (?, ?, 0)')
                        ->execute([$studentId, $t['id']]);
                }

                $imported++;
            }

            $db->commit();
            jsonSuccess(['imported' => $imported], "{$imported}명의 학생이 등록되었습니다");
        } catch (Exception $e) {
            $db->rollBack();
            jsonError('일괄 등록 중 오류: ' . $e->getMessage(), 500);
        }
        break;

    // 일괄 반 배정
    case 'bulk_assign_class':
        if ($method !== 'POST') jsonError('POST만 허용됩니다', 405);
        $admin = requireSystem();
        $input = getJsonInput();
        $studentIds = $input['student_ids'] ?? [];
        $classId = (int)($input['class_id'] ?? 0);
        $isPrimary = (int)($input['is_primary'] ?? 1);

        if (empty($studentIds) || !$classId) jsonError('학생 목록과 반 ID가 필요합니다');

        $db = getDB();
        $count = 0;

        foreach ($studentIds as $sid) {
            $sid = (int)$sid;
            if (!$sid) continue;

            // 본반 배정 시 기존 본반 해제
            if ($isPrimary) {
                $stmt = $db->prepare('UPDATE junior_class_students SET is_primary = 0 WHERE student_id = ? AND is_primary = 1');
                $stmt->execute([$sid]);
            }

            $stmt = $db->prepare('
                INSERT INTO junior_class_students (class_id, student_id, is_primary, joined_at)
                VALUES (?, ?, ?, CURDATE())
                ON DUPLICATE KEY UPDATE is_primary = VALUES(is_primary), is_active = 1
            ');
            $stmt->execute([$classId, $sid, $isPrimary]);
            $count++;
        }

        jsonSuccess(['count' => $count], "{$count}명의 학생이 반에 배정되었습니다");
        break;

    // 일괄 학생 삭제 (비활성화)
    case 'bulk_delete_students':
        if ($method !== 'POST') jsonError('POST만 허용됩니다', 405);
        $admin = requireSystem();
        $input = getJsonInput();
        $studentIds = $input['student_ids'] ?? [];

        if (empty($studentIds)) jsonError('학생 목록이 필요합니다');

        $db = getDB();
        $count = 0;

        foreach ($studentIds as $sid) {
            $sid = (int)$sid;
            if (!$sid) continue;

            $stmt = $db->prepare('UPDATE junior_students SET is_active = 0 WHERE id = ?');
            $stmt->execute([$sid]);

            auditLog('junior_students', $sid, 'delete', null, null, null, '일괄 삭제',
                $admin['system_id'], 'system_admin', $admin['system_name']);
            $count++;
        }

        jsonSuccess(['count' => $count], "{$count}명의 학생이 삭제되었습니다");
        break;

    // 부모-학생 연결
    case 'link_parent':
        if ($method !== 'POST') jsonError('POST만 허용됩니다', 405);
        $admin = requireSystem();
        $input = getJsonInput();
        $parentId = (int)($input['parent_id'] ?? 0);
        $studentId = (int)($input['student_id'] ?? 0);

        if (!$parentId || !$studentId) jsonError('부모 ID와 학생 ID가 필요합니다');

        $db = getDB();
        $stmt = $db->prepare('INSERT IGNORE INTO junior_parent_students (parent_id, student_id) VALUES (?, ?)');
        $stmt->execute([$parentId, $studentId]);

        jsonSuccess([], '부모-학생이 연결되었습니다');
        break;

    // 학생 반 배정
    case 'assign_class':
        if ($method !== 'POST') jsonError('POST만 허용됩니다', 405);
        $admin = requireSystem();
        $input = getJsonInput();
        $studentId = (int)($input['student_id'] ?? 0);
        $classId = (int)($input['class_id'] ?? 0);
        $isPrimary = (int)($input['is_primary'] ?? 1);

        if (!$studentId || !$classId) jsonError('학생 ID와 반 ID가 필요합니다');

        $db = getDB();

        // 본반 배정 시 기존 본반 해제
        if ($isPrimary) {
            $stmt = $db->prepare('UPDATE junior_class_students SET is_primary = 0 WHERE student_id = ? AND is_primary = 1');
            $stmt->execute([$studentId]);
        }

        $stmt = $db->prepare('
            INSERT INTO junior_class_students (class_id, student_id, is_primary, joined_at)
            VALUES (?, ?, ?, CURDATE())
            ON DUPLICATE KEY UPDATE is_primary = VALUES(is_primary), is_active = 1
        ');
        $stmt->execute([$classId, $studentId, $isPrimary]);

        jsonSuccess([], '반 배정이 완료되었습니다');
        break;

    // ============================================
    // 초대 링크 (공유 버튼)
    // ============================================

    // 초대 토큰 생성 (super_admin만)
    case 'create_invite':
        if ($method !== 'POST') jsonError('POST만 허용됩니다', 405);
        $admin = requireSystem(['super_admin']);

        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1시간 후

        $db = getDB();
        $stmt = $db->prepare('
            INSERT INTO junior_system_invite_tokens (token, created_by, expires_at)
            VALUES (?, ?, ?)
        ');
        $stmt->execute([$token, $admin['system_id'], $expiresAt]);

        $url = 'https://j.soritune.com/system/invite.php?token=' . $token;

        jsonSuccess([
            'token'      => $token,
            'url'        => $url,
            'expires_at' => $expiresAt,
        ], '초대 링크가 생성되었습니다 (1시간 유효)');
        break;

    // 초대 토큰 확인 (인증 불필요)
    case 'verify_invite':
        $token = trim($_GET['token'] ?? '');
        if (!$token) jsonError('토큰이 필요합니다');

        $db = getDB();
        $stmt = $db->prepare('
            SELECT id, expires_at, used_at, is_active
            FROM junior_system_invite_tokens
            WHERE token = ?
        ');
        $stmt->execute([$token]);
        $invite = $stmt->fetch();

        if (!$invite) jsonError('유효하지 않은 초대 링크입니다');
        if (!$invite['is_active']) jsonError('이미 사용된 초대 링크입니다');
        if ($invite['used_at']) jsonError('이미 사용된 초대 링크입니다');
        if (strtotime($invite['expires_at']) < time()) jsonError('만료된 초대 링크입니다');

        jsonSuccess(['valid' => true, 'expires_at' => $invite['expires_at']]);
        break;

    // 초대 링크로 시스템관리자 등록 (인증 불필요)
    case 'register_via_invite':
        if ($method !== 'POST') jsonError('POST만 허용됩니다', 405);
        $input = getJsonInput();
        $token = trim($input['token'] ?? '');
        $loginId = trim($input['login_id'] ?? '');
        $password = trim($input['password'] ?? '');
        $name = trim($input['name'] ?? '');

        if (!$token || !$loginId || !$password || !$name) {
            jsonError('모든 항목을 입력해 주세요');
        }
        if (mb_strlen($password) < 6) jsonError('비밀번호는 6자 이상이어야 합니다');

        $db = getDB();

        // 토큰 유효성 확인
        $stmt = $db->prepare('
            SELECT id, expires_at, used_at, is_active
            FROM junior_system_invite_tokens
            WHERE token = ?
            FOR UPDATE
        ');
        $db->beginTransaction();
        $stmt->execute([$token]);
        $invite = $stmt->fetch();

        if (!$invite || !$invite['is_active'] || $invite['used_at'] || strtotime($invite['expires_at']) < time()) {
            $db->rollBack();
            jsonError('유효하지 않거나 만료된 초대 링크입니다');
        }

        // 아이디 중복 확인
        $stmt = $db->prepare('SELECT id FROM junior_system_admins WHERE login_id = ?');
        $stmt->execute([$loginId]);
        if ($stmt->fetch()) {
            $db->rollBack();
            jsonError('이미 사용중인 아이디입니다');
        }

        // 시스템관리자 생성
        $hash = password_hash($password, PASSWORD_ARGON2ID);
        $stmt = $db->prepare('INSERT INTO junior_system_admins (login_id, password_hash, name, role) VALUES (?, ?, ?, ?)');
        $stmt->execute([$loginId, $hash, $name, 'system_admin']);
        $newAdminId = $db->lastInsertId();

        // 토큰 사용 처리
        $clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        $clientIp = trim(explode(',', $clientIp)[0]);

        $stmt = $db->prepare('
            UPDATE junior_system_invite_tokens
            SET used_at = NOW(), used_by_ip = ?, used_by_admin_id = ?, is_active = 0
            WHERE id = ?
        ');
        $stmt->execute([$clientIp, $newAdminId, $invite['id']]);

        // IP 자동 로그인 등록
        $existingIps = getSetting('system_auto_login_ips', '');
        $ipList = array_filter(array_map('trim', explode(',', $existingIps)));
        if (!in_array($clientIp, $ipList)) {
            $ipList[] = $clientIp;
            $newIps = implode(',', $ipList);
            $stmt = $db->prepare("UPDATE junior_settings SET setting_value = ? WHERE setting_key = 'system_auto_login_ips'");
            $stmt->execute([$newIps]);
        }

        $db->commit();

        // 자동 로그인
        loginSystem($newAdminId, $name, 'system_admin');

        jsonSuccess([
            'admin' => [
                'id'   => $newAdminId,
                'name' => $name,
                'role' => 'system_admin',
            ]
        ], '시스템관리자 계정이 생성되었습니다');
        break;

    // ============================================
    // 대시보드: 과제 현황
    // ============================================
    case 'dashboard_assignments':
        $admin = requireSystem();
        $db = getDB();

        $date = trim($_GET['date'] ?? date('Y-m-d'));
        $classId = (int)($_GET['class_id'] ?? 0);

        // 오늘 과제 요약 (반별)
        if (!$classId) {
            $stmt = $db->prepare('
                SELECT c.id as class_id, c.display_name as class_name, c.coach_name,
                       COUNT(DISTINCT dc.student_id) as checked_count,
                       (SELECT COUNT(*) FROM junior_class_students cs2
                        WHERE cs2.class_id = c.id AND cs2.is_active = 1) as total_students,
                       SUM(dc.band_mission) as mission_done,
                       SUM(dc.zoom_attendance) as zoom_done,
                       SUM(dc.posture_king) as posture_done,
                       SUM(dc.sound_homework) as homework_done,
                       SUM(dc.leader_king) as leader_done
                FROM junior_classes c
                LEFT JOIN junior_daily_checklist dc ON c.id = dc.class_id AND dc.check_date = ?
                WHERE c.is_active = 1
                GROUP BY c.id
                ORDER BY c.sort_order
            ');
            $stmt->execute([$date]);
            $classSummary = $stmt->fetchAll();

            // 전체 통계
            $stmt = $db->prepare('
                SELECT COUNT(DISTINCT student_id) as total_checked,
                       SUM(band_mission) as total_mission,
                       SUM(zoom_attendance) as total_zoom,
                       SUM(posture_king) as total_posture,
                       SUM(sound_homework) as total_homework,
                       SUM(leader_king) as total_leader
                FROM junior_daily_checklist
                WHERE check_date = ?
            ');
            $stmt->execute([$date]);
            $overallStats = $stmt->fetch();

            $stmt = $db->prepare('SELECT COUNT(*) FROM junior_students WHERE is_active = 1');
            $stmt->execute();
            $totalStudents = (int)$stmt->fetchColumn();

            jsonSuccess([
                'date'           => $date,
                'total_students' => $totalStudents,
                'overall'        => $overallStats,
                'classes'        => $classSummary,
            ]);
        } else {
            // 특정 반 학생별 상세
            $stmt = $db->prepare('
                SELECT s.id, s.name,
                       dc.band_mission, dc.zoom_attendance, dc.posture_king,
                       dc.sound_homework, dc.leader_king, dc.note
                FROM junior_students s
                JOIN junior_class_students cs ON s.id = cs.student_id AND cs.is_active = 1
                LEFT JOIN junior_daily_checklist dc ON s.id = dc.student_id AND dc.check_date = ? AND dc.class_id = ?
                WHERE cs.class_id = ? AND s.is_active = 1
                ORDER BY s.name
            ');
            $stmt->execute([$date, $classId, $classId]);
            $students = $stmt->fetchAll();

            jsonSuccess([
                'date'     => $date,
                'class_id' => $classId,
                'students' => $students,
            ]);
        }
        break;

    // 달력 과제 현황 (월별 집계)
    case 'dashboard_calendar':
        $admin = requireSystem();
        $db = getDB();

        $month = trim($_GET['month'] ?? date('Y-m')); // "2026-01"
        $classId = (int)($_GET['class_id'] ?? 0);

        $startDate = $month . '-01';
        $endDate = date('Y-m-t', strtotime($startDate));

        $where = 'dc.check_date BETWEEN ? AND ?';
        $params = [$startDate, $endDate];

        if ($classId) {
            $where .= ' AND dc.class_id = ?';
            $params[] = $classId;
        }

        $stmt = $db->prepare("
            SELECT dc.check_date,
                   COUNT(DISTINCT dc.student_id) as checked_count,
                   SUM(dc.band_mission) as mission_done,
                   SUM(dc.zoom_attendance) as zoom_done,
                   SUM(dc.posture_king) as posture_done,
                   SUM(dc.sound_homework) as homework_done,
                   SUM(dc.leader_king) as leader_done
            FROM junior_daily_checklist dc
            WHERE {$where}
            GROUP BY dc.check_date
            ORDER BY dc.check_date
        ");
        $stmt->execute($params);
        $dailyStats = $stmt->fetchAll();

        // 총 학생 수
        if ($classId) {
            $stmt = $db->prepare('SELECT COUNT(*) FROM junior_class_students WHERE class_id = ? AND is_active = 1');
            $stmt->execute([$classId]);
        } else {
            $stmt = $db->prepare('SELECT COUNT(*) FROM junior_students WHERE is_active = 1');
            $stmt->execute();
        }
        $totalStudents = (int)$stmt->fetchColumn();

        jsonSuccess([
            'month'          => $month,
            'class_id'       => $classId,
            'total_students' => $totalStudents,
            'daily'          => $dailyStats,
        ]);
        break;

    // ============================================
    // 과제 체크 토글 (개별 항목)
    // ============================================
    case 'toggle_checklist':
        if ($method !== 'POST') jsonError('POST만 허용됩니다', 405);
        $admin = requireSystem();
        $input = getJsonInput();
        $studentId = (int)($input['student_id'] ?? 0);
        $classId   = (int)($input['class_id'] ?? 0);
        $date      = trim($input['date'] ?? '');
        $field     = trim($input['field'] ?? '');
        $value     = (int)($input['value'] ?? 0);

        $boolFields = ['sound_homework', 'band_mission', 'leader_king', 'reboot_card'];
        $numFields  = ['zoom_attendance', 'posture_king'];
        $validFields = array_merge($boolFields, $numFields);
        if (!in_array($field, $validFields, true)) jsonError('유효하지 않은 항목입니다');
        if (!$studentId || !$classId || !$date) jsonError('필수 항목이 누락되었습니다');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) jsonError('날짜 형식이 올바르지 않습니다');

        // 값 범위 검증: boolean 0/1, numeric 0~99
        if (in_array($field, $boolFields, true)) {
            $value = $value ? 1 : 0;
        } else {
            $value = max(0, min(99, $value));
        }

        $db = getDB();

        // 기존 행 조회
        $stmt = $db->prepare('SELECT id, ' . $field . ' FROM junior_daily_checklist WHERE student_id = ? AND class_id = ? AND check_date = ?');
        $stmt->execute([$studentId, $classId, $date]);
        $existing = $stmt->fetch();

        $oldValue = $existing ? (string)$existing[$field] : '0';
        $newValue = (string)$value;

        if ($existing) {
            $stmt = $db->prepare("UPDATE junior_daily_checklist SET {$field} = ? WHERE id = ?");
            $stmt->execute([$value, $existing['id']]);
            $recordId = (int)$existing['id'];
        } else {
            $stmt = $db->prepare("INSERT INTO junior_daily_checklist (student_id, class_id, check_date, coach_id, {$field}) VALUES (?, ?, ?, 0, ?)");
            $stmt->execute([$studentId, $classId, $date, $value]);
            $recordId = (int)$db->lastInsertId();
        }

        // 학생 이름 조회
        $stmt = $db->prepare('SELECT name FROM junior_students WHERE id = ?');
        $stmt->execute([$studentId]);
        $studentName = $stmt->fetchColumn() ?: '학생#' . $studentId;

        $fieldLabels = [
            'zoom_attendance' => '줌출석',
            'posture_king'    => '바른자세왕',
            'sound_homework'  => '소리과제',
            'band_mission'    => '밴드미션',
            'leader_king'     => '리더왕',
        ];

        auditLog('junior_daily_checklist', $recordId, 'update', $field, $oldValue, $newValue,
            "{$studentName} {$date} {$fieldLabels[$field]} " . ($value ? '체크' : '해제'),
            $admin['system_id'], 'system_admin', $admin['system_name']);

        jsonSuccess(['value' => $value, 'field' => $field]);
        break;

    // ============================================
    // 대행 로그인 (Impersonation)
    // ============================================

    // 코치쌤 대행 로그인
    case 'impersonate_coach':
        if ($method !== 'POST') jsonError('POST만 허용됩니다', 405);
        $admin = requireSystem();
        $input = getJsonInput();
        $coachId = (int)($input['coach_id'] ?? 0);
        if (!$coachId) jsonError('코치 ID가 필요합니다');

        $db = getDB();
        $stmt = $db->prepare('SELECT * FROM junior_admins WHERE id = ? AND role = ? AND is_active = 1');
        $stmt->execute([$coachId, 'coach']);
        $coach = $stmt->fetch();
        if (!$coach) jsonError('코치를 찾을 수 없습니다');

        // 코치 세션 생성 (시스템관리자 세션은 유지)
        loginAdmin($coach['id'], $coach['name'], $coach['role']);

        auditLog('junior_admins', $coach['id'], 'update', null, null,
            $coach['name'], '시스템관리자 대행 로그인: ' . $coach['name'],
            $admin['system_id'], 'system_admin', $admin['system_name']);

        jsonSuccess([
            'coach' => [
                'id' => $coach['id'],
                'name' => $coach['name'],
                'role' => $coach['role'],
            ]
        ], $coach['name'] . ' 코치쌤으로 로그인합니다');
        break;

    // 학생 대행 로그인
    case 'impersonate_student':
        if ($method !== 'POST') jsonError('POST만 허용됩니다', 405);
        $admin = requireSystem();
        $input = getJsonInput();
        $studentId = (int)($input['student_id'] ?? 0);
        if (!$studentId) jsonError('학생 ID가 필요합니다');

        $db = getDB();
        $stmt = $db->prepare('
            SELECT s.id, s.name, cs.class_id, c.display_name as class_name
            FROM junior_students s
            LEFT JOIN junior_class_students cs ON s.id = cs.student_id AND cs.is_primary = 1 AND cs.is_active = 1
            LEFT JOIN junior_classes c ON cs.class_id = c.id
            WHERE s.id = ? AND s.is_active = 1
        ');
        $stmt->execute([$studentId]);
        $student = $stmt->fetch();
        if (!$student) jsonError('학생을 찾을 수 없습니다');

        // 학생 세션 생성 (시스템관리자 세션은 유지)
        loginStudent($student['id'], $student['name'], $student['class_id'] ? (int)$student['class_id'] : null, $student['class_name']);

        auditLog('junior_students', $student['id'], 'update', null, null,
            $student['name'], '시스템관리자 대행 로그인: ' . $student['name'],
            $admin['system_id'], 'system_admin', $admin['system_name']);

        jsonSuccess([
            'student' => [
                'id' => $student['id'],
                'name' => $student['name'],
                'class_name' => $student['class_name'],
            ]
        ], $student['name'] . ' 학생으로 로그인합니다');
        break;

    // 코치 목록 (대행용)
    case 'coach_list':
        $admin = requireSystem();
        $db = getDB();
        $stmt = $db->prepare('
            SELECT a.id, a.name, a.login_id,
                   GROUP_CONCAT(c.display_name ORDER BY c.sort_order SEPARATOR ", ") as class_names
            FROM junior_admins a
            LEFT JOIN junior_admin_classes ac ON a.id = ac.admin_id AND ac.is_active = 1
            LEFT JOIN junior_classes c ON ac.class_id = c.id AND c.is_active = 1
            WHERE a.role = "coach" AND a.is_active = 1
            GROUP BY a.id
            ORDER BY a.name
        ');
        $stmt->execute();
        jsonSuccess(['coaches' => $stmt->fetchAll()]);
        break;

    // 반별 학생 목록 (대행용)
    case 'students_by_class':
        $admin = requireSystem();
        $db = getDB();
        $classId = (int)($_GET['class_id'] ?? 0);
        if (!$classId) jsonError('반 ID가 필요합니다');

        $stmt = $db->prepare('
            SELECT s.id, s.name, s.grade, s.phone_last4
            FROM junior_students s
            JOIN junior_class_students cs ON s.id = cs.student_id AND cs.class_id = ? AND cs.is_active = 1
            WHERE s.is_active = 1
            ORDER BY s.name
        ');
        $stmt->execute([$classId]);
        jsonSuccess(['students' => $stmt->fetchAll()]);
        break;

    // 관리쌤/부모 목록 (대행용)
    case 'admin_list':
        $admin = requireSystem();
        $db = getDB();
        $role = trim($_GET['role'] ?? '');
        $where = 'a.is_active = 1 AND a.role IN ("admin_teacher", "parent")';
        $params = [];
        if ($role && in_array($role, ['admin_teacher', 'parent'])) {
            $where = 'a.is_active = 1 AND a.role = ?';
            $params[] = $role;
        }
        $stmt = $db->prepare("
            SELECT a.id, a.name, a.login_id, a.role, a.phone,
                   GROUP_CONCAT(c.display_name ORDER BY c.sort_order SEPARATOR ', ') as class_names
            FROM junior_admins a
            LEFT JOIN junior_admin_classes ac ON a.id = ac.admin_id AND ac.is_active = 1
            LEFT JOIN junior_classes c ON ac.class_id = c.id AND c.is_active = 1
            WHERE {$where}
            GROUP BY a.id
            ORDER BY a.role, a.name
        ");
        $stmt->execute($params);
        jsonSuccess(['admins' => $stmt->fetchAll()]);
        break;

    // 관리쌤/부모 대행 로그인
    case 'impersonate_admin':
        if ($method !== 'POST') jsonError('POST만 허용됩니다', 405);
        $admin = requireSystem();
        $input = getJsonInput();
        $adminId = (int)($input['admin_id'] ?? 0);
        if (!$adminId) jsonError('관리자 ID가 필요합니다');

        $db = getDB();
        $stmt = $db->prepare('SELECT * FROM junior_admins WHERE id = ? AND role IN ("admin_teacher", "parent") AND is_active = 1');
        $stmt->execute([$adminId]);
        $target = $stmt->fetch();
        if (!$target) jsonError('관리자를 찾을 수 없습니다');

        loginAdmin($target['id'], $target['name'], $target['role']);

        $roleLabel = $target['role'] === 'parent' ? '부모' : '관리쌤';
        auditLog('junior_admins', $target['id'], 'update', null, null,
            $target['name'], "시스템관리자 대행 로그인: {$target['name']} ({$roleLabel})",
            $admin['system_id'], 'system_admin', $admin['system_name']);

        jsonSuccess([
            'admin' => [
                'id' => $target['id'],
                'name' => $target['name'],
                'role' => $target['role'],
            ]
        ], $target['name'] . " ({$roleLabel})으로 로그인합니다");
        break;

    // 전체 반 과제율 랭킹 (시스템관리자용)
    case 'class_assignment_ranking':
        $admin = requireSystem();
        $db = getDB();
        $date = trim($_GET['date'] ?? date('Y-m-d'));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');

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

        $ranked = [];
        foreach ($classes as &$cls) {
            $total = (int)$cls['total_students'];
            $checked = (int)$cls['checked_count'];
            $cls['completion_rate'] = $total > 0 ? round(($checked / $total) * 100) : 0;
            $ranked[] = $cls;
        }
        unset($cls);

        usort($ranked, function($a, $b) {
            return $b['completion_rate'] - $a['completion_rate'];
        });

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

    // 대시보드 종합 데이터 (7일 추세 + 오늘 항목별 + 반별 요약)
    case 'dashboard_enhanced':
        $admin = requireSystem();
        $db = getDB();
        $realToday = date('Y-m-d');
        $targetDate = trim($_GET['date'] ?? $realToday);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate)) $targetDate = $realToday;

        // 1) 선택 날짜 기준 이전 7일 추세 (선택 날짜 포함, 그 이전 6일)
        $trendEnd   = $targetDate;
        $trendStart = date('Y-m-d', strtotime($targetDate . ' -6 days'));
        $stmt = $db->prepare('
            SELECT dc.check_date,
                   COUNT(DISTINCT dc.student_id) as checked_students,
                   COALESCE(SUM(dc.zoom_attendance),0) as zoom_total,
                   COALESCE(SUM(dc.posture_king),0) as posture_total,
                   COALESCE(SUM(dc.sound_homework),0) as homework_total,
                   COALESCE(SUM(dc.band_mission),0) as mission_total,
                   COALESCE(SUM(dc.leader_king),0) as leader_total
            FROM junior_daily_checklist dc
            WHERE dc.check_date >= ? AND dc.check_date <= ?
            GROUP BY dc.check_date
            ORDER BY dc.check_date ASC
        ');
        $stmt->execute([$trendStart, $trendEnd]);
        $dailyTrend = $stmt->fetchAll();

        // 전체 활성 학생 수
        $totalActive = (int)$db->query('SELECT COUNT(*) FROM junior_students WHERE is_active=1')->fetchColumn();

        // 일별 추세 데이터를 날짜 키 맵으로 변환
        $trendMap = [];
        foreach ($dailyTrend as $row) {
            $trendMap[$row['check_date']] = $row;
        }

        // 7일 모든 날짜를 채워서 빈 날짜 없이 반환
        $trendData = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime($targetDate . " -{$i} days"));
            if (isset($trendMap[$d])) {
                $row = $trendMap[$d];
                $checked = (int)$row['checked_students'];
                $trendData[] = [
                    'date' => $d,
                    'checked_students' => $checked,
                    'total_students' => $totalActive,
                    'completion_rate' => $totalActive > 0 ? round(($checked / $totalActive) * 100) : 0,
                    'zoom' => (int)$row['zoom_total'],
                    'posture' => (int)$row['posture_total'],
                    'homework' => (int)$row['homework_total'],
                    'mission' => (int)$row['mission_total'],
                    'leader' => (int)$row['leader_total'],
                ];
            } else {
                $trendData[] = [
                    'date' => $d,
                    'checked_students' => 0,
                    'total_students' => $totalActive,
                    'completion_rate' => 0,
                    'zoom' => 0, 'posture' => 0, 'homework' => 0, 'mission' => 0, 'leader' => 0,
                ];
            }
        }

        // 2) 선택 날짜 항목별 현황
        $stmt2 = $db->prepare('
            SELECT COALESCE(SUM(zoom_attendance),0) as zoom,
                   COALESCE(SUM(posture_king),0) as posture,
                   COALESCE(SUM(sound_homework),0) as homework,
                   COALESCE(SUM(band_mission),0) as mission,
                   COALESCE(SUM(leader_king),0) as leader,
                   COUNT(DISTINCT student_id) as checked_students
            FROM junior_daily_checklist
            WHERE check_date = ?
        ');
        $stmt2->execute([$targetDate]);
        $todayStats = $stmt2->fetch();

        // 3) 반별 선택 날짜 요약 + 코치 정보
        $stmt3 = $db->prepare('
            SELECT c.id, c.display_name, c.coach_name, c.color,
                   (SELECT COUNT(*) FROM junior_class_students cs WHERE cs.class_id=c.id AND cs.is_active=1) as total_students,
                   COUNT(DISTINCT dc.student_id) as checked_count,
                   COALESCE(SUM(dc.zoom_attendance),0) as zoom_done,
                   COALESCE(SUM(dc.posture_king),0) as posture_done,
                   COALESCE(SUM(dc.sound_homework),0) as homework_done,
                   COALESCE(SUM(dc.band_mission),0) as mission_done,
                   COALESCE(SUM(dc.leader_king),0) as leader_done
            FROM junior_classes c
            LEFT JOIN junior_daily_checklist dc ON c.id = dc.class_id AND dc.check_date = ?
            WHERE c.is_active = 1
            GROUP BY c.id
            ORDER BY c.sort_order
        ');
        $stmt3->execute([$targetDate]);
        $classSummary = $stmt3->fetchAll();

        foreach ($classSummary as &$cls) {
            $total = (int)$cls['total_students'];
            $checked = (int)$cls['checked_count'];
            $cls['completion_rate'] = $total > 0 ? round(($checked / $total) * 100) : 0;
        }
        unset($cls);

        // 4) 선택 날짜가 데이터 0일 때 → 가장 최근 데이터 있는 날짜 조회
        $lastDataDate = null;
        if ((int)$todayStats['checked_students'] === 0) {
            $stmt5 = $db->prepare('
                SELECT check_date FROM junior_daily_checklist
                WHERE check_date <= ?
                GROUP BY check_date HAVING COUNT(DISTINCT student_id) > 0
                ORDER BY check_date DESC LIMIT 1
            ');
            $stmt5->execute([$targetDate]);
            $lastRow = $stmt5->fetch();
            if ($lastRow) $lastDataDate = $lastRow['check_date'];
        }

        // 5) 최근 로그인한 관리자 (24시간 이내)
        $stmt4 = $db->prepare('
            SELECT name, role, last_login_at as last_login
            FROM junior_admins
            WHERE is_active = 1 AND last_login_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY last_login_at DESC
            LIMIT 10
        ');
        $stmt4->execute();
        $recentLogins = $stmt4->fetchAll();

        jsonSuccess([
            'date' => $targetDate,
            'last_data_date' => $lastDataDate,
            'total_students' => $totalActive,
            'today' => [
                'zoom' => (int)$todayStats['zoom'],
                'posture' => (int)$todayStats['posture'],
                'homework' => (int)$todayStats['homework'],
                'mission' => (int)$todayStats['mission'],
                'leader' => (int)$todayStats['leader'],
                'checked_students' => (int)$todayStats['checked_students'],
            ],
            'trend' => $trendData,
            'classes' => $classSummary,
            'recent_logins' => $recentLogins,
        ]);
        break;

    // 부모 접속 QR 코드 생성
    case 'generate_parent_qr':
        requireSystem();
        require_once __DIR__ . '/../qr/lib/QRRenderer.php';
        $renderer = new QRRenderer();
        $qrImage = $renderer->generateBase64('https://j.soritune.com/admin/', 300);
        jsonSuccess(['qr_image' => $qrImage]);
        break;

    // ── 시스템 관리자 생성 링크 ──
    case 'generate_setup_link':
        requireSystem();
        if ($method !== 'POST') jsonError('POST만 허용됩니다', 405);
        $session = getSystemSession();
        $db = getDB();

        $db->exec("UPDATE junior_setup_tokens SET is_active = 0 WHERE used_at IS NULL AND is_active = 1");

        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 3600);

        $db->prepare("INSERT INTO junior_setup_tokens (token, created_by, created_by_name, expires_at) VALUES (?, ?, ?, ?)")
            ->execute([$token, $session['system_id'], $session['system_name'], $expiresAt]);

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'j.soritune.com';
        $link = "{$protocol}://{$host}/system/setup.php?token={$token}";

        jsonSuccess(['link' => $link, 'expires_at' => $expiresAt, 'token' => $token]);
        break;

    case 'validate_setup_token':
        $token = trim($_GET['token'] ?? '');
        if (!$token) jsonError('토큰이 필요합니다.');

        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM junior_setup_tokens WHERE token = ? AND is_active = 1 AND expires_at > NOW() AND used_at IS NULL");
        $stmt->execute([$token]);
        $row = $stmt->fetch();

        if (!$row) jsonError('유효하지 않거나 만료된 링크입니다.');
        jsonSuccess(['valid' => true, 'expires_at' => $row['expires_at'], 'created_by_name' => $row['created_by_name']]);
        break;

    case 'use_setup_token':
        if ($method !== 'POST') jsonError('POST만 허용됩니다', 405);
        $input = getJsonInput();
        $token = trim($input['token'] ?? '');
        $loginId = trim($input['login_id'] ?? '');
        $password = $input['password'] ?? '';
        $name = trim($input['name'] ?? '');

        if (!$token) jsonError('토큰이 필요합니다.');
        if (!$loginId || strlen($loginId) < 3) jsonError('아이디는 3자 이상 입력해주세요.');
        if (!$password || strlen($password) < 6) jsonError('비밀번호는 6자 이상 입력해주세요.');
        if (!$name) jsonError('이름을 입력해주세요.');

        $db = getDB();

        $stmt = $db->prepare("SELECT * FROM junior_setup_tokens WHERE token = ? AND is_active = 1 AND expires_at > NOW() AND used_at IS NULL");
        $stmt->execute([$token]);
        $tokenRow = $stmt->fetch();
        if (!$tokenRow) jsonError('유효하지 않거나 만료된 링크입니다.');

        $dup = $db->prepare("SELECT id FROM junior_system_admins WHERE login_id = ?");
        $dup->execute([$loginId]);
        if ($dup->fetch()) jsonError('이미 사용 중인 아이디입니다.');

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $db->prepare("INSERT INTO junior_system_admins (login_id, password_hash, name, role, is_active) VALUES (?, ?, ?, 'super_admin', 1)")
            ->execute([$loginId, $hash, $name]);
        $newAdminId = (int)$db->lastInsertId();

        $clientIP = getClientIP();
        $ipExpires = date('Y-m-d H:i:s', time() + 86400);
        $db->prepare("INSERT INTO junior_temp_ip_access (token, ip_address, created_by, created_by_name, expires_at, is_active) VALUES (?, ?, ?, ?, ?, 1)")
            ->execute([bin2hex(random_bytes(16)), $clientIP, $newAdminId, $name, $ipExpires]);

        $db->prepare("UPDATE junior_setup_tokens SET used_at = NOW(), used_ip = ?, created_admin_id = ?, is_active = 0 WHERE id = ?")
            ->execute([$clientIP, $newAdminId, $tokenRow['id']]);

        jsonSuccess(['admin_id' => $newAdminId, 'name' => $name, 'ip_whitelisted' => $clientIP, 'ip_expires' => $ipExpires]);
        break;

    default:
        jsonError('알 수 없는 요청입니다', 404);
}
