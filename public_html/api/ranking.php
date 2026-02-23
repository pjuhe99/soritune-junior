<?php
/**
 * 랭킹 API (공개 - 인증 불필요)
 * Dense Ranking 방식
 */
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json; charset=utf-8');

// 비로그인 시 이름 마스킹 (개인정보 보호)
$isLoggedIn = false;
if (isset($_COOKIE['JUNIOR_STUDENT_SID'])) {
    $session = getStudentSession();
    $isLoggedIn = ($session !== null);
}

function maskNameForPublic(string $name): string {
    $len = mb_strlen($name, 'UTF-8');
    if ($len <= 1) return $name;
    return mb_substr($name, 0, 1, 'UTF-8') . str_repeat('ㅇ', $len - 1);
}

$action = getAction();

switch ($action) {
    // 전체 코인 랭킹
    case 'overall':
        $db = getDB();
        $limit = min((int)($_GET['limit'] ?? 100), 500);
        $offset = max((int)($_GET['offset'] ?? 0), 0);

        $stmt = $db->prepare('
            SELECT s.id, s.name,
                   c.display_name as class_name,
                   s.ace_current_level, s.bravo_current_level,
                   COALESCE(SUM(sr.quantity * rt.coin_value), 0) as total_coins
            FROM junior_students s
            LEFT JOIN junior_class_students cs ON s.id = cs.student_id AND cs.is_primary = 1 AND cs.is_active = 1
            LEFT JOIN junior_classes c ON cs.class_id = c.id
            LEFT JOIN junior_student_rewards sr ON s.id = sr.student_id
            LEFT JOIN junior_reward_types rt ON sr.reward_type_id = rt.id
            WHERE s.is_active = 1
            GROUP BY s.id
            ORDER BY total_coins DESC, s.name ASC
        ');
        $stmt->execute();
        $all = $stmt->fetchAll();

        // Dense ranking 계산
        $rankings = [];
        $rank = 0;
        $prevCoins = -1;
        foreach ($all as $i => $row) {
            if ($row['total_coins'] != $prevCoins) {
                $rank++;
                $prevCoins = $row['total_coins'];
            }
            $rankings[] = [
                'rank'       => $rank,
                'student_id' => $row['id'],
                'name'       => $row['name'],
                'class_name' => $row['class_name'],
                'total_coins' => (int)$row['total_coins'],
                'ace_current_level'   => $row['ace_current_level'] ? (int)$row['ace_current_level'] : null,
                'bravo_current_level' => $row['bravo_current_level'] ? (int)$row['bravo_current_level'] : null,
            ];
        }

        $total = count($rankings);
        $totalCoins = array_sum(array_column($rankings, 'total_coins'));
        $paged = array_slice($rankings, $offset, $limit);

        // 비로그인: 이름 마스킹
        if (!$isLoggedIn) {
            $paged = array_map(function($r) {
                $r['name'] = maskNameForPublic($r['name']);
                return $r;
            }, $paged);
        }

        jsonSuccess([
            'rankings'       => $paged,
            'total'          => $total,
            'total_students' => $total,
            'total_coins'    => $totalCoins,
            'offset'         => $offset,
            'limit'          => $limit,
        ]);
        break;

    // 반별 랭킹
    case 'class':
        $classId = (int)($_GET['class_id'] ?? 0);
        if (!$classId) jsonError('반을 골라줘!');

        $db = getDB();

        // 반 정보
        $stmt = $db->prepare('SELECT id, display_name, coach_name FROM junior_classes WHERE id = ? AND is_active = 1');
        $stmt->execute([$classId]);
        $classInfo = $stmt->fetch();
        if (!$classInfo) jsonError('🔧 잠깐 문제가 생겼어.');

        $stmt = $db->prepare('
            SELECT s.id, s.name,
                   COALESCE(SUM(sr.quantity * rt.coin_value), 0) as total_coins
            FROM junior_students s
            JOIN junior_class_students cs ON s.id = cs.student_id AND cs.is_primary = 1 AND cs.is_active = 1
            LEFT JOIN junior_student_rewards sr ON s.id = sr.student_id
            LEFT JOIN junior_reward_types rt ON sr.reward_type_id = rt.id
            WHERE cs.class_id = ? AND s.is_active = 1
            GROUP BY s.id
            ORDER BY total_coins DESC, s.name ASC
        ');
        $stmt->execute([$classId]);
        $students = $stmt->fetchAll();

        $rankings = [];
        $rank = 0;
        $prevCoins = -1;
        foreach ($students as $row) {
            if ($row['total_coins'] != $prevCoins) {
                $rank++;
                $prevCoins = $row['total_coins'];
            }
            $rankings[] = [
                'rank'        => $rank,
                'student_id'  => $row['id'],
                'name'        => $row['name'],
                'total_coins' => (int)$row['total_coins'],
            ];
        }

        // 비로그인: 이름 마스킹
        if (!$isLoggedIn) {
            $rankings = array_map(function($r) {
                $r['name'] = maskNameForPublic($r['name']);
                return $r;
            }, $rankings);
        }

        jsonSuccess([
            'class'    => $classInfo,
            'rankings' => $rankings,
        ]);
        break;

    default:
        jsonError('앗, 뭔가 이상해! 다시 해보자.', 404);
}
