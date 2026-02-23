<?php
/**
 * 소리튠 주니어 영어학교 - 3종 세션 관리
 * 학생 / 관리자(코치,관리쌤,부모) / 시스템관리자 독립 세션
 */

require_once __DIR__ . '/config.php';

// ============================================
// 세션 쿠키 설정
// ============================================
define('SESSION_CONFIGS', [
    'student' => [
        'cookie_name' => 'JUNIOR_STUDENT_SID',
        'lifetime'    => 86400 * 30, // 30일
        'path'        => '/',
        'samesite'    => 'Lax',
    ],
    'admin' => [
        'cookie_name' => 'JUNIOR_ADMIN_SID',
        'lifetime'    => 86400,      // 24시간
        'path'        => '/',
        'samesite'    => 'Lax',
    ],
    'system' => [
        'cookie_name' => 'JUNIOR_SYSTEM_SID',
        'lifetime'    => 14400,      // 4시간
        'path'        => '/',
        'samesite'    => 'Strict',
    ],
]);

// ============================================
// 학생 세션
// ============================================

function startStudentSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        if (session_name() === SESSION_CONFIGS['student']['cookie_name']) {
            return;
        }
        session_write_close();
        session_id(''); // 이전 세션 ID 초기화 → 쿠키에서 올바른 ID 읽기
    }

    $config = SESSION_CONFIGS['student'];
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    session_name($config['cookie_name']);
    session_set_cookie_params([
        'lifetime' => $config['lifetime'],
        'path'     => $config['path'],
        'secure'   => $isSecure,
        'httponly'  => true,
        'samesite'  => $config['samesite'],
    ]);

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function loginStudent(int $studentId, string $name, ?int $classId = null, ?string $className = null): void {
    startStudentSession();
    session_regenerate_id(true);

    $_SESSION['student_id']    = $studentId;
    $_SESSION['student_name']  = $name;
    $_SESSION['class_id']      = $classId;
    $_SESSION['class_name']    = $className;
    $_SESSION['login_type']    = 'student';
    $_SESSION['login_at']      = time();
}

function getStudentSession(): ?array {
    startStudentSession();
    if (empty($_SESSION['student_id']) || ($_SESSION['login_type'] ?? '') !== 'student') {
        return null;
    }
    return [
        'student_id'   => $_SESSION['student_id'],
        'student_name' => $_SESSION['student_name'],
        'class_id'     => $_SESSION['class_id'] ?? null,
        'class_name'   => $_SESSION['class_name'] ?? null,
    ];
}

function requireStudent(): array {
    $session = getStudentSession();
    if (!$session) {
        jsonError('로그인이 필요합니다', 401);
    }
    return $session;
}

function logoutStudent(): void {
    startStudentSession();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

// ============================================
// 관리자 세션 (코치/관리쌤/부모)
// ============================================

function startAdminSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        if (session_name() === SESSION_CONFIGS['admin']['cookie_name']) {
            return;
        }
        session_write_close();
        session_id(''); // 이전 세션 ID 초기화 → 쿠키에서 올바른 ID 읽기
    }

    $config = SESSION_CONFIGS['admin'];
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    session_name($config['cookie_name']);
    session_set_cookie_params([
        'lifetime' => $config['lifetime'],
        'path'     => $config['path'],
        'secure'   => $isSecure,
        'httponly'  => true,
        'samesite'  => $config['samesite'],
    ]);

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function loginAdmin(int $adminId, string $name, string $role): void {
    startAdminSession();
    session_regenerate_id(true);

    $_SESSION['admin_id']    = $adminId;
    $_SESSION['admin_name']  = $name;
    $_SESSION['admin_role']  = $role;
    $_SESSION['login_type']  = 'admin';
    $_SESSION['login_at']    = time();

    // 마지막 로그인 시간 업데이트
    $db = getDB();
    $stmt = $db->prepare('UPDATE junior_admins SET last_login_at = NOW() WHERE id = ?');
    $stmt->execute([$adminId]);
}

