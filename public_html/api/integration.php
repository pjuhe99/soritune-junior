<?php
/**
 * Cross-Site Integration API
 * APP (soritune.com) <-> Junior (j.soritune.com) data exchange
 *
 * Authentication: X-Integration-Key header or ?api_key= query parameter
 * All actions except 'health' require valid integration key
 */
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, X-Integration-Key');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$action = getAction();
$method = getMethod();

// ============================================
// Authentication
// ============================================

/**
 * Validate integration API key from header or query parameter
 */
function validateIntegrationKey(): bool {
    $key = $_SERVER['HTTP_X_INTEGRATION_KEY'] ?? $_GET['api_key'] ?? '';
    if ($key === '') return false;
    $storedKey = getSetting('integration_api_key', '');
    if ($storedKey === '') return false;
    return hash_equals($storedKey, $key);
}

/**
 * Require valid integration key or die with 401
 */
function requireIntegrationAuth(): void {
    if (!validateIntegrationKey()) {
        jsonError('Invalid or missing integration key', 401);
    }
}

// ============================================
// Rate limiting (simple per-IP, per-minute)
// ============================================

/**
 * Basic rate limiting using tmp files
 */
function checkRateLimit(int $maxPerMinute = 60): void {
    $ip = getClientIP();
    $file = sys_get_temp_dir() . '/j_integration_rate_' . md5($ip);
    $now = time();

    $data = ['count' => 0, 'window_start' => $now];
    if (file_exists($file)) {
        $raw = @file_get_contents($file);
        $parsed = $raw ? json_decode($raw, true) : null;
        if ($parsed && isset($parsed['window_start'])) {
            if ($now - $parsed['window_start'] < 60) {
                $data = $parsed;
            }
        }
    }

    $data['count']++;
    if ($data['count'] > $maxPerMinute) {
        jsonError('Rate limit exceeded. Max ' . $maxPerMinute . ' requests per minute.', 429);
    }

    @file_put_contents($file, json_encode($data), LOCK_EX);
}

// ============================================
// Action Router
// ============================================

