<?php
/**
 * 소리튠 주니어 영어학교 - 핵심 설정
 * DB 싱글톤, 공통 함수, 상수 정의
 */

// 에러 리포팅 (프로덕션에서는 끄기)
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', dirname(__DIR__) . '/logs/php_error.log');

// 타임존
date_default_timezone_set('Asia/Seoul');

// DB 자격증명 로드
function loadDbCredentials(): array {
    $credFile = dirname(__DIR__) . '/.db_credentials';
    if (!file_exists($credFile)) {
        throw new RuntimeException('DB credentials file not found');
    }
    $lines = file($credFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $creds = [];
    foreach ($lines as $line) {
        if (str_contains($line, '=')) {
            [$key, $val] = explode('=', $line, 2);
            $creds[trim($key)] = trim($val);
        }
    }
    return $creds;
}

// PDO 싱글톤
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $creds = loadDbCredentials();
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            $creds['DB_HOST'],
            $creds['DB_NAME']
        );
        $pdo = new PDO($dsn, $creds['DB_USER'], $creds['DB_PASS'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        // MySQL 세션 시간대를 PHP(Asia/Seoul)와 동기화
        $pdo->exec("SET time_zone = '+09:00'");
    }
    return $pdo;
}

// Composer autoload
require_once dirname(__DIR__) . '/vendor/autoload.php';

// ============================================
// 상수 정의
// ============================================

// 카드 정의 (코드 → 한글명, 코인값, 색상)
define('CARD_TYPES', [
    'steady'  => ['name' => '꾸준왕',   'coins' => 5, 'color' => '#4CAF50', 'en' => 'Steady King'],
    'leader'  => ['name' => '리더왕',   'coins' => 2, 'color' => '#2196F3', 'en' => 'Leader King'],
    'mission' => ['name' => '미션왕',   'coins' => 1, 'color' => '#FF9800', 'en' => 'Mission King'],
    'posture' => ['name' => '바른자세왕', 'coins' => 1, 'color' => '#9C27B0', 'en' => 'Posture King'],
    'passion' => ['name' => '열정왕',   'coins' => 1, 'color' => '#F44336', 'en' => 'Passion King'],
    'reboot'  => ['name' => '리부트',   'coins' => 2, 'color' => '#00BCD4', 'en' => 'Reboot'],
]);

// 체크리스트 항목 → 카드 매핑
define('CHECKLIST_CARD_MAP', [
    'zoom_attendance' => 'passion',    // 줌출석 → 열정왕
    'posture_king'    => 'posture',    // 자세왕 → 바른자세왕
    'sound_homework'  => 'steady',     // 소리과제 → 꾸준왕
    'band_mission'    => 'mission',    // 밴드미션 → 미션왕
    'leader_king'     => 'leader',     // 리더왕 → 리더왕
    'reboot_card'     => 'reboot',     // 리부트 → 리부트
]);

// 디자인 색상
define('COLORS', [
    'main_bg'     => '#FF7E17',
    'coin_badge'  => '#F5B422',
    'coin_number' => '#F5A623',
    'gold'        => '#FFD700',
    'silver'      => '#C0C0C0',
    'bronze'      => '#CD7F32',
]);

// ============================================
// 공통 유틸리티 함수
// ============================================

/**
 * JSON 응답 반환
 */
function jsonResponse(array $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * JSON 에러 응답
 */
function jsonError(string $message, int $code = 400, array $extra = []): never {
    jsonResponse(array_merge(['success' => false, 'error' => $message], $extra), $code);
}

/**
 * JSON 성공 응답
 */
function jsonSuccess(array $data = [], string $message = ''): never {
    $response = ['success' => true];
    if ($message) $response['message'] = $message;
    jsonResponse(array_merge($response, $data));
}

/**
 * XSS 방지 이스케이프
 */
function e(string $val): string {
    return htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
}

/**
 * 요청 JSON 바디 파싱
 */
function getJsonInput(): array {
    $raw = file_get_contents('php://input');
    if (empty($raw)) return $_POST;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/**
 * 요청 액션 가져오기
 */
function getAction(): string {
    return $_GET['action'] ?? $_POST['action'] ?? '';
}

/**
 * 요청 메서드 확인
 */
function getMethod(): string {
    return strtoupper($_SERVER['REQUEST_METHOD']);
}

/**
 * 클라이언트 IP 주소
 */
function getClientIP(): string {
    $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = explode(',', $_SERVER[$header])[0];
            return trim($ip);
        }
    }
    return '0.0.0.0';
}

