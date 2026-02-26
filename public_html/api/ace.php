<?php
/**
 * 소리튠 주니어 - ACE 영어 테스트 API
 * 학생 녹음, 코치 평가, 부모 리포트
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// 오디오 저장 경로
define('ACE_UPLOAD_DIR', dirname(dirname(__DIR__)) . '/ace_uploads');

try {
    switch ($action) {

        // ==========================================
        // 학생용 API
        // ==========================================

        case 'student_status':
            $session = requireStudent();
            $studentId = $session['student_id'];
            $db = getDB();

            // 현재 레벨
            $stmt = $db->prepare('SELECT ace_current_level, bravo_current_level FROM junior_students WHERE id = ?');
            $stmt->execute([$studentId]);
            $studentRow = $stmt->fetch();
            $currentLevel = $studentRow['ace_current_level'];
            $bravoCurrentLevel = $studentRow['bravo_current_level'];

            // 모든 평가 결과
            $stmt = $db->prepare('
                SELECT ace_level, result, created_at
                FROM junior_ace_evaluations
                WHERE student_id = ?
                ORDER BY ace_level, created_at DESC
            ');
            $stmt->execute([$studentId]);
            $evaluations = $stmt->fetchAll();

            // 평가 대기 중인 제출(After)이 있는지 확인
            // Before만 제출한 경우(입학)는 차단하지 않음
            $stmt = $db->prepare('
                SELECT COUNT(*) FROM junior_ace_submissions
                WHERE student_id = ? AND status = ? AND role = ?
            ');
            $stmt->execute([$studentId, 'submitted', 'after']);
            $awaitingEval = (int)$stmt->fetchColumn() > 0;

            // Before 녹음(입학) 완료 여부
            $stmt = $db->prepare('
                SELECT COUNT(*) FROM junior_ace_submissions
                WHERE student_id = ? AND ace_level = 1 AND role = ? AND status IN (?, ?)
            ');
            $stmt->execute([$studentId, 'before', 'submitted', 'evaluated']);
            $beforeCompleted = (int)$stmt->fetchColumn() > 0;

            // ACE 항목
            $items = $db->query('SELECT * FROM junior_ace_items ORDER BY ace_level, item_index')->fetchAll();

            // Bravo 레벨별 최신 제출 상태
            $bravoLevelStatus = [];
            if ((int)$currentLevel >= 4) {
                $stmt = $db->prepare('
                    SELECT bravo_level, status, coach_result
                    FROM junior_bravo_submissions
                    WHERE student_id = ?
                    ORDER BY bravo_level, id DESC
                ');
                $stmt->execute([$studentId]);
                $bravoSubs = $stmt->fetchAll();
                foreach ($bravoSubs as $bs) {
                    $lv = (int)$bs['bravo_level'];
                    if (!isset($bravoLevelStatus[$lv])) {
                        $bravoLevelStatus[$lv] = ['status' => $bs['status'], 'coach_result' => $bs['coach_result']];
                    }
                }
            }

            // 테스트 날짜 정보 조회
            $tdStmt = $db->query('SELECT test_type, start_date, end_date FROM junior_test_dates');
            $testDates = [];
            foreach ($tdStmt->fetchAll() as $td) {
                $testDates[$td['test_type']] = ['start_date' => $td['start_date'], 'end_date' => $td['end_date']];
            }

            jsonSuccess([
                'current_level' => $currentLevel ? (int)$currentLevel : null,
                'bravo_current_level' => $bravoCurrentLevel ? (int)$bravoCurrentLevel : null,
                'bravo_level_status' => (object)$bravoLevelStatus,
                'evaluations' => $evaluations,
                'awaiting_evaluation' => $awaitingEval,
                'before_completed' => $beforeCompleted,
                'items' => $items,
                'test_dates' => $testDates,
            ]);

        case 'start_session':
            if ($method !== 'POST') jsonError('POST required', 405);
            $session = requireStudent();
            $studentId = $session['student_id'];
            $db = getDB();

            // 현재 레벨 확인
            $stmt = $db->prepare('SELECT ace_current_level FROM junior_students WHERE id = ?');
            $stmt->execute([$studentId]);
            $currentLevel = $stmt->fetchColumn();

            // 테스트 날짜 가드
            $aceLevel = $currentLevel ? (int)$currentLevel : 1;
            if ($aceLevel >= 1 && $aceLevel <= 3) {
                $testType = 'ace_' . $aceLevel;
                $tdStmt = $db->prepare('SELECT start_date, end_date FROM junior_test_dates WHERE test_type = ?');
                $tdStmt->execute([$testType]);
                $td = $tdStmt->fetch();
                if ($td) {
                    $today = date('Y-m-d');
                    if ($td['start_date'] && $today < $td['start_date']) {
                        jsonError('테스트 기간이 아닙니다. 시작일: ' . $td['start_date']);
                    }
                    if ($td['end_date'] && $today > $td['end_date']) {
                        jsonError('테스트 기간이 종료되었습니다. 종료일: ' . $td['end_date']);
                    }
                }
            }

            // 평가 대기 중인 After 제출이 있으면 차단 (Before만 제출은 허용)
            $stmt = $db->prepare('
                SELECT COUNT(*) FROM junior_ace_submissions
                WHERE student_id = ? AND status = ? AND role = ?
            ');
            $stmt->execute([$studentId, 'submitted', 'after']);
            if ((int)$stmt->fetchColumn() > 0) {
                jsonError('코치 선생님의 평가를 기다리고 있어요. 평가가 완료되면 다시 도전할 수 있습니다.');
            }

            // 미완료(recording) 상태 submission이 있으면 삭제하고 새로 시작
            $stmt = $db->prepare('
                SELECT id FROM junior_ace_submissions
                WHERE student_id = ? AND status = ?
            ');
            $stmt->execute([$studentId, 'recording']);
            $oldSubs = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($oldSubs)) {
                $placeholders = implode(',', array_fill(0, count($oldSubs), '?'));
                // recordings는 CASCADE로 자동 삭제
                $db->prepare("DELETE FROM junior_ace_submissions WHERE id IN ($placeholders)")->execute($oldSubs);

                // 첫 세션(Before)을 완료하지 못한 경우 레벨을 null로 되돌림
                if ((int)$currentLevel === 1) {
                    $stmt = $db->prepare('
                        SELECT COUNT(*) FROM junior_ace_submissions
                        WHERE student_id = ? AND ace_level = 1 AND role = ? AND status IN (?, ?)
                    ');
                    $stmt->execute([$studentId, 'before', 'submitted', 'evaluated']);
                    if ((int)$stmt->fetchColumn() === 0) {
                        $db->prepare('UPDATE junior_students SET ace_current_level = NULL WHERE id = ?')->execute([$studentId]);
                        $currentLevel = null;
                    }
                }
            }

            $db->beginTransaction();
            try {
                $submissions = [];

                if ($currentLevel === null || $currentLevel === false) {
                    // 입학: ACE1 Before만
                    $stmt = $db->prepare('INSERT INTO junior_ace_submissions (student_id, ace_level, role) VALUES (?, 1, ?)');
                    $stmt->execute([$studentId, 'before']);
                    $submissions[] = ['id' => (int)$db->lastInsertId(), 'ace_level' => 1, 'role' => 'before'];

                    // ace_current_level을 1로 설정
                    $db->prepare('UPDATE junior_students SET ace_current_level = 1 WHERE id = ?')->execute([$studentId]);

                } else {
                    $level = (int)$currentLevel;
                    if ($level >= 4) {
                        $db->rollBack();
                        jsonError('모든 ACE 레벨을 완료했습니다');
                    }

                    // 현재 레벨 After
                    $stmt = $db->prepare('INSERT INTO junior_ace_submissions (student_id, ace_level, role) VALUES (?, ?, ?)');
                    $stmt->execute([$studentId, $level, 'after']);
                    $submissions[] = ['id' => (int)$db->lastInsertId(), 'ace_level' => $level, 'role' => 'after'];

                    // 다음 레벨 Before (Bonus) - ACE3가 마지막이므로 3 이하만
                    if ($level < 3) {
                        $nextLevel = $level + 1;
                        $stmt = $db->prepare('INSERT INTO junior_ace_submissions (student_id, ace_level, role) VALUES (?, ?, ?)');
                        $stmt->execute([$studentId, $nextLevel, 'before']);
                        $submissions[] = ['id' => (int)$db->lastInsertId(), 'ace_level' => $nextLevel, 'role' => 'before'];
                    }
                }

                $db->commit();

                // 해당 레벨 항목 조회
                $levels = array_unique(array_column($submissions, 'ace_level'));
                $placeholders = implode(',', array_fill(0, count($levels), '?'));
                $stmt = $db->prepare("SELECT * FROM junior_ace_items WHERE ace_level IN ($placeholders) ORDER BY ace_level, item_index");
                $stmt->execute(array_values($levels));
                $items = $stmt->fetchAll();

                jsonSuccess([
                    'submissions' => $submissions,
                    'items' => $items,
                    'resumed' => false,
                ]);

            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }

        case 'upload_audio':
            if ($method !== 'POST') jsonError('POST required', 405);
            $session = requireStudent();
            $studentId = $session['student_id'];
            $db = getDB();

            $submissionId = (int)($_POST['submission_id'] ?? 0);
            $itemId = (int)($_POST['item_id'] ?? 0);

            if (!$submissionId || !$itemId) jsonError('submission_id와 item_id가 필요합니다');

            // submission 소유권 확인
            $stmt = $db->prepare('SELECT id, ace_level, role, status FROM junior_ace_submissions WHERE id = ? AND student_id = ?');
            $stmt->execute([$submissionId, $studentId]);
            $sub = $stmt->fetch();
            if (!$sub) jsonError('유효하지 않은 submission입니다');
            if ($sub['status'] !== 'recording') jsonError('이미 제출된 세션입니다');

            // 파일 검증
            if (!isset($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
                jsonError('오디오 파일이 필요합니다');
            }

            $file = $_FILES['audio'];
            if ($file['size'] < 1000) jsonError('녹음이 너무 짧습니다. 다시 녹음해주세요.');
            if ($file['size'] > 5 * 1024 * 1024) jsonError('파일 크기는 5MB 이하여야 합니다');

            $mimeType = $file['type'] ?: 'audio/webm';
            if (!str_starts_with($mimeType, 'audio/')) {
                // MIME fallback
                $mimeType = 'audio/webm';
            }

            // item 존재 확인
            $stmt = $db->prepare('SELECT id, item_index FROM junior_ace_items WHERE id = ? AND ace_level = ?');
            $stmt->execute([$itemId, $sub['ace_level']]);
            $item = $stmt->fetch();
            if (!$item) jsonError('유효하지 않은 항목입니다');

            // 확장자 결정
            $ext = match(true) {
                str_contains($mimeType, 'webm') => 'webm',
                str_contains($mimeType, 'ogg') => 'ogg',
                str_contains($mimeType, 'mp4') => 'mp4',
                str_contains($mimeType, 'wav') => 'wav',
                default => 'webm',
            };

            // 저장 경로
            $dir = ACE_UPLOAD_DIR . "/{$studentId}/{$sub['ace_level']}";
            if (!is_dir($dir)) mkdir($dir, 0755, true);

            $fileName = "{$submissionId}_{$item['item_index']}_{$sub['role']}.{$ext}";
            $filePath = "{$studentId}/{$sub['ace_level']}/{$fileName}";
            $fullPath = ACE_UPLOAD_DIR . "/{$filePath}";

            if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
                jsonError('파일 저장에 실패했습니다');
            }

            // DB 저장 (upsert)
            $stmt = $db->prepare('
                INSERT INTO junior_ace_recordings (submission_id, item_id, file_path, file_size, mime_type)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE file_path = VALUES(file_path), file_size = VALUES(file_size), mime_type = VALUES(mime_type), recorded_at = CURRENT_TIMESTAMP, id = LAST_INSERT_ID(id)
            ');
            $stmt->execute([$submissionId, $itemId, $filePath, $file['size'], $mimeType]);
            $recordingId = $db->lastInsertId();

            // 녹음 진행률
            $stmt = $db->prepare('SELECT COUNT(*) FROM junior_ace_recordings WHERE submission_id = ?');
            $stmt->execute([$submissionId]);
            $recorded = (int)$stmt->fetchColumn();

            $stmt = $db->prepare('SELECT COUNT(*) FROM junior_ace_items WHERE ace_level = ?');
            $stmt->execute([$sub['ace_level']]);
            $total = (int)$stmt->fetchColumn();

            jsonSuccess([
                'recording_id' => (int)$recordingId,
                'recorded' => $recorded,
                'total' => $total,
            ]);

        case 'submit':
            if ($method !== 'POST') jsonError('POST required', 405);
            $session = requireStudent();
            $studentId = $session['student_id'];
            $db = getDB();

            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $submissionIds = $input['submission_ids'] ?? [];
            if (empty($submissionIds)) jsonError('submission_ids가 필요합니다');

            $db->beginTransaction();
            try {
                $mainLevel = 0;
                foreach ($submissionIds as $subId) {
                    $subId = (int)$subId;
                    $stmt = $db->prepare('SELECT id, ace_level, role, status FROM junior_ace_submissions WHERE id = ? AND student_id = ?');
                    $stmt->execute([$subId, $studentId]);
                    $sub = $stmt->fetch();
                    if (!$sub) { $db->rollBack(); jsonError('유효하지 않은 submission: ' . $subId); }
                    if ($sub['status'] !== 'recording') continue;

                    // 모든 항목이 녹음되었는지 확인
                    $stmt = $db->prepare('SELECT COUNT(*) FROM junior_ace_recordings WHERE submission_id = ?');
                    $stmt->execute([$subId]);
                    $recorded = (int)$stmt->fetchColumn();

                    $stmt = $db->prepare('SELECT COUNT(*) FROM junior_ace_items WHERE ace_level = ?');
                    $stmt->execute([$sub['ace_level']]);
                    $total = (int)$stmt->fetchColumn();

                    if ($recorded < $total) {
                        $db->rollBack();
                        jsonError("ACE{$sub['ace_level']}의 녹음이 완료되지 않았습니다 ({$recorded}/{$total})");
                    }

                    // 제출 처리
                    $stmt = $db->prepare('UPDATE junior_ace_submissions SET status = ?, submitted_at = NOW() WHERE id = ?');
                    $stmt->execute(['submitted', $subId]);

                    if ($sub['role'] === 'after') {
                        $mainLevel = $sub['ace_level'];
                    }
                    if ($mainLevel === 0) {
                        $mainLevel = $sub['ace_level']; // before-only (입학)
                    }
                }

                // 코인 지급 (도전당 1회, +3)
                $coinResult = changeReward($studentId, 'ace', 1, 'ace_submission', "ACE{$mainLevel} 제출");
                if ($coinResult['success']) {
                    // 제출 세션에 코인 기록
                    foreach ($submissionIds as $subId) {
                        $db->prepare('UPDATE junior_ace_submissions SET coins_awarded = 3 WHERE id = ? AND role = ?')
                           ->execute([(int)$subId, $mainLevel > 0 ? 'after' : 'before']);
                    }
                }

                $db->commit();

                $totalCoins = getStudentTotalCoins($studentId);

                jsonSuccess([
                    'coins_awarded' => 3,
                    'total_coins' => $totalCoins,
                    'message' => "ACE{$mainLevel} 녹음이 제출되었습니다! +3코인",
                ]);

            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }

        case 'audio':
            $recordingId = (int)($_GET['id'] ?? 0);
            $token = trim($_GET['token'] ?? '');

            if (!$recordingId) jsonError('recording id가 필요합니다');

            // 인증: 학생 세션, 관리자 세션, 또는 리포트 토큰
            $authorized = false;

            if ($token) {
                $db = getDB();
                $stmt = $db->prepare('
                    SELECT e.student_id FROM junior_ace_evaluations e
                    WHERE e.report_token = ?
                ');
                $stmt->execute([$token]);
                if ($stmt->fetch()) $authorized = true;
            }

            if (!$authorized && !empty($_COOKIE['JUNIOR_ADMIN_SID'])) {
                // 관리자 세션 체크 (코치/관리쌤)
                startAdminSession();
                if (!empty($_SESSION['admin_id'])) $authorized = true;
            }

            if (!$authorized && !empty($_COOKIE['JUNIOR_STUDENT_SID'])) {
                // 학생 세션 체크
                startStudentSession();
                if (!empty($_SESSION['student_id'])) $authorized = true;
            }

            if (!$authorized) jsonError('인증이 필요합니다', 401);

            $db = getDB();
            $stmt = $db->prepare('SELECT file_path, mime_type FROM junior_ace_recordings WHERE id = ?');
            $stmt->execute([$recordingId]);
            $rec = $stmt->fetch();
            if (!$rec) jsonError('녹음을 찾을 수 없습니다', 404);

            $fullPath = ACE_UPLOAD_DIR . '/' . $rec['file_path'];
            if (!file_exists($fullPath)) jsonError('파일을 찾을 수 없습니다', 404);

            header('Content-Type: ' . $rec['mime_type']);
            header('Content-Length: ' . filesize($fullPath));
            header('Accept-Ranges: bytes');
            header('Cache-Control: private, max-age=3600');
            readfile($fullPath);
            exit;

        // ==========================================
        // 코치용 API
        // ==========================================

        case 'coach_pending':
            $admin = requireAdmin(['coach', 'admin_teacher']);
            $classId = (int)($_GET['class_id'] ?? 0);
            $db = getDB();

            // 코치의 담당 반 확인
            $stmt = $db->prepare('SELECT class_id FROM junior_admin_classes WHERE admin_id = ? AND is_active = 1');
            $stmt->execute([$admin['admin_id']]);
            $myClassIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if ($classId && !in_array($classId, $myClassIds)) {
                jsonError('접근 권한이 없는 반입니다');
            }
            $targetClasses = $classId ? [$classId] : $myClassIds;
            if (empty($targetClasses)) jsonSuccess(['students' => []]);

            $placeholders = implode(',', array_fill(0, count($targetClasses), '?'));

            // submitted 상태의 학생 목록
            $stmt = $db->prepare("
                SELECT DISTINCT s.id as student_id, s.name as student_name,
                       c.id as class_id, c.display_name as class_name, c.color as class_color,
                       sub.ace_level, sub.role, sub.submitted_at
                FROM junior_ace_submissions sub
                JOIN junior_students s ON sub.student_id = s.id
                JOIN junior_class_students cs ON cs.student_id = s.id AND cs.is_active = 1 AND cs.is_primary = 1
                JOIN junior_classes c ON cs.class_id = c.id
                WHERE sub.status = 'submitted'
                  AND sub.role = 'after'
                  AND cs.class_id IN ($placeholders)
                  AND NOT EXISTS (
                      SELECT 1 FROM junior_ace_evaluations e
                      WHERE e.student_id = sub.student_id
                        AND e.ace_level = sub.ace_level
                        AND e.after_submission_id = sub.id
                  )
                ORDER BY sub.submitted_at ASC
            ");
            $stmt->execute($targetClasses);
            $pending = $stmt->fetchAll();

            // 입학 녹음(ACE1 Before) 완료 표시
            // ACE1 After가 이미 제출/평가된 경우 제외 (보너스 Before는 표시 안 함)
            $stmt = $db->prepare("
                SELECT DISTINCT s.id as student_id, s.name as student_name,
                       c.id as class_id, c.display_name as class_name, c.color as class_color,
                       sub.ace_level, sub.role, sub.submitted_at
                FROM junior_ace_submissions sub
                JOIN junior_students s ON sub.student_id = s.id
                JOIN junior_class_students cs ON cs.student_id = s.id AND cs.is_active = 1 AND cs.is_primary = 1
                JOIN junior_classes c ON cs.class_id = c.id
                WHERE sub.status IN ('submitted','evaluated')
                  AND sub.role = 'before'
                  AND sub.ace_level = 1
                  AND cs.class_id IN ($placeholders)
                  AND NOT EXISTS (
                      SELECT 1 FROM junior_ace_submissions a
                      WHERE a.student_id = sub.student_id
                        AND a.ace_level = 1
                        AND a.role = 'after'
                        AND a.status IN ('submitted','evaluated')
                  )
                ORDER BY sub.submitted_at ASC
            ");
            $stmt->execute($targetClasses);
            $beforeOnly = $stmt->fetchAll();

            // === 요약 통계 ===
            // 반 전체 학생 수
            $stmt = $db->prepare("
                SELECT COUNT(DISTINCT cs.student_id)
                FROM junior_class_students cs
                WHERE cs.class_id IN ($placeholders) AND cs.is_active = 1
            ");
            $stmt->execute($targetClasses);
            $totalStudents = (int)$stmt->fetchColumn();

            // Before 녹음 완료 인원
            $stmt = $db->prepare("
                SELECT COUNT(DISTINCT sub.student_id)
                FROM junior_ace_submissions sub
                JOIN junior_class_students cs ON cs.student_id = sub.student_id AND cs.is_active = 1 AND cs.is_primary = 1
                WHERE sub.ace_level = 1 AND sub.role = 'before' AND sub.status IN ('submitted','evaluated')
                  AND cs.class_id IN ($placeholders)
            ");
            $stmt->execute($targetClasses);
            $beforeCount = (int)$stmt->fetchColumn();

            // ACE 레벨별 통과 인원 (ace_current_level 기준: 2=ACE1통과, 3=ACE1+2통과, 4=전부통과)
            $stmt = $db->prepare("
                SELECT s.ace_current_level, COUNT(*) as cnt
                FROM junior_students s
                JOIN junior_class_students cs ON cs.student_id = s.id AND cs.is_active = 1 AND cs.is_primary = 1
                WHERE cs.class_id IN ($placeholders) AND s.ace_current_level IS NOT NULL
                GROUP BY s.ace_current_level
            ");
            $stmt->execute($targetClasses);
            $levelCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // level => count

            // 누적 통과: ACE1 통과 = level>=2, ACE2 통과 = level>=3, ACE3 통과 = level>=4
            $ace1Pass = 0; $ace2Pass = 0; $ace3Pass = 0;
            foreach ($levelCounts as $lv => $cnt) {
                $lv = (int)$lv;
                if ($lv >= 2) $ace1Pass += $cnt;
                if ($lv >= 3) $ace2Pass += $cnt;
                if ($lv >= 4) $ace3Pass += $cnt;
            }

            // 레벨별 재시도 인원 (retry 평가 후 아직 재통과 못한 학생)
            $stmt = $db->prepare("
                SELECT e.ace_level, COUNT(DISTINCT e.student_id) as cnt
                FROM junior_ace_evaluations e
                JOIN junior_class_students cs ON cs.student_id = e.student_id AND cs.is_active = 1 AND cs.is_primary = 1
                WHERE cs.class_id IN ($placeholders)
                  AND e.result = 'retry'
                  AND NOT EXISTS (
                      SELECT 1 FROM junior_ace_evaluations e2
                      WHERE e2.student_id = e.student_id AND e2.ace_level = e.ace_level AND e2.result = 'pass'
                  )
                GROUP BY e.ace_level
            ");
            $stmt->execute($targetClasses);
            $retryCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            $summary = [
                'total_students' => $totalStudents,
                'before_completed' => $beforeCount,
                'ace1_pass' => $ace1Pass,
                'ace2_pass' => $ace2Pass,
                'ace3_pass' => $ace3Pass,
                'ace1_retry' => (int)($retryCounts[1] ?? 0),
                'ace2_retry' => (int)($retryCounts[2] ?? 0),
                'ace3_retry' => (int)($retryCounts[3] ?? 0),
            ];

            jsonSuccess([
                'summary' => $summary,
                'pending' => $pending,
                'before_submitted' => $beforeOnly,
            ]);

        case 'coach_student_detail':
            $admin = requireAdmin(['coach', 'admin_teacher']);
            $studentIdParam = (int)($_GET['student_id'] ?? 0);
            if (!$studentIdParam) jsonError('student_id가 필요합니다');
            $db = getDB();

            // 학생 정보
            $stmt = $db->prepare('
                SELECT s.id, s.name, s.ace_current_level, c.display_name as class_name, c.color as class_color
                FROM junior_students s
                JOIN junior_class_students cs ON cs.student_id = s.id AND cs.is_primary = 1 AND cs.is_active = 1
                JOIN junior_classes c ON cs.class_id = c.id
                WHERE s.id = ?
            ');
            $stmt->execute([$studentIdParam]);
            $student = $stmt->fetch();
            if (!$student) jsonError('학생을 찾을 수 없습니다');

            // 모든 submission
            $stmt = $db->prepare('
                SELECT id, ace_level, role, status, submitted_at, created_at
                FROM junior_ace_submissions
                WHERE student_id = ?
                ORDER BY ace_level, role, created_at DESC
            ');
            $stmt->execute([$studentIdParam]);
            $submissions = $stmt->fetchAll();

            // 모든 녹음
            $subIds = array_column($submissions, 'id');
            $recordings = [];
            if (!empty($subIds)) {
                $ph = implode(',', array_fill(0, count($subIds), '?'));
                $stmt = $db->prepare("
                    SELECT r.id, r.submission_id, r.item_id, r.duration_ms,
                           i.item_text, i.item_ipa, i.item_index, i.ace_level, i.item_type
                    FROM junior_ace_recordings r
                    JOIN junior_ace_items i ON r.item_id = i.id
                    WHERE r.submission_id IN ($ph)
                    ORDER BY i.ace_level, i.item_index
                ");
                $stmt->execute($subIds);
                $recordings = $stmt->fetchAll();
            }

            // 평가 이력
            $stmt = $db->prepare('
                SELECT * FROM junior_ace_evaluations
                WHERE student_id = ?
                ORDER BY ace_level, created_at DESC
            ');
            $stmt->execute([$studentIdParam]);
            $evaluations = $stmt->fetchAll();

            jsonSuccess([
                'student' => $student,
                'submissions' => $submissions,
                'recordings' => $recordings,
                'evaluations' => $evaluations,
            ]);

        case 'comment_templates':
            requireAdmin(['coach', 'admin_teacher']);
            $db = getDB();
            $type = $_GET['type'] ?? '';

            if ($type) {
                $stmt = $db->prepare('SELECT * FROM junior_ace_comment_templates WHERE comment_type = ? ORDER BY sort_order');
                $stmt->execute([$type]);
            } else {
                $stmt = $db->query('SELECT * FROM junior_ace_comment_templates ORDER BY comment_type, sort_order');
            }

            jsonSuccess(['templates' => $stmt->fetchAll()]);

        case 'evaluate':
            if ($method !== 'POST') jsonError('POST required', 405);
            $admin = requireAdmin(['coach', 'admin_teacher']);
            $db = getDB();

            $input = json_decode(file_get_contents('php://input'), true);
            $studentIdParam = (int)($input['student_id'] ?? 0);
            $aceLevel = (int)($input['ace_level'] ?? 0);
            $result = $input['result'] ?? '';
            $commentType = $input['comment_type'] ?? null;
            $commentText = $input['comment_text'] ?? null;

            if (!$studentIdParam || !$aceLevel || !in_array($result, ['pass', 'retry'])) {
                jsonError('student_id, ace_level, result(pass/retry)가 필요합니다');
            }

            // Before/After submission 찾기
            $stmt = $db->prepare('
                SELECT id, role FROM junior_ace_submissions
                WHERE student_id = ? AND ace_level = ? AND status IN (?, ?)
                ORDER BY role, created_at DESC
            ');
            $stmt->execute([$studentIdParam, $aceLevel, 'submitted', 'evaluated']);
            $subs = $stmt->fetchAll();

            $beforeSubId = null;
            $afterSubId = null;
            foreach ($subs as $s) {
                if ($s['role'] === 'before' && !$beforeSubId) $beforeSubId = $s['id'];
                if ($s['role'] === 'after' && !$afterSubId) $afterSubId = $s['id'];
            }

            if (!$beforeSubId || !$afterSubId) {
                jsonError('Before/After 녹음이 모두 필요합니다');
            }

            $db->beginTransaction();
            try {
                // 리포트 토큰 생성
                $reportToken = bin2hex(random_bytes(32));

                // 평가 저장
                $stmt = $db->prepare('
                    INSERT INTO junior_ace_evaluations
                    (student_id, ace_level, before_submission_id, after_submission_id, result, comment_type, comment_text, evaluated_by, report_token, report_sent_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ');
                $stmt->execute([
                    $studentIdParam, $aceLevel, $beforeSubId, $afterSubId,
                    $result, $commentType, $commentText, $admin['admin_id'], $reportToken
                ]);
                $evalId = (int)$db->lastInsertId();

                // submission 상태 업데이트
                $db->prepare('UPDATE junior_ace_submissions SET status = ? WHERE id IN (?, ?)')
                   ->execute(['evaluated', $beforeSubId, $afterSubId]);

                // PASS 시 레벨 업
                if ($result === 'pass') {
                    $nextLevel = $aceLevel + 1; // 4 = 전체 완료
                    $db->prepare('UPDATE junior_students SET ace_current_level = ? WHERE id = ? AND (ace_current_level IS NULL OR ace_current_level <= ?)')
                       ->execute([$nextLevel, $studentIdParam, $aceLevel]);
                }

                $db->commit();

                jsonSuccess([
                    'evaluation_id' => $evalId,
                    'report_token' => $reportToken,
                    'message' => $result === 'pass'
                        ? "ACE{$aceLevel} PASS 처리되었습니다!"
                        : "ACE{$aceLevel} 재도전으로 처리되었습니다.",
                ]);

            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }

        case 'send_report':
            if ($method !== 'POST') jsonError('POST required', 405);
            $admin = requireAdmin(['coach', 'admin_teacher']);
            $db = getDB();

            $input = json_decode(file_get_contents('php://input'), true);
            $evaluationId = (int)($input['evaluation_id'] ?? 0);
            if (!$evaluationId) jsonError('evaluation_id가 필요합니다');

            // 평가 확인
            $stmt = $db->prepare('SELECT id, report_token FROM junior_ace_evaluations WHERE id = ?');
            $stmt->execute([$evaluationId]);
            $eval = $stmt->fetch();
            if (!$eval) jsonError('평가를 찾을 수 없습니다');

            // report_sent_at 업데이트
            $db->prepare('UPDATE junior_ace_evaluations SET report_sent_at = NOW() WHERE id = ?')
               ->execute([$evaluationId]);

            $reportUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'j.soritune.com') . '/ace-report/?token=' . $eval['report_token'];

            jsonSuccess([
                'report_url' => $reportUrl,
                'message' => '리포트 URL이 생성되었습니다.',
            ]);

        // ==========================================
        // 부모 리포트 API
        // ==========================================

        case 'report':
            $token = trim($_GET['token'] ?? '');
            if (!$token) jsonError('토큰이 필요합니다');

            $db = getDB();
            $stmt = $db->prepare('
                SELECT e.*, s.name as student_name, s.ace_current_level,
                       c.display_name as class_name, c.color as class_color,
                       a.name as coach_name
                FROM junior_ace_evaluations e
                JOIN junior_students s ON e.student_id = s.id
                JOIN junior_class_students cs ON cs.student_id = s.id AND cs.is_primary = 1 AND cs.is_active = 1
                JOIN junior_classes c ON cs.class_id = c.id
                LEFT JOIN junior_admins a ON e.evaluated_by = a.id
                WHERE e.report_token = ?
            ');
            $stmt->execute([$token]);
            $report = $stmt->fetch();
            if (!$report) jsonError('유효하지 않은 리포트입니다', 404);

            // Before/After 녹음 데이터
            $stmt = $db->prepare('
                SELECT r.id as recording_id, r.submission_id, r.duration_ms,
                       i.item_text, i.item_ipa, i.item_index, i.item_type, i.ace_level,
                       sub.role
                FROM junior_ace_recordings r
                JOIN junior_ace_items i ON r.item_id = i.id
                JOIN junior_ace_submissions sub ON r.submission_id = sub.id
                WHERE r.submission_id IN (?, ?)
                ORDER BY i.item_index
            ');
            $stmt->execute([$report['before_submission_id'], $report['after_submission_id']]);
            $recordings = $stmt->fetchAll();

            // 전체 ACE 현황
            $stmt = $db->prepare('
                SELECT ace_level, result, created_at
                FROM junior_ace_evaluations
                WHERE student_id = ?
                ORDER BY ace_level
            ');
            $stmt->execute([$report['student_id']]);
            $allEvals = $stmt->fetchAll();

            jsonSuccess([
                'student_name' => $report['student_name'],
                'class_name' => $report['class_name'],
                'class_color' => $report['class_color'],
                'coach_name' => $report['coach_name'],
                'ace_level' => (int)$report['ace_level'],
                'result' => $report['result'],
                'comment_type' => $report['comment_type'],
                'comment_text' => $report['comment_text'],
                'evaluated_at' => $report['created_at'],
                'recordings' => $recordings,
                'all_evaluations' => $allEvals,
                'current_level' => $report['ace_current_level'],
                'token' => $token,
            ]);

        case 'certificate_data':
            $token = trim($_GET['token'] ?? '');
            $db = getDB();

            if ($token) {
                $stmt = $db->prepare('
                    SELECT e.student_id FROM junior_ace_evaluations e WHERE e.report_token = ?
                ');
                $stmt->execute([$token]);
                $row = $stmt->fetch();
                if (!$row) jsonError('유효하지 않은 토큰입니다', 404);
                $studentIdParam = $row['student_id'];
            } else {
                $session = requireStudent();
                $studentIdParam = $session['student_id'];
            }

            // 학생 정보
            $stmt = $db->prepare('
                SELECT s.name, s.ace_current_level, c.display_name as class_name,
                       c.coach_name
                FROM junior_students s
                JOIN junior_class_students cs ON cs.student_id = s.id AND cs.is_primary = 1 AND cs.is_active = 1
                JOIN junior_classes c ON cs.class_id = c.id
                WHERE s.id = ?
            ');
            $stmt->execute([$studentIdParam]);
            $student = $stmt->fetch();
            if (!$student) jsonError('학생을 찾을 수 없습니다', 404);

            // PASS된 레벨
            $stmt = $db->prepare('
                SELECT ace_level, created_at
                FROM junior_ace_evaluations
                WHERE student_id = ? AND result = ?
                ORDER BY ace_level
            ');
            $stmt->execute([$studentIdParam, 'pass']);
            $passed = $stmt->fetchAll();

            jsonSuccess([
                'student_name' => $student['name'],
                'class_name' => $student['class_name'],
                'coach_name' => $student['coach_name'],
                'current_level' => $student['ace_current_level'],
                'passed_levels' => $passed,
            ]);

        default:
            jsonError('Unknown action: ' . $action, 404);
    }
} catch (Exception $e) {
    error_log('ACE API Error: ' . $e->getMessage());
    jsonError('서버 오류가 발생했습니다: ' . $e->getMessage(), 500);
}