switch ($action) {

    // ------------------------------------------
    // 1. Health check (no auth)
    // ------------------------------------------
    case 'health':
        jsonSuccess([
            'status'    => 'ok',
            'site'      => 'j.soritune.com',
            'timestamp' => date('c'),
        ]);
        break;

    // ------------------------------------------
    // 2. Get students list (for coaching linkage)
    // ------------------------------------------
    case 'get_students':
        requireIntegrationAuth();
        checkRateLimit();
        if ($method !== 'GET') jsonError('GET method required', 405);

        $classId = (int)($_GET['class_id'] ?? 0);
        $db = getDB();

        $where = 's.is_active = 1';
        $params = [];

        if ($classId > 0) {
            $where .= ' AND cs.class_id = ?';
            $params[] = $classId;
        }

        $stmt = $db->prepare("
            SELECT s.id, s.name, s.phone, s.soritune_id as student_code,
                   c.id as class_id, c.display_name as class_name,
                   s.is_active
            FROM junior_students s
            LEFT JOIN junior_class_students cs ON s.id = cs.student_id AND cs.is_primary = 1 AND cs.is_active = 1
            LEFT JOIN junior_classes c ON cs.class_id = c.id
            WHERE {$where}
            ORDER BY s.name
        ");
        $stmt->execute($params);
        $students = $stmt->fetchAll();

        // Append total_coins for each student
        $result = [];
        foreach ($students as $s) {
            $totalCoins = getStudentTotalCoins((int)$s['id']);
            $result[] = [
                'id'           => (int)$s['id'],
                'name'         => $s['name'],
                'phone'        => $s['phone'],
                'student_code' => $s['student_code'] ?? null,
                'class_name'   => $s['class_name'],
                'total_coins'  => $totalCoins,
                'is_active'    => (int)$s['is_active'],
            ];
        }

        jsonSuccess(['students' => $result, 'count' => count($result)]);
        break;

    // ------------------------------------------
    // 3. Get student detail
    // ------------------------------------------
    case 'get_student_detail':
        requireIntegrationAuth();
        checkRateLimit();
        if ($method !== 'GET') jsonError('GET method required', 405);

        $studentId = (int)($_GET['student_id'] ?? 0);
        if (!$studentId) jsonError('student_id is required');

        $db = getDB();

        // Student basic info
        $stmt = $db->prepare('
            SELECT s.id, s.name, s.phone, s.soritune_id as student_code, s.grade, s.is_active, s.created_at,
                   c.id as class_id, c.display_name as class_name, c.coach_name
            FROM junior_students s
            LEFT JOIN junior_class_students cs ON s.id = cs.student_id AND cs.is_primary = 1 AND cs.is_active = 1
            LEFT JOIN junior_classes c ON cs.class_id = c.id
            WHERE s.id = ?
        ');
        $stmt->execute([$studentId]);
        $student = $stmt->fetch();

        if (!$student) jsonError('Student not found', 404);

        // Rewards
        $rewards = getStudentRewards($studentId);
        $totalCoins = getStudentTotalCoins($studentId);

        // Recent 30-day checklist summary
        $thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));
        $stmt = $db->prepare('
            SELECT COUNT(*) as total_days,
                   COALESCE(SUM(zoom_attendance), 0) as zoom_total,
                   COALESCE(SUM(CASE WHEN posture_king > 0 THEN 1 ELSE 0 END), 0) as posture_days,
                   COALESCE(SUM(sound_homework), 0) as homework_total,
                   COALESCE(SUM(band_mission), 0) as band_total,
                   COALESCE(SUM(leader_king), 0) as leader_total
            FROM junior_daily_checklist
            WHERE student_id = ? AND check_date >= ?
        ');
        $stmt->execute([$studentId, $thirtyDaysAgo]);
        $checklistSummary = $stmt->fetch();

        jsonSuccess([
            'student' => [
                'id'           => (int)$student['id'],
                'name'         => $student['name'],
                'phone'        => $student['phone'],
                'student_code' => $student['student_code'] ?? null,
                'grade'        => $student['grade'] ?? null,
                'is_active'    => (int)$student['is_active'],
                'created_at'   => $student['created_at'],
            ],
            'class' => [
                'id'         => $student['class_id'] ? (int)$student['class_id'] : null,
                'name'       => $student['class_name'],
                'coach_name' => $student['coach_name'] ?? null,
            ],
            'rewards'           => $rewards,
            'total_coins'       => $totalCoins,
            'checklist_summary' => [
                'period'         => $thirtyDaysAgo . ' ~ ' . date('Y-m-d'),
                'total_days'     => (int)$checklistSummary['total_days'],
                'zoom_total'     => (int)$checklistSummary['zoom_total'],
                'posture_days'   => (int)$checklistSummary['posture_days'],
                'homework_total' => (int)$checklistSummary['homework_total'],
                'band_total'     => (int)$checklistSummary['band_total'],
                'leader_total'   => (int)$checklistSummary['leader_total'],
            ],
        ]);
        break;

    // ------------------------------------------
    // 4. Get student stats (performance)
    // ------------------------------------------
    case 'get_student_stats':
        requireIntegrationAuth();
        checkRateLimit();
        if ($method !== 'GET') jsonError('GET method required', 405);

        $studentId = (int)($_GET['student_id'] ?? 0);
        if (!$studentId) jsonError('student_id is required');

        $db = getDB();

        // Verify student exists
        $stmt = $db->prepare('SELECT id, name FROM junior_students WHERE id = ?');
        $stmt->execute([$studentId]);
        $student = $stmt->fetch();
        if (!$student) jsonError('Student not found', 404);

        // Checklist totals (all time)
        $stmt = $db->prepare('
            SELECT COUNT(*) as total_checklist_days,
                   COALESCE(SUM(zoom_attendance), 0) as zoom_total,
                   COALESCE(SUM(sound_homework), 0) as homework_total,
                   COALESCE(SUM(band_mission), 0) as band_total
            FROM junior_daily_checklist
            WHERE student_id = ?
        ');
        $stmt->execute([$studentId]);
        $stats = $stmt->fetch();

        $totalDays = (int)$stats['total_checklist_days'];
        $zoomTotal = (int)$stats['zoom_total'];
        $homeworkTotal = (int)$stats['homework_total'];
        $bandTotal = (int)$stats['band_total'];

        // Calculate rates (percentage)
        $zoomRate = $totalDays > 0 ? round(($zoomTotal / $totalDays) * 100, 1) : 0;
        $homeworkRate = $totalDays > 0 ? round(($homeworkTotal / $totalDays) * 100, 1) : 0;
        $bandRate = $totalDays > 0 ? round(($bandTotal / $totalDays) * 100, 1) : 0;

        // Total coins
        $totalCoins = getStudentTotalCoins($studentId);

        // Reward breakdown
        $rewards = getStudentRewards($studentId);
        $rewardList = [];
        foreach ($rewards as $r) {
            $rewardList[] = [
                'type'     => $r['code'],
                'name'     => $r['name_ko'] ?? $r['name'] ?? $r['code'],
                'quantity' => (int)$r['quantity'],
            ];
        }

        jsonSuccess([
            'student_id'          => $studentId,
            'student_name'        => $student['name'],
            'total_checklist_days' => $totalDays,
            'zoom_rate'           => $zoomRate,
            'homework_rate'       => $homeworkRate,
            'band_rate'           => $bandRate,
            'total_coins'         => $totalCoins,
            'rewards'             => $rewardList,
        ]);
        break;

    // ------------------------------------------
    // 5. Get classes
    // ------------------------------------------
    case 'get_classes':
        requireIntegrationAuth();
        checkRateLimit();
        if ($method !== 'GET') jsonError('GET method required', 405);

        $db = getDB();
        $stmt = $db->prepare('
            SELECT c.id, c.name, c.display_name,
                   COUNT(cs.id) as student_count,
                   c.is_active
            FROM junior_classes c
            LEFT JOIN junior_class_students cs ON c.id = cs.class_id AND cs.is_active = 1
            WHERE c.is_active = 1
            GROUP BY c.id
            ORDER BY c.sort_order
        ');
        $stmt->execute();
        $classes = $stmt->fetchAll();

        $result = [];
        foreach ($classes as $c) {
            $result[] = [
                'id'            => (int)$c['id'],
                'name'          => $c['display_name'] ?? $c['name'],
                'student_count' => (int)$c['student_count'],
                'is_active'     => (int)$c['is_active'],
            ];
        }

        jsonSuccess(['classes' => $result, 'count' => count($result)]);
        break;

    // ------------------------------------------
    // 6. Get coaches (junior coach/teacher list)
    // ------------------------------------------
    case 'get_coaches':
        requireIntegrationAuth();
        checkRateLimit();
        if ($method !== 'GET') jsonError('GET method required', 405);

        $db = getDB();
        $stmt = $db->prepare('
            SELECT a.id, a.name, a.role, a.is_active
            FROM junior_admins a
            WHERE a.role = ? AND a.is_active = 1
            ORDER BY a.name
        ');
        $stmt->execute(['coach']);
        $coaches = $stmt->fetchAll();

        $result = [];
        foreach ($coaches as $c) {
            $result[] = [
                'id'        => (int)$c['id'],
                'name'      => $c['name'],
                'role'      => $c['role'],
                'is_active' => (int)$c['is_active'],
            ];
        }

        jsonSuccess(['coaches' => $result, 'count' => count($result)]);
        break;

    // ------------------------------------------
    // 7. Get dashboard stats (site-wide)
    // ------------------------------------------
    case 'get_dashboard_stats':
        requireIntegrationAuth();
        checkRateLimit();
        if ($method !== 'GET') jsonError('GET method required', 405);

        $db = getDB();
        $today = date('Y-m-d');

        // Active students
        $stmt = $db->prepare('SELECT COUNT(*) FROM junior_students WHERE is_active = 1');
        $stmt->execute();
        $activeStudents = (int)$stmt->fetchColumn();

        // Active classes
        $stmt = $db->prepare('SELECT COUNT(*) FROM junior_classes WHERE is_active = 1');
        $stmt->execute();
        $activeClasses = (int)$stmt->fetchColumn();

        // Active coaches
        $stmt = $db->prepare('SELECT COUNT(*) FROM junior_admins WHERE role = ? AND is_active = 1');
        $stmt->execute(['coach']);
        $coaches = (int)$stmt->fetchColumn();

        // Today's checklist summary
        $stmt = $db->prepare('
            SELECT COUNT(DISTINCT student_id) as checked_students,
                   COALESCE(SUM(zoom_attendance), 0) as zoom_total,
                   COALESCE(SUM(CASE WHEN posture_king > 0 THEN 1 ELSE 0 END), 0) as posture_count,
                   COALESCE(SUM(sound_homework), 0) as homework_total,
                   COALESCE(SUM(band_mission), 0) as band_total,
                   COALESCE(SUM(leader_king), 0) as leader_total
            FROM junior_daily_checklist
            WHERE check_date = ?
        ');
        $stmt->execute([$today]);
        $todayChecklist = $stmt->fetch();

        jsonSuccess([
            'active_students' => $activeStudents,
            'active_classes'  => $activeClasses,
            'coaches'         => $coaches,
            'today_checklist_summary' => [
                'date'             => $today,
                'checked_students' => (int)$todayChecklist['checked_students'],
                'total_students'   => $activeStudents,
                'completion_rate'  => $activeStudents > 0
                    ? round(((int)$todayChecklist['checked_students'] / $activeStudents) * 100, 1)
                    : 0,
                'zoom_total'       => (int)$todayChecklist['zoom_total'],
                'posture_count'    => (int)$todayChecklist['posture_count'],
                'homework_total'   => (int)$todayChecklist['homework_total'],
                'band_total'       => (int)$todayChecklist['band_total'],
                'leader_total'     => (int)$todayChecklist['leader_total'],
            ],
        ]);
        break;

    // ------------------------------------------
    // 8. Update coach info (receive from APP)
    // ------------------------------------------
    case 'update_coach_info':
        requireIntegrationAuth();
        checkRateLimit();
        if ($method !== 'POST') jsonError('POST method required', 405);

        $input = getJsonInput();
        $coaches = $input['coaches'] ?? [];

        if (!is_array($coaches) || empty($coaches)) {
            jsonError('coaches array is required');
        }

        // Validate each coach entry
        $cleanCoaches = [];
        foreach ($coaches as $coach) {
            if (!isset($coach['id'])) continue;
            $cleanCoaches[] = [
                'id'        => (int)$coach['id'],
                'name'      => trim($coach['name'] ?? ''),
                'grade'     => trim($coach['grade'] ?? ''),
                'disc_type' => trim($coach['disc_type'] ?? ''),
                'status'    => trim($coach['status'] ?? 'active'),
            ];
        }

        if (empty($cleanCoaches)) {
            jsonError('No valid coach data provided');
        }

        // Store as JSON in junior_settings
        $db = getDB();
        $jsonValue = json_encode($cleanCoaches, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // Upsert the setting
        $stmt = $db->prepare('
            INSERT INTO junior_settings (setting_key, setting_value, setting_type, description)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ');
        $stmt->execute([
            'app_coach_data',
            $jsonValue,
            'json',
            'Coach data synced from APP site'
        ]);

        jsonSuccess([
            'updated'    => count($cleanCoaches),
            'updated_at' => date('c'),
        ]);
        break;

    // ------------------------------------------
    // 9. Notify matching (coaching match notification)
    // ------------------------------------------
    case 'notify_matching':
        requireIntegrationAuth();
        checkRateLimit();
        if ($method !== 'POST') jsonError('POST method required', 405);

        $input = getJsonInput();
        $juniorStudentId = (int)($input['junior_student_id'] ?? 0);
        $coachName       = trim($input['coach_name'] ?? '');
        $productCode     = trim($input['product_code'] ?? '');
        $matchedAt       = trim($input['matched_at'] ?? date('c'));

        if (!$juniorStudentId) jsonError('junior_student_id is required');
        if (!$coachName) jsonError('coach_name is required');

        $db = getDB();

        // Verify student exists
        $stmt = $db->prepare('SELECT id, name FROM junior_students WHERE id = ?');
        $stmt->execute([$juniorStudentId]);
        $student = $stmt->fetch();

        if (!$student) jsonError('Student not found', 404);

        // Store matching notification in settings as a log
        // First get existing notifications
        $existingNotifications = getSetting('matching_notifications', []);
        if (!is_array($existingNotifications)) {
            $existingNotifications = [];
        }

        // Prepend new notification (keep last 100)
        array_unshift($existingNotifications, [
            'junior_student_id'   => $juniorStudentId,
            'junior_student_name' => $student['name'],
            'coach_name'          => $coachName,
            'product_code'        => $productCode,
            'matched_at'          => $matchedAt,
            'received_at'         => date('c'),
            'ip'                  => getClientIP(),
        ]);
        $existingNotifications = array_slice($existingNotifications, 0, 100);

        $jsonValue = json_encode($existingNotifications, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $stmt = $db->prepare('
            INSERT INTO junior_settings (setting_key, setting_value, setting_type, description)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ');
        $stmt->execute([
            'matching_notifications',
            $jsonValue,
            'json',
            'Coaching match notifications from APP site'
        ]);

        // Also log it via audit log for traceability
        auditLog(
            'junior_students',
            $juniorStudentId,
            'update',
            'coaching_match',
            null,
            $coachName . ' / ' . $productCode,
            'Coaching match notification from APP: coach=' . $coachName . ', product=' . $productCode,
            null,
            'integration',
            'APP Integration'
        );

        jsonSuccess([
            'received'     => true,
            'student_name' => $student['name'],
            'received_at'  => date('c'),
        ]);
        break;

    // ------------------------------------------
    // 10. Search student by name or phone
    // ------------------------------------------
    case 'search_student':
        requireIntegrationAuth();
        checkRateLimit();
        if ($method !== 'GET') jsonError('GET method required', 405);

        $q = trim($_GET['q'] ?? '');
        if ($q === '') jsonError('Search term (q) is required');
        if (mb_strlen($q) < 2) jsonError('Search term must be at least 2 characters');

        $db = getDB();

        $stmt = $db->prepare('
            SELECT s.id, s.name, s.phone, s.soritune_id as student_code, s.is_active,
                   c.id as class_id, c.display_name as class_name
            FROM junior_students s
            LEFT JOIN junior_class_students cs ON s.id = cs.student_id AND cs.is_primary = 1 AND cs.is_active = 1
            LEFT JOIN junior_classes c ON cs.class_id = c.id
            WHERE s.is_active = 1 AND (s.name LIKE ? OR s.phone LIKE ?)
            ORDER BY s.name
            LIMIT 50
        ');
        $searchTerm = '%' . $q . '%';
        $stmt->execute([$searchTerm, $searchTerm]);
        $students = $stmt->fetchAll();

        $result = [];
        foreach ($students as $s) {
            $result[] = [
                'id'           => (int)$s['id'],
                'name'         => $s['name'],
                'phone'        => $s['phone'],
                'student_code' => $s['student_code'] ?? null,
                'class_id'     => $s['class_id'] ? (int)$s['class_id'] : null,
                'class_name'   => $s['class_name'],
                'is_active'    => (int)$s['is_active'],
            ];
        }

        jsonSuccess(['students' => $result, 'count' => count($result), 'query' => $q]);
        break;

    // ------------------------------------------
    // Default: unknown action
    // ------------------------------------------
    default:
        jsonError('Unknown action: ' . $action, 404);
}
