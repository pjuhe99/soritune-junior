<?php
/**
 * 카드/코인 관리 API
 * 카드 조회, 코인 계산, 이력 조회
 */
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json; charset=utf-8');

$action = getAction();
$method = getMethod();

switch ($action) {
    // 카드 종류 목록
    case 'types':
        $db = getDB();
        $stmt = $db->prepare('SELECT * FROM junior_reward_types WHERE is_active = 1 ORDER BY sort_order');
        $stmt->execute();
        jsonSuccess(['types' => $stmt->fetchAll()]);
        break;

    // 학생별 카드 보유 현황
    case 'student_rewards':
        $studentId = (int)($_GET['student_id'] ?? 0);
        if (!$studentId) jsonError('학생 ID가 필요합니다');

        $rewards = getStudentRewards($studentId);
        $totalCoins = getStudentTotalCoins($studentId);

        jsonSuccess([
            'rewards'     => $rewards,
            'total_coins' => $totalCoins,
        ]);
        break;

    // 카드 히스토리 (관리자용 - 전체)
    case 'history':
        $admin = requireAdmin(['coach', 'admin_teacher']);
        $studentId = (int)($_GET['student_id'] ?? 0);
        $rewardCode = trim($_GET['code'] ?? '');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;

        if (!$studentId) jsonError('학생 ID가 필요합니다');

        $db = getDB();

        $where = ['rl.student_id = ?'];
        $params = [$studentId];

        if ($rewardCode) {
            $where[] = 'rt.code = ?';
            $params[] = $rewardCode;
        }

        $whereStr = implode(' AND ', $where);

        $stmt = $db->prepare("
            SELECT rl.*, rt.name_ko, rt.code, rt.coin_value, rt.color
            FROM junior_reward_log rl
            JOIN junior_reward_types rt ON rl.reward_type_id = rt.id
            WHERE {$whereStr}
            ORDER BY rl.created_at DESC
            LIMIT {$limit} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $history = $stmt->fetchAll();

        $stmt = $db->prepare("
            SELECT COUNT(*) FROM junior_reward_log rl
            JOIN junior_reward_types rt ON rl.reward_type_id = rt.id
            WHERE {$whereStr}
        ");
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        jsonSuccess([
            'history' => $history,
            'total'   => $total,
            'page'    => $page,
        ]);
        break;

    // 수동 카드 부여/차감 (관리자)
    case 'adjust':
        if ($method !== 'POST') jsonError('POST만 허용됩니다', 405);
        $admin = requireAdmin(['coach']);
        $input = getJsonInput();

        $studentId = (int)($input['student_id'] ?? 0);
        $rewardCode = trim($input['reward_code'] ?? '');
        $amount = (int)($input['amount'] ?? 0);
        $reason = trim($input['reason'] ?? '');

        if (!$studentId || !$rewardCode || $amount === 0) {
            jsonError('필수 항목을 모두 입력해 주세요');
        }
        if (!$reason) {
            jsonError('수정 사유를 반드시 입력해 주세요');
        }

        // 현재 수량
        $db = getDB();
        $stmt = $db->prepare('
            SELECT COALESCE(sr.quantity, 0) as quantity
            FROM junior_reward_types rt
            LEFT JOIN junior_student_rewards sr ON sr.reward_type_id = rt.id AND sr.student_id = ?
            WHERE rt.code = ?
        ');
        $stmt->execute([$studentId, $rewardCode]);
        $current = $stmt->fetch();
        $oldQty = $current ? (int)$current['quantity'] : 0;

        // 변경
        $result = changeReward($studentId, $rewardCode, $amount, 'manual', $reason, $admin['admin_id'], 'coach');
        if (!$result['success']) {
            if ($result['error'] === 'weekly_limit_exceeded') {
                jsonError($result['message'], 400, [
                    'remaining' => $result['remaining'],
                    'limit'     => $result['limit'],
                ]);
            }
            jsonError('카드 종류를 찾을 수 없습니다');
        }

        $newQty = max(0, $oldQty + $amount);

        // 감사 로그
        auditLog(
            'junior_student_rewards', $studentId, 'update',
            $rewardCode, (string)$oldQty, (string)$newQty,
            $reason, $admin['admin_id'], 'coach', $admin['admin_name']
        );

        jsonSuccess([
            'old_quantity' => $oldQty,
            'new_quantity' => $newQty,
            'total_coins'  => getStudentTotalCoins($studentId),
        ], '카드가 수정되었습니다');
        break;

    // 반별 코인 요약 (전체)
    case 'class_summary':
        $db = getDB();
        $stmt = $db->prepare('
            SELECT c.id, c.display_name,
                   COUNT(DISTINCT cs.student_id) as student_count,
                   COALESCE(SUM(sr.quantity * rt.coin_value), 0) as total_coins
            FROM junior_classes c
            LEFT JOIN junior_class_students cs ON c.id = cs.class_id AND cs.is_primary = 1 AND cs.is_active = 1
            LEFT JOIN junior_students s ON cs.student_id = s.id AND s.is_active = 1
            LEFT JOIN junior_student_rewards sr ON s.id = sr.student_id
            LEFT JOIN junior_reward_types rt ON sr.reward_type_id = rt.id
            WHERE c.is_active = 1
            GROUP BY c.id
            ORDER BY total_coins DESC
        ');
        $stmt->execute();
        jsonSuccess(['classes' => $stmt->fetchAll()]);
        break;

    default:
        jsonError('알 수 없는 요청입니다', 404);
}