/**
 * 공용 IP 화이트리스트 로드
 * /var/www/html/.shared_ip_whitelist 파일에서 읽음 (웹 접근 불가)
 */
function getSharedWhitelistedIPs(): array {
    static $ips = null;
    if ($ips !== null) return $ips;

    $file = '/var/www/html/.shared_ip_whitelist';
    $ips = [];
    if (file_exists($file)) {
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '' && $line[0] !== '#') {
                $ips[] = $line;
            }
        }
    }
    return $ips;
}

/**
 * IP가 화이트리스트에 포함되는지 확인
 * 공유 파일 + DB 설정 + 임시 IP 모두 확인
 */
function isWhitelistedIP(?string $ip = null): bool {
    $ip = $ip ?? getClientIP();

    // 1. 공용 파일 체크
    if (in_array($ip, getSharedWhitelistedIPs(), true)) return true;

    // 2. DB 설정 체크 (J사이트: 콤마 구분 문자열)
    $dbIPs = getSetting('system_auto_login_ips', '');
    if (is_string($dbIPs)) {
        $ipList = array_map('trim', explode(',', $dbIPs));
        if (in_array($ip, $ipList, true)) return true;
    } elseif (is_array($dbIPs) && in_array($ip, $dbIPs, true)) {
        return true;
    }

    // 3. 임시 IP 체크
    try {
        $db = getDB();
        $stmt = $db->prepare('SELECT 1 FROM junior_temp_ip_access WHERE ip_address = ? AND is_active = 1 AND expires_at > NOW() LIMIT 1');
        $stmt->execute([$ip]);
        if ($stmt->fetch()) return true;
    } catch (\Exception $e) {}

    return false;
}

/**
 * 감사 로그 기록
 */
function auditLog(
    string $tableName,
    int $recordId,
    string $action,
    ?string $fieldName,
    ?string $oldValue,
    ?string $newValue,
    ?string $reason,
    ?int $changedBy,
    string $changedByType,
    ?string $changedByName = null
): void {
    $db = getDB();
    $stmt = $db->prepare('
        INSERT INTO junior_edit_audit_log
        (table_name, record_id, action, field_name, old_value, new_value, reason, changed_by, changed_by_type, changed_by_name, ip_address)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $tableName, $recordId, $action, $fieldName,
        $oldValue, $newValue, $reason,
        $changedBy, $changedByType, $changedByName,
        getClientIP()
    ]);
}

/**
 * 시스템 설정 조회
 */
function getSetting(string $key, mixed $default = null): mixed {
    static $cache = [];
    if (isset($cache[$key])) return $cache[$key];

    $db = getDB();
    $stmt = $db->prepare('SELECT setting_value, setting_type FROM junior_settings WHERE setting_key = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch();

    if (!$row) return $default;

    $val = match($row['setting_type']) {
        'number'  => (int)$row['setting_value'],
        'boolean' => (bool)(int)$row['setting_value'],
        'json'    => json_decode($row['setting_value'], true),
        default   => $row['setting_value'],
    };

    $cache[$key] = $val;
    return $val;
}

// ============================================
// 이미지 업로드 유틸
// ============================================
define('MSG_UPLOAD_DIR', dirname(__DIR__) . '/msg_uploads');
define('ANN_UPLOAD_DIR', dirname(__DIR__) . '/ann_uploads');
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp']);
define('MAX_IMAGE_SIZE', 5 * 1024 * 1024); // 5MB

/**
 * 이미지 파일 업로드 처리
 * @param array $file $_FILES['image'] 형식
 * @param string $baseDir 저장 기본 디렉토리 (MSG_UPLOAD_DIR 또는 ANN_UPLOAD_DIR)
 * @param string $subDir 하위 디렉토리 (예: thread_id)
 * @return string|null 저장된 상대 경로 또는 null (파일 없는 경우)
 */
function uploadImage(array $file, string $baseDir, string $subDir = ''): ?string {
    if ($file['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($file['error'] !== UPLOAD_ERR_OK) {
        error_log("uploadImage error: code={$file['error']}, name={$file['name']}, size={$file['size']}");
        jsonError('파일 업로드에 실패했습니다');
    }
    if ($file['size'] > MAX_IMAGE_SIZE) jsonError('이미지는 5MB 이하여야 합니다');

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    if (!in_array($mimeType, ALLOWED_IMAGE_TYPES, true)) {
        jsonError('JPG, PNG, WebP 이미지만 허용됩니다');
    }

    $ext = match($mimeType) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        default      => 'jpg',
    };

    $dir = $baseDir . ($subDir ? "/{$subDir}" : '');
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $fileName = uniqid() . '_' . bin2hex(random_bytes(4)) . ".{$ext}";
    $relPath = ($subDir ? "{$subDir}/" : '') . $fileName;
    $fullPath = "{$dir}/{$fileName}";

    if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
        jsonError('파일 저장에 실패했습니다');
    }

    return $relPath;
}