function loginParent(string $phone, string $parentName, array $studentIds): void {
    startAdminSession();
    session_regenerate_id(true);

    $_SESSION['admin_id']            = 0;
    $_SESSION['admin_name']          = $parentName;
    $_SESSION['admin_role']          = 'parent';
    $_SESSION['login_type']          = 'admin';
    $_SESSION['login_at']            = time();
    $_SESSION['parent_phone']        = $phone;
    $_SESSION['parent_student_ids']  = $studentIds;
}

function saveParentFingerprint(PDO $db, string $phone, string $fingerprint, $deviceInfo = null): void {
    $stmt = $db->prepare('
        INSERT INTO junior_parent_fingerprints (phone, fingerprint, device_info)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE is_active = 1, last_used_at = NOW(), device_info = VALUES(device_info)
    ');
    $stmt->execute([$phone, $fingerprint, $deviceInfo ? json_encode($deviceInfo) : null]);
}

function getAdminSession(): ?array {
    startAdminSession();
    if (($_SESSION['login_type'] ?? '') !== 'admin') {
        return null;
    }

    $role = $_SESSION['admin_role'] ?? '';
    $adminId = $_SESSION['admin_id'] ?? 0;

    // parent는 admin_id=0이므로 별도 처리
    if (!$adminId && $role !== 'parent') {
        return null;
    }

    $result = [
        'admin_id'   => $adminId,
        'admin_name' => $_SESSION['admin_name'],
        'admin_role' => $role,
    ];

    if ($role === 'parent') {
        $result['parent_student_ids'] = $_SESSION['parent_student_ids'] ?? [];
        $result['parent_phone'] = $_SESSION['parent_phone'] ?? '';
    }

    return $result;
}

function requireAdmin(array $roles = []): array {
    $session = getAdminSession();
    if (!$session) {
        jsonError('관리자 로그인이 필요합니다', 401);
    }
    if (!empty($roles) && !in_array($session['admin_role'], $roles)) {
        jsonError('접근 권한이 없습니다', 403);
    }
    return $session;
}

function logoutAdmin(): void {
    startAdminSession();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

// ============================================
// 시스템관리자 세션
// ============================================

function startSystemSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        if (session_name() === SESSION_CONFIGS['system']['cookie_name']) {
            return;
        }
        session_write_close();
        session_id(''); // 이전 세션 ID 초기화 → 쿠키에서 올바른 ID 읽기
    }

    $config = SESSION_CONFIGS['system'];
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    session_name($config['cookie_name']);
    session_set_cookie_params([
        'lifetime' => $config['lifetime'],
        'path'     => $config['path'],
        'secure'   => $isSecure,
        'httponly'  => true,
        'samesite'  => $config['samesite'],
    ]);

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function loginSystem(int $adminId, string $name, string $role): void {
    startSystemSession();
    session_regenerate_id(true);

    $_SESSION['system_id']   = $adminId;
    $_SESSION['system_name'] = $name;
    $_SESSION['system_role'] = $role;
    $_SESSION['login_type']  = 'system';
    $_SESSION['login_at']    = time();

    // 마지막 로그인 시간 업데이트
    $db = getDB();
    $stmt = $db->prepare('UPDATE junior_system_admins SET last_login_at = NOW() WHERE id = ?');
    $stmt->execute([$adminId]);
}

function getSystemSession(): ?array {
    startSystemSession();
    if (empty($_SESSION['system_id']) || ($_SESSION['login_type'] ?? '') !== 'system') {
        return null;
    }
    return [
        'system_id'   => $_SESSION['system_id'],
        'system_name' => $_SESSION['system_name'],
        'system_role' => $_SESSION['system_role'],
    ];
}

function requireSystem(array $roles = []): array {
    $session = getSystemSession();
    if (!$session) {
        jsonError('시스템 관리자 로그인이 필요합니다', 401);
    }
    if (!empty($roles) && !in_array($session['system_role'], $roles)) {
        jsonError('시스템 관리자 권한이 없습니다', 403);
    }
    return $session;
}

function logoutSystem(): void {
    startSystemSession();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}
