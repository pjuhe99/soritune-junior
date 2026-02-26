<?php
/**
 * 소리튠 주니어 - Bravo 테스트 API
 * 학생 퀴즈/녹음/블록, 코치 확인
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

define('BRAVO_UPLOAD_DIR', dirname(dirname(__DIR__)) . '/bravo_uploads');
define('BRAVO_PASS_RATE', 0.6);

// 밴드 정보
define('BRAVO_BANDS', [
    'yellow' => ['name' => 'Yellow반', 'emoji' => '🟡', 'color' => '#F59E0B', 'levels' => [1, 2, 3]],
    'green'  => ['name' => 'Green반',  'emoji' => '🟢', 'color' => '#10B981', 'levels' => [4, 5, 6]],
    'blue'   => ['name' => 'Blue반',   'emoji' => '🔵', 'color' => '#3B82F6', 'levels' => [7, 8, 9]],
]);

// 레벨 메타
define('BRAVO_LEVELS', [
    1 => ['bravo' => 'Bravo Jr 1', 'level' => 'Level aa', 'title' => '파닉스 마스터',    'band' => 'yellow', 'color' => '#F59E0B', 'isPhonics' => true],
    2 => ['bravo' => 'Bravo Jr 2', 'level' => 'Level a',  'title' => '소리블록 기초',     'band' => 'yellow', 'color' => '#FB923C', 'isPhonics' => false],
    3 => ['bravo' => 'Bravo Jr 3', 'level' => 'Level b',  'title' => '소리블록 확장',     'band' => 'yellow', 'color' => '#EA580C', 'isPhonics' => false],
    4 => ['bravo' => 'Bravo Jr 4', 'level' => 'Level C',  'title' => '기초 문장 패턴',    'band' => 'green',  'color' => '#10B981', 'isPhonics' => false],
    5 => ['bravo' => 'Bravo Jr 5', 'level' => 'Level D',  'title' => '복합 문장 패턴',    'band' => 'green',  'color' => '#059669', 'isPhonics' => false],
    6 => ['bravo' => 'Bravo Jr 6', 'level' => 'Level E',  'title' => '스토리 & 표현',     'band' => 'green',  'color' => '#047857', 'isPhonics' => false],
]);

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
            $row = $stmt->fetch();
            $aceLevel = $row['ace_current_level'] ? (int)$row['ace_current_level'] : null;
            $bravoLevel = $row['bravo_current_level'] ? (int)$row['bravo_current_level'] : null;

            // ACE 3 통과 여부 (ace_current_level >= 4)
            $aceCompleted = $aceLevel !== null && $aceLevel >= 4;

            // 각 레벨별 최신 제출 상태
            $stmt = $db->prepare('
                SELECT bravo_level, status, auto_result, coach_result, quiz_correct, quiz_total, block_correct, block_total, submitted_at, confirmed_at
                FROM junior_bravo_submissions
                WHERE student_id = ?
                ORDER BY bravo_level, created_at DESC
            ');
            $stmt->execute([$studentId]);
            $allSubs = $stmt->fetchAll();

            // 레벨별 최신 1개만
            $levelStatus = [];
            foreach ($allSubs as $sub) {
                $lv = (int)$sub['bravo_level'];
                if (!isset($levelStatus[$lv])) {
                    $levelStatus[$lv] = $sub;
                }
            }

            // 제출 대기 중인지 (submitted but not confirmed)
            $awaitingConfirm = false;
            foreach ($levelStatus as $ls) {
                if ($ls['status'] === 'submitted') {
                    $awaitingConfirm = true;
                    break;
                }
            }

            jsonSuccess([
                'ace_completed' => $aceCompleted,
                'bravo_current_level' => $bravoLevel,
                'level_status' => $levelStatus,
                'awaiting_confirm' => $awaitingConfirm,
                'levels_meta' => BRAVO_LEVELS,
                'bands' => BRAVO_BANDS,
            ]);

        case 'get_items':
            $session = requireStudent();
            $bravoLevel = (int)($_GET['level'] ?? 0);
            if ($bravoLevel < 1 || $bravoLevel > 9) jsonError('유효하지 않은 레벨');

            $db = getDB();
            $stmt = $db->prepare('
                SELECT id, section_type, item_index, item_data
                FROM junior_bravo_items
                WHERE bravo_level = ?
                ORDER BY section_type, item_index
            ');
            $stmt->execute([$bravoLevel]);
            $items = $stmt->fetchAll();

            // JSON decode item_data
            foreach ($items as &$item) {
                $item['item_data'] = json_decode($item['item_data'], true);
            }

            $meta = BRAVO_LEVELS[$bravoLevel] ?? null;

            jsonSuccess([
                'level' => $bravoLevel,
                'meta' => $meta,
                'items' => $items,
            ]);

        case 'start_session':
            if ($method !== 'POST') jsonError('POST required', 405);
            $session = requireStudent();
            $studentId = $session['student_id'];
            $db = getDB();

            $input = getJsonInput();
            $bravoLevel = (int)($input['level'] ?? 0);
            if ($bravoLevel < 1 || $bravoLevel > 9) jsonError('유효하지 않은 레벨');

            // 테스트 날짜 가드
            if ($bravoLevel >= 1 && $bravoLevel <= 6) {
                $testType = 'bravo_' . $bravoLevel;
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

            // ACE 3 통과 확인
            $stmt = $db->prepare('SELECT ace_current_level, bravo_current_level FROM junior_students WHERE id = ?');
            $stmt->execute([$studentId]);
            $student = $stmt->fetch();
            if (!$student || (int)($student['ace_current_level'] ?? 0) < 4) {
                jsonError('ACE 3를 먼저 통과해야 합니다');
            }

            $currentBravo = $student['bravo_current_level'] ? (int)$student['bravo_current_level'] : 1;

            // 해당 레벨 도전 가능한지 확인
            if ($bravoLevel > $currentBravo) {
                jsonError('이전 레벨을 먼저 통과해야 합니다');
            }

            // 기존 in_progress 정리
            $db->prepare('DELETE FROM junior_bravo_submissions WHERE student_id = ? AND bravo_level = ? AND status = ?')
               ->execute([$studentId, $bravoLevel, 'in_progress']);

            // 이미 제출 대기 중이면 차단
            $stmt = $db->prepare('SELECT COUNT(*) FROM junior_bravo_submissions WHERE student_id = ? AND status = ?');
            $stmt->execute([$studentId, 'submitted']);
            if ((int)$stmt->fetchColumn() > 0) {
                jsonError('이전 제출의 코치 확인을 기다리고 있습니다');
            }

            // 새 세션 생성
            $stmt = $db->prepare('INSERT INTO junior_bravo_submissions (student_id, bravo_level) VALUES (?, ?)');
            $stmt->execute([$studentId, $bravoLevel]);
            $submissionId = (int)$db->lastInsertId();

            jsonSuccess([
                'submission_id' => $submissionId,
                'bravo_level' => $bravoLevel,
            ]);

        case 'save_answer':
            if ($method !== 'POST') jsonError('POST required', 405);
            $session = requireStudent();
            $studentId = $session['student_id'];
            $db = getDB();

            $input = getJsonInput();
            $submissionId = (int)($input['submission_id'] ?? 0);
            $itemId = (int)($input['item_id'] ?? 0);
            $answer = $input['answer'] ?? null;

            if (!$submissionId || !$itemId) jsonError('submission_id, item_id 필요');

            // 제출 확인
            $stmt = $db->prepare('SELECT id, bravo_level, status FROM junior_bravo_submissions WHERE id = ? AND student_id = ?');
            $stmt->execute([$submissionId, $studentId]);
            $sub = $stmt->fetch();
            if (!$sub || $sub['status'] !== 'in_progress') jsonError('유효하지 않은 세션');

            // 아이템 확인
            $stmt = $db->prepare('SELECT id, section_type, item_data FROM junior_bravo_items WHERE id = ? AND bravo_level = ?');
            $stmt->execute([$itemId, $sub['bravo_level']]);
            $item = $stmt->fetch();
            if (!$item) jsonError('유효하지 않은 문항');

            $itemData = json_decode($item['item_data'], true);
            $sectionType = $item['section_type'];

            // 정답 체크
            $isCorrect = false;
            if ($sectionType === 'quiz') {
                $isCorrect = ($answer === $itemData['a']);
            } elseif ($sectionType === 'block') {
                // answer는 {0: "word", 1: "word", ...} 형태
                if (is_array($answer) && isset($itemData['a'])) {
                    $isCorrect = true;
                    foreach ($itemData['a'] as $i => $correct) {
                        if (!isset($answer[$i]) && !isset($answer[(string)$i])) {
                            $isCorrect = false;
                            break;
                        }
                        $given = $answer[$i] ?? $answer[(string)$i] ?? '';
                        if ($given !== $correct) {
                            $isCorrect = false;
                            break;
                        }
                    }
                }
            }

            // Upsert
            $answerJson = json_encode($answer, JSON_UNESCAPED_UNICODE);
            $stmt = $db->prepare('
                INSERT INTO junior_bravo_answers (submission_id, item_id, answer_data, is_correct)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE answer_data = VALUES(answer_data), is_correct = VALUES(is_correct)
            ');
            $stmt->execute([$submissionId, $itemId, $answerJson, $isCorrect ? 1 : 0]);

            jsonSuccess([
                'is_correct' => $isCorrect,
                'correct_answer' => $itemData['a'],
            ]);

        case 'upload_audio':
            if ($method !== 'POST') jsonError('POST required', 405);
            $session = requireStudent();
            $studentId = $session['student_id'];
            $db = getDB();

            $submissionId = (int)($_POST['submission_id'] ?? 0);
            $itemId = (int)($_POST['item_id'] ?? 0);
            if (!$submissionId || !$itemId) jsonError('submission_id, item_id 필요');

            // 제출 확인
            $stmt = $db->prepare('SELECT id, bravo_level, status FROM junior_bravo_submissions WHERE id = ? AND student_id = ?');
            $stmt->execute([$submissionId, $studentId]);
            $sub = $stmt->fetch();
            if (!$sub || $sub['status'] !== 'in_progress') jsonError('유효하지 않은 세션');

            // 아이템 확인 (sentence 또는 phonics만)
            $stmt = $db->prepare('SELECT id, section_type, item_index FROM junior_bravo_items WHERE id = ? AND bravo_level = ? AND section_type IN (?, ?)');
            $stmt->execute([$itemId, $sub['bravo_level'], 'sentence', 'phonics']);
            $item = $stmt->fetch();
            if (!$item) jsonError('유효하지 않은 문항');

            // 파일 확인
            if (empty($_FILES['audio'])) jsonError('오디오 파일이 필요합니다');
            $file = $_FILES['audio'];
            if ($file['error'] !== UPLOAD_ERR_OK) jsonError('업로드 오류: ' . $file['error']);
            if ($file['size'] < 1000) jsonError('녹음이 너무 짧습니다');
            if ($file['size'] > 5 * 1024 * 1024) jsonError('파일이 너무 큽니다 (최대 5MB)');

            $mimeType = $file['type'] ?: 'audio/webm';
            $ext = match(true) {
                str_contains($mimeType, 'webm') => 'webm',
                str_contains($mimeType, 'ogg') => 'ogg',
                str_contains($mimeType, 'mp4'), str_contains($mimeType, 'm4a') => 'mp4',
                default => 'wav',
            };

            // 저장
            $uploadDir = BRAVO_UPLOAD_DIR . "/{$studentId}/{$sub['bravo_level']}";
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $fileName = "{$submissionId}_{$item['item_index']}_{$item['section_type']}.{$ext}";
            $filePath = "{$studentId}/{$sub['bravo_level']}/{$fileName}";
            $fullPath = BRAVO_UPLOAD_DIR . "/{$filePath}";

            if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
                jsonError('파일 저장 실패');
            }

            // DB upsert
            $stmt = $db->prepare('
                INSERT INTO junior_bravo_recordings (submission_id, item_id, file_path, file_size, mime_type)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE file_path = VALUES(file_path), file_size = VALUES(file_size), mime_type = VALUES(mime_type), recorded_at = CURRENT_TIMESTAMP, id = LAST_INSERT_ID(id)
            ');
            $stmt->execute([$submissionId, $itemId, $filePath, $file['size'], $mimeType]);
            $recordingId = (int)$db->lastInsertId();

            // 녹음 진행률
            $stmt = $db->prepare('SELECT COUNT(*) FROM junior_bravo_recordings WHERE submission_id = ?');
            $stmt->execute([$submissionId]);
            $recorded = (int)$stmt->fetchColumn();

            $stmt = $db->prepare('SELECT COUNT(*) FROM junior_bravo_items WHERE bravo_level = ? AND section_type IN (?, ?)');
            $stmt->execute([$sub['bravo_level'], 'sentence', 'phonics']);
            $total = (int)$stmt->fetchColumn();

            jsonSuccess([
                'recording_id' => $recordingId,
                'recorded' => $recorded,
                'total' => $total,
            ]);

        case 'submit':
            if ($method !== 'POST') jsonError('POST required', 405);
            $session = requireStudent();
            $studentId = $session['student_id'];
            $db = getDB();

            $input = getJsonInput();
            $submissionId = (int)($input['submission_id'] ?? 0);
            if (!$submissionId) jsonError('submission_id 필요');

            // 제출 확인
            $stmt = $db->prepare('SELECT id, bravo_level, status FROM junior_bravo_submissions WHERE id = ? AND student_id = ?');
            $stmt->execute([$submissionId, $studentId]);
            $sub = $stmt->fetch();
            if (!$sub || $sub['status'] !== 'in_progress') jsonError('유효하지 않은 세션');

            $bravoLevel = (int)$sub['bravo_level'];

            // 퀴즈 + 블록 답안 확인
            $stmt = $db->prepare('
                SELECT ba.is_correct, bi.section_type
                FROM junior_bravo_answers ba
                JOIN junior_bravo_items bi ON bi.id = ba.item_id
                WHERE ba.submission_id = ?
            ');
            $stmt->execute([$submissionId]);
            $answers = $stmt->fetchAll();

            $quizCorrect = 0; $quizTotal = 0;
            $blockCorrect = 0; $blockTotal = 0;
            foreach ($answers as $ans) {
                if ($ans['section_type'] === 'quiz') {
                    $quizTotal++;
                    if ($ans['is_correct']) $quizCorrect++;
                } elseif ($ans['section_type'] === 'block') {
                    $blockTotal++;
                    if ($ans['is_correct']) $blockCorrect++;
                }
            }

            // 녹음 확인
            $stmt = $db->prepare('SELECT COUNT(*) FROM junior_bravo_recordings WHERE submission_id = ?');
            $stmt->execute([$submissionId]);
            $recordedCount = (int)$stmt->fetchColumn();

            $stmt = $db->prepare('SELECT COUNT(*) FROM junior_bravo_items WHERE bravo_level = ? AND section_type IN (?, ?)');
            $stmt->execute([$bravoLevel, 'sentence', 'phonics']);
            $requiredRecordings = (int)$stmt->fetchColumn();

            if ($recordedCount < $requiredRecordings) {
                jsonError("녹음이 완료되지 않았습니다 ({$recordedCount}/{$requiredRecordings})");
            }

            // 필요한 퀴즈/블록 답안 수 확인
            $stmt = $db->prepare('SELECT COUNT(*) FROM junior_bravo_items WHERE bravo_level = ? AND section_type = ?');
            $stmt->execute([$bravoLevel, 'quiz']);
            $requiredQuiz = (int)$stmt->fetchColumn();

            $stmt = $db->prepare('SELECT COUNT(*) FROM junior_bravo_items WHERE bravo_level = ? AND section_type = ?');
            $stmt->execute([$bravoLevel, 'block']);
            $requiredBlock = (int)$stmt->fetchColumn();

            // phonics 레벨은 block 대신 phonics
            $meta = BRAVO_LEVELS[$bravoLevel] ?? [];
            $isPhonics = !empty($meta['isPhonics']);

            if (!$isPhonics && $quizTotal < $requiredQuiz) {
                jsonError("퀴즈가 완료되지 않았습니다 ({$quizTotal}/{$requiredQuiz})");
            }
            if (!$isPhonics && $blockTotal < $requiredBlock) {
                jsonError("블록이 완료되지 않았습니다 ({$blockTotal}/{$requiredBlock})");
            }

            // 자동 채점 (퀴즈 + 블록)
            $objectiveCorrect = $quizCorrect + $blockCorrect;
            $objectiveTotal = $quizTotal + $blockTotal;
            $autoResult = 'fail';
            if ($objectiveTotal > 0) {
                $rate = $objectiveCorrect / $objectiveTotal;
                if ($rate >= BRAVO_PASS_RATE) $autoResult = 'pass';
            } elseif ($isPhonics) {
                // Jr 1: 퀴즈만 자동 채점 (블록 없음)
                if ($quizTotal > 0) {
                    $rate = $quizCorrect / $quizTotal;
                    $autoResult = $rate >= BRAVO_PASS_RATE ? 'pass' : 'fail';
                }
            }

            $db->beginTransaction();
            try {
                // 제출 업데이트
                $stmt = $db->prepare('
                    UPDATE junior_bravo_submissions
                    SET status = ?, quiz_correct = ?, quiz_total = ?, block_correct = ?, block_total = ?,
                        auto_result = ?, submitted_at = NOW()
                    WHERE id = ?
                ');
                $stmt->execute(['submitted', $quizCorrect, $quizTotal, $blockCorrect, $blockTotal, $autoResult, $submissionId]);

                // 코인 지급
                $coinResult = changeReward($studentId, 'ace', 1, 'bravo_submission', "Bravo Jr {$bravoLevel} 제출");
                if ($coinResult['success']) {
                    $db->prepare('UPDATE junior_bravo_submissions SET coins_awarded = 3 WHERE id = ?')
                       ->execute([$submissionId]);
                }

                $db->commit();

                $totalCoins = getStudentTotalCoins($studentId);

                jsonSuccess([
                    'auto_result' => $autoResult,
                    'quiz_score' => "{$quizCorrect}/{$quizTotal}",
                    'block_score' => "{$blockCorrect}/{$blockTotal}",
                    'coins_awarded' => 3,
                    'total_coins' => $totalCoins,
                ]);
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }

        case 'audio':
            $recordingId = (int)($_GET['id'] ?? 0);
            $audioToken = trim($_GET['token'] ?? '');
            if (!$recordingId) jsonError('recording id가 필요합니다');

            $authorized = false;

            // 리포트 토큰 기반 인증
            if ($audioToken) {
                $db = getDB();
                $stmt = $db->prepare('SELECT student_id FROM junior_bravo_submissions WHERE report_token = ?');
                $stmt->execute([$audioToken]);
                if ($stmt->fetch()) $authorized = true;
            }

            if (!$authorized && !empty($_COOKIE['JUNIOR_ADMIN_SID'])) {
                startAdminSession();
                if (!empty($_SESSION['admin_id'])) $authorized = true;
            }
            if (!$authorized && !empty($_COOKIE['JUNIOR_STUDENT_SID'])) {
                startStudentSession();
                if (!empty($_SESSION['student_id'])) $authorized = true;
            }

            if (!$authorized) jsonError('인증이 필요합니다', 401);

            $db = getDB();
            $stmt = $db->prepare('SELECT file_path, mime_type FROM junior_bravo_recordings WHERE id = ?');
            $stmt->execute([$recordingId]);
            $rec = $stmt->fetch();
            if (!$rec) jsonError('녹음을 찾을 수 없습니다', 404);

            $fullPath = BRAVO_UPLOAD_DIR . '/' . $rec['file_path'];
            if (!file_exists($fullPath)) jsonError('파일을 찾을 수 없습니다', 404);

            header('Content-Type: ' . ($rec['mime_type'] ?: 'audio/webm'));
            header('Content-Length: ' . filesize($fullPath));
            header('Cache-Control: private, max-age=3600');
            readfile($fullPath);
            exit;

        // ==========================================
        // 코치용 API
        // ==========================================

        case 'coach_pending':
            $admin = requireAdmin(['coach', 'admin_teacher']);
            $db = getDB();
            $adminId = $admin['admin_id'];
            $classId = (int)($_GET['class_id'] ?? 0);

            // 코치의 반 가져오기
            $stmt = $db->prepare('SELECT class_id FROM junior_admin_classes WHERE admin_id = ? AND is_active = 1');
            $stmt->execute([$adminId]);
            $myClassIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (empty($myClassIds)) jsonSuccess(['pending' => [], 'summary' => []]);

            if ($classId && !in_array($classId, $myClassIds)) {
                jsonError('접근 권한이 없는 반입니다');
            }
            $classIds = $classId ? [$classId] : $myClassIds;
            $placeholders = implode(',', array_fill(0, count($classIds), '?'));

            // 확인 대기 중인 제출
            $stmt = $db->prepare("
                SELECT bs.id, bs.student_id, bs.bravo_level, bs.status, bs.auto_result,
                       bs.quiz_correct, bs.quiz_total, bs.block_correct, bs.block_total,
                       bs.submitted_at, s.name as student_name,
                       c.color as class_color
                FROM junior_bravo_submissions bs
                JOIN junior_students s ON s.id = bs.student_id
                JOIN junior_class_students cs ON cs.student_id = bs.student_id AND cs.is_active = 1
                JOIN junior_classes c ON cs.class_id = c.id
                WHERE bs.status = 'submitted'
                  AND cs.class_id IN ({$placeholders})
                ORDER BY bs.submitted_at ASC
            ");
            $stmt->execute($classIds);
            $pending = $stmt->fetchAll();

            // 요약 통계
            $stmt = $db->prepare("
                SELECT COUNT(DISTINCT s.id) as total_students,
                       SUM(CASE WHEN s.bravo_current_level >= 2 THEN 1 ELSE 0 END) as jr1_pass,
                       SUM(CASE WHEN s.bravo_current_level >= 3 THEN 1 ELSE 0 END) as jr2_pass,
                       SUM(CASE WHEN s.bravo_current_level >= 4 THEN 1 ELSE 0 END) as jr3_pass,
                       SUM(CASE WHEN s.bravo_current_level >= 5 THEN 1 ELSE 0 END) as jr4_pass,
                       SUM(CASE WHEN s.bravo_current_level >= 6 THEN 1 ELSE 0 END) as jr5_pass,
                       SUM(CASE WHEN s.bravo_current_level >= 7 THEN 1 ELSE 0 END) as jr6_pass
                FROM junior_students s
                JOIN junior_class_students cs ON cs.student_id = s.id
                WHERE cs.class_id IN ({$placeholders})
                  AND s.ace_current_level >= 4
            ");
            $stmt->execute($classIds);
            $summary = $stmt->fetch();

            jsonSuccess([
                'pending' => $pending,
                'summary' => $summary,
            ]);

        case 'coach_student_detail':
            $admin = requireAdmin(['coach', 'admin_teacher']);
            $db = getDB();

            $studentId = (int)($_GET['student_id'] ?? 0);
            if (!$studentId) jsonError('student_id 필요');

            // 학생 정보
            $stmt = $db->prepare('
                SELECT s.id, s.name, s.bravo_current_level, c.display_name as class_name, c.color as class_color
                FROM junior_students s
                LEFT JOIN junior_class_students cs ON cs.student_id = s.id AND cs.is_active = 1 AND cs.is_primary = 1
                LEFT JOIN junior_classes c ON cs.class_id = c.id
                WHERE s.id = ?
            ');
            $stmt->execute([$studentId]);
            $student = $stmt->fetch();
            if (!$student) jsonError('학생을 찾을 수 없습니다');

            // 모든 제출
            $stmt = $db->prepare('
                SELECT id, bravo_level, status, auto_result, coach_result,
                       comment_type, comment_text, report_token,
                       quiz_correct, quiz_total, block_correct, block_total,
                       submitted_at, confirmed_at
                FROM junior_bravo_submissions
                WHERE student_id = ?
                ORDER BY bravo_level, created_at DESC
            ');
            $stmt->execute([$studentId]);
            $submissions = $stmt->fetchAll();

            // 각 제출의 녹음
            $recordings = [];
            foreach ($submissions as $sub) {
                $stmt = $db->prepare('
                    SELECT br.id, br.item_id, bi.section_type, bi.item_index, bi.item_data
                    FROM junior_bravo_recordings br
                    JOIN junior_bravo_items bi ON bi.id = br.item_id
                    WHERE br.submission_id = ?
                    ORDER BY bi.section_type, bi.item_index
                ');
                $stmt->execute([$sub['id']]);
                $recs = $stmt->fetchAll();
                foreach ($recs as &$r) {
                    $r['item_data'] = json_decode($r['item_data'], true);
                }
                $recordings[$sub['id']] = $recs;
            }

            // 각 제출의 답안
            $answers = [];
            foreach ($submissions as $sub) {
                $stmt = $db->prepare('
                    SELECT ba.item_id, ba.answer_data, ba.is_correct, bi.section_type, bi.item_index, bi.item_data
                    FROM junior_bravo_answers ba
                    JOIN junior_bravo_items bi ON bi.id = ba.item_id
                    WHERE ba.submission_id = ?
                    ORDER BY bi.section_type, bi.item_index
                ');
                $stmt->execute([$sub['id']]);
                $ans = $stmt->fetchAll();
                foreach ($ans as &$a) {
                    $a['item_data'] = json_decode($a['item_data'], true);
                    $a['answer_data'] = json_decode($a['answer_data'], true);
                }
                $answers[$sub['id']] = $ans;
            }

            jsonSuccess([
                'student' => $student,
                'submissions' => $submissions,
                'recordings' => $recordings,
                'answers' => $answers,
            ]);

        case 'coach_confirm':
            if ($method !== 'POST') jsonError('POST required', 405);
            $admin = requireAdmin(['coach', 'admin_teacher']);
            $db = getDB();

            $input = getJsonInput();
            $submissionId = (int)($input['submission_id'] ?? 0);
            $result = $input['result'] ?? '';
            $commentType = $input['comment_type'] ?? null;
            $commentText = $input['comment_text'] ?? null;

            if (!$submissionId || !in_array($result, ['pass', 'retry'])) {
                jsonError('submission_id와 result(pass/retry) 필요');
            }

            // 제출 확인
            $stmt = $db->prepare('SELECT id, student_id, bravo_level, status FROM junior_bravo_submissions WHERE id = ?');
            $stmt->execute([$submissionId]);
            $sub = $stmt->fetch();
            if (!$sub || $sub['status'] !== 'submitted') jsonError('유효하지 않은 제출');

            $db->beginTransaction();
            try {
                // 리포트 토큰 생성
                $reportToken = bin2hex(random_bytes(32));

                // 확인 처리
                $stmt = $db->prepare('
                    UPDATE junior_bravo_submissions
                    SET status = ?, coach_result = ?, comment_type = ?, comment_text = ?,
                        report_token = ?, confirmed_by = ?, confirmed_at = NOW()
                    WHERE id = ?
                ');
                $stmt->execute(['confirmed', $result, $commentType, $commentText, $reportToken, $admin['admin_id'], $submissionId]);

                // PASS면 레벨 업
                if ($result === 'pass') {
                    $nextLevel = (int)$sub['bravo_level'] + 1;
                    $stmt = $db->prepare('
                        UPDATE junior_students
                        SET bravo_current_level = GREATEST(COALESCE(bravo_current_level, 0), ?)
                        WHERE id = ?
                    ');
                    $stmt->execute([$nextLevel, $sub['student_id']]);
                }

                $db->commit();

                $resultText = $result === 'pass' ? 'PASS' : 'RETRY';
                $meta = BRAVO_LEVELS[(int)$sub['bravo_level']] ?? [];
                $levelName = $meta['bravo'] ?? "Jr {$sub['bravo_level']}";

                jsonSuccess([
                    'message' => "{$levelName}: {$resultText} 처리 완료",
                    'report_token' => $reportToken,
                ]);
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }

        case 'comment_templates':
            requireAdmin(['coach', 'admin_teacher']);
            $db = getDB();
            $type = $_GET['type'] ?? '';

            if ($type) {
                $stmt = $db->prepare('SELECT * FROM junior_bravo_comment_templates WHERE comment_type = ? ORDER BY sort_order');
                $stmt->execute([$type]);
            } else {
                $stmt = $db->query('SELECT * FROM junior_bravo_comment_templates ORDER BY comment_type, sort_order');
            }

            jsonSuccess(['templates' => $stmt->fetchAll()]);

        case 'send_report':
            if ($method !== 'POST') jsonError('POST required', 405);
            $admin = requireAdmin(['coach', 'admin_teacher']);
            $db = getDB();

            $input = getJsonInput();
            $submissionId = (int)($input['submission_id'] ?? 0);
            if (!$submissionId) jsonError('submission_id가 필요합니다');

            $stmt = $db->prepare('SELECT id, report_token FROM junior_bravo_submissions WHERE id = ?');
            $stmt->execute([$submissionId]);
            $sub = $stmt->fetch();
            if (!$sub || !$sub['report_token']) jsonError('리포트 토큰이 없습니다');

            $reportUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'j.soritune.com') . '/bravo-report/?token=' . $sub['report_token'];

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
                SELECT bs.*, s.name as student_name, s.bravo_current_level,
                       c.display_name as class_name, c.color as class_color,
                       a.name as coach_name
                FROM junior_bravo_submissions bs
                JOIN junior_students s ON bs.student_id = s.id
                JOIN junior_class_students cs ON cs.student_id = s.id AND cs.is_primary = 1 AND cs.is_active = 1
                JOIN junior_classes c ON cs.class_id = c.id
                LEFT JOIN junior_admins a ON bs.confirmed_by = a.id
                WHERE bs.report_token = ?
            ');
            $stmt->execute([$token]);
            $report = $stmt->fetch();
            if (!$report) jsonError('유효하지 않은 리포트입니다', 404);

            // 녹음 데이터 (문장/파닉스)
            $stmt = $db->prepare('
                SELECT br.id as recording_id, br.item_id,
                       bi.section_type, bi.item_index, bi.item_data
                FROM junior_bravo_recordings br
                JOIN junior_bravo_items bi ON bi.id = br.item_id
                WHERE br.submission_id = ?
                ORDER BY bi.section_type, bi.item_index
            ');
            $stmt->execute([$report['id']]);
            $recordings = $stmt->fetchAll();
            foreach ($recordings as &$rec) {
                $rec['item_data'] = json_decode($rec['item_data'], true);
            }

            // 전체 BRAVO 레벨 현황
            $stmt = $db->prepare('
                SELECT bravo_level, coach_result, confirmed_at
                FROM junior_bravo_submissions
                WHERE student_id = ? AND status = ?
                ORDER BY bravo_level
            ');
            $stmt->execute([$report['student_id'], 'confirmed']);
            $allEvals = $stmt->fetchAll();

            jsonSuccess([
                'student_name' => $report['student_name'],
                'class_name' => $report['class_name'],
                'class_color' => $report['class_color'],
                'coach_name' => $report['coach_name'],
                'bravo_level' => (int)$report['bravo_level'],
                'result' => $report['coach_result'],
                'auto_result' => $report['auto_result'],
                'quiz_correct' => (int)$report['quiz_correct'],
                'quiz_total' => (int)$report['quiz_total'],
                'block_correct' => (int)$report['block_correct'],
                'block_total' => (int)$report['block_total'],
                'comment_type' => $report['comment_type'],
                'comment_text' => $report['comment_text'],
                'confirmed_at' => $report['confirmed_at'],
                'recordings' => $recordings,
                'all_evaluations' => $allEvals,
                'current_level' => $report['bravo_current_level'],
                'levels_meta' => BRAVO_LEVELS,
                'token' => $token,
            ]);

        default:
            jsonError('Unknown action: ' . $action, 400);
    }
} catch (Exception $e) {
    error_log('Bravo API Error: ' . $e->getMessage());
    jsonError('서버 오류가 발생했습니다', 500);
}