/**
 * 학생 총 코인 계산
 */
function getStudentTotalCoins(int $studentId): int {
    $db = getDB();
    $stmt = $db->prepare('
        SELECT COALESCE(SUM(sr.quantity * rt.coin_value), 0) + COALESCE(s.coin_offset, 0) as total_coins
        FROM junior_students s
        LEFT JOIN junior_student_rewards sr ON sr.student_id = s.id
        LEFT JOIN junior_reward_types rt ON sr.reward_type_id = rt.id
        WHERE s.id = ?
    ');
    $stmt->execute([$studentId]);
    return (int)$stmt->fetchColumn();
}

/**
 * 학생 카드 수량 조회
 */
function getStudentRewards(int $studentId): array {
    $db = getDB();
    $stmt = $db->prepare('
        SELECT rt.code, rt.name_ko, rt.name_en, rt.coin_value, rt.color, rt.image_file,
               COALESCE(sr.quantity, 0) as quantity
        FROM junior_reward_types rt
        LEFT JOIN junior_student_rewards sr ON sr.reward_type_id = rt.id AND sr.student_id = ?
        WHERE rt.is_active = 1
        ORDER BY rt.sort_order
    ');
    $stmt->execute([$studentId]);
    return $stmt->fetchAll();
}

/**
 * 주간 카드 사용량 조회 (월~일 KST 기준)
 * @return array ['used' => int, 'limit' => int|null, 'remaining' => int|null]
 */
function getWeeklyCardUsage(int $studentId, string $rewardCode): array {
    $db = getDB();

    $stmt = $db->prepare('SELECT id, weekly_limit FROM junior_reward_types WHERE code = ?');
    $stmt->execute([$rewardCode]);
    $type = $stmt->fetch();
    if (!$type) return ['used' => 0, 'limit' => null, 'remaining' => null];

    $limit = $type['weekly_limit'] !== null ? (int)$type['weekly_limit'] : null;
    if ($limit === null) return ['used' => 0, 'limit' => null, 'remaining' => null];

    // 이번 주 월요일 00:00:00 ~ 일요일 23:59:59 (KST)
    $now = new DateTime('now', new DateTimeZone('Asia/Seoul'));
    $dayOfWeek = (int)$now->format('N'); // 1=월, 7=일
    $monday = clone $now;
    $monday->modify('-' . ($dayOfWeek - 1) . ' days');
    $monday->setTime(0, 0, 0);
    $sunday = clone $monday;
    $sunday->modify('+6 days');
    $sunday->setTime(23, 59, 59);

    // 순 지급량 (지급 - 차감)으로 계산하여 차감 시 한도 복구
    $stmt = $db->prepare('
        SELECT GREATEST(0, COALESCE(SUM(change_amount), 0))
        FROM junior_reward_log
        WHERE student_id = ? AND reward_type_id = ?
          AND created_at BETWEEN ? AND ?
    ');
    $stmt->execute([$studentId, $type['id'], $monday->format('Y-m-d H:i:s'), $sunday->format('Y-m-d H:i:s')]);
    $used = (int)$stmt->fetchColumn();

    return [
        'used'      => $used,
        'limit'     => $limit,
        'remaining' => max(0, $limit - $used),
    ];
}

/**
 * 보상 수량 변경 + 로그 기록 (주간 한도 검증 포함)
 * @return array ['success' => bool, 'error' => string|null, ...]
 */
function changeReward(
    int $studentId,
    string $rewardCode,
    int $changeAmount,
    string $source,
    ?string $sourceDetail = null,
    ?int $givenBy = null,
    string $givenByType = 'auto'
): array {
    $db = getDB();

    // reward_type 조회
    $stmt = $db->prepare('SELECT id, weekly_limit FROM junior_reward_types WHERE code = ?');
    $stmt->execute([$rewardCode]);
    $rewardType = $stmt->fetch();
    if (!$rewardType) return ['success' => false, 'error' => 'invalid_reward_code'];
    $rewardTypeId = $rewardType['id'];

    // 주간 한도 검증 (양수 변경 + 한도 설정된 카드만)
    if ($changeAmount > 0 && $rewardType['weekly_limit'] !== null) {
        $usage = getWeeklyCardUsage($studentId, $rewardCode);
        if ($changeAmount > $usage['remaining']) {
            $typeName = CARD_TYPES[$rewardCode]['name'] ?? $rewardCode;
            return [
                'success'   => false,
                'error'     => 'weekly_limit_exceeded',
                'message'   => "{$typeName} 주간 한도 초과: 이번 주 남은 수량 {$usage['remaining']}장 (한도 {$usage['limit']}장)",
                'remaining' => $usage['remaining'],
                'limit'     => $usage['limit'],
                'used'      => $usage['used'],
            ];
        }
    }

    // upsert student_rewards
    $stmt = $db->prepare('
        INSERT INTO junior_student_rewards (student_id, reward_type_id, quantity)
        VALUES (?, ?, GREATEST(0, ?))
        ON DUPLICATE KEY UPDATE quantity = GREATEST(0, quantity + ?)
    ');
    $stmt->execute([$studentId, $rewardTypeId, $changeAmount, $changeAmount]);

    // 현재 잔액 조회
    $stmt = $db->prepare('SELECT quantity FROM junior_student_rewards WHERE student_id = ? AND reward_type_id = ?');
    $stmt->execute([$studentId, $rewardTypeId]);
    $balance = (int)$stmt->fetchColumn();

    // reward_log 기록
    $stmt = $db->prepare('
        INSERT INTO junior_reward_log
        (student_id, reward_type_id, change_amount, balance_after, source, source_detail, given_by, given_by_type)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $studentId, $rewardTypeId, $changeAmount, $balance,
        $source, $sourceDetail, $givenBy, $givenByType
    ]);

    return ['success' => true, 'error' => null];
}

/**
 * 꾸준왕 주간 판정: 해당 날짜가 속한 주의 소리과제 완료 횟수를 확인하여 카드 지급/회수
 */
function evaluateSteadyForWeek(int $studentId, string $date, ?int $givenBy = null, string $givenByType = 'auto'): array {
    $db = getDB();

    // 1. 해당 날짜가 속한 주차 조회
    $stmt = $db->prepare('SELECT week_start, week_end, required_count
                          FROM junior_weekly_calendar WHERE week_start <= ? AND week_end >= ?');
    $stmt->execute([$date, $date]);
    $week = $stmt->fetch();
    if (!$week) return ['success' => true, 'action' => 'no_calendar'];

    // 2. 그 주에 sound_homework=1인 날 수
    $stmt = $db->prepare('SELECT COUNT(*) FROM junior_daily_checklist
                          WHERE student_id = ? AND check_date BETWEEN ? AND ? AND sound_homework = 1');
    $stmt->execute([$studentId, $week['week_start'], $week['week_end']]);
    $count = (int)$stmt->fetchColumn();

    // 3. 이 주차의 현재 카드 지급 상태
    $weekKey = $week['week_start'] . '~' . $week['week_end'];
    $stmt = $db->prepare('SELECT id FROM junior_reward_types WHERE code = ?');
    $stmt->execute(['steady']);
    $rewardTypeId = (int)$stmt->fetchColumn();

    $stmt = $db->prepare('SELECT COALESCE(SUM(change_amount), 0)
                          FROM junior_reward_log
                          WHERE student_id = ? AND reward_type_id = ? AND source = ? AND source_detail = ?');
    $stmt->execute([$studentId, $rewardTypeId, 'weekly_steady', $weekKey]);
    $currentAward = (int)$stmt->fetchColumn();

    $qualified = ($count > 0 && $count >= (int)$week['required_count']);

    // 4. 지급 또는 회수
    if ($qualified && $currentAward <= 0) {
        return changeReward($studentId, 'steady', 1, 'weekly_steady', $weekKey, $givenBy, $givenByType);
    } elseif (!$qualified && $currentAward > 0) {
        return changeReward($studentId, 'steady', -1, 'weekly_steady', $weekKey, $givenBy, $givenByType);
    }

    return ['success' => true, 'action' => 'no_change'];
}
