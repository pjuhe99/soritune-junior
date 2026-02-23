#!/usr/bin/env php
<?php
/**
 * 주간 과제 요약 계산 크론
 * 매주 일요일 01:00 실행 권장
 * crontab: 0 1 * * 0 php /var/www/html/_______site_SORITUNECOM_J/cron/weekly_summary.php
 *
 * Usage:
 *   php weekly_summary.php              → 지난주 자동 계산
 *   php weekly_summary.php 2026-02-10   → 해당 날짜가 포함된 주 계산
 *   php weekly_summary.php backfill     → 2026-01-05부터 현재까지 전체 주 계산
 */

require_once __DIR__ . '/../public_html/config.php';

$arg = $argv[1] ?? null;

if ($arg === 'backfill') {
    backfillAll();
} elseif ($arg && preg_match('/^\d{4}-\d{2}-\d{2}$/', $arg)) {
    processWeek($arg);
} elseif ($arg === null) {
    // 지난주 (직전 완료된 주)
    $lastFriday = new DateTime();
    $dow = (int)$lastFriday->format('N');
    // 가장 최근 금요일 찾기
    if ($dow < 5) {
        $lastFriday->modify('last friday');
    } elseif ($dow > 5) {
        $lastFriday->modify('last friday');
    }
    processWeek($lastFriday->format('Y-m-d'));
} else {
    echo "Usage: php weekly_summary.php [YYYY-MM-DD|backfill]\n";
    exit(1);
}

/**
 * 특정 날짜가 포함된 주를 계산
 */
function processWeek(string $targetDate): void {
    $db = getDB();

    $dt = new DateTime($targetDate);
    $dayOfWeek = (int)$dt->format('N'); // 1=Mon ... 7=Sun

    // 해당 주의 월요일
    $monday = clone $dt;
    $monday->modify('-' . ($dayOfWeek - 1) . ' days');
    $weekStart = $monday->format('Y-m-d');

    // 해당 주의 금요일
    $friday = clone $monday;
    $friday->modify('+4 days');
    $weekEnd = $friday->format('Y-m-d');

    echo "[{$weekStart} ~ {$weekEnd}] ";

    // 해당 주의 쉬는 날 (평일 중)
    $stmt = $db->prepare('
        SELECT off_date FROM junior_off_days
        WHERE off_date BETWEEN ? AND ?
        AND DAYOFWEEK(off_date) BETWEEN 2 AND 6
    ');
    $stmt->execute([$weekStart, $weekEnd]);
    $offDates = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // 평일 수 계산 (일반적으로 5, 하지만 시즌 시작/끝 고려)
    $weekdays = 5;
    $requiredDays = $weekdays - count($offDates);

    // off_date 제외 조건 빌드
    $excludeClause = '';
    $excludeParams = [];
    if (!empty($offDates)) {
        $placeholders = implode(',', array_fill(0, count($offDates), '?'));
        $excludeClause = "AND dc.check_date NOT IN ({$placeholders})";
        $excludeParams = $offDates;
    }

    // 전체 학생 과제 현황 (단일 쿼리)
    $sql = "
        SELECT
            s.id as student_id,
            cs.class_id,
            COALESCE(SUM(dc.zoom_attendance), 0) as zoom_total,
            COALESCE(SUM(dc.posture_king), 0) as posture_total,
            COALESCE(SUM(dc.sound_homework), 0) as homework_count,
            COALESCE(SUM(dc.band_mission), 0) as mission_count,
            COALESCE(SUM(dc.leader_king), 0) as leader_count,
            COUNT(DISTINCT CASE WHEN dc.sound_homework > 0 THEN dc.check_date END) as hw_days
        FROM junior_students s
        JOIN junior_class_students cs ON s.id = cs.student_id AND cs.is_primary = 1 AND cs.is_active = 1
        LEFT JOIN junior_daily_checklist dc ON s.id = dc.student_id
            AND dc.check_date BETWEEN ? AND ?
            AND DAYOFWEEK(dc.check_date) BETWEEN 2 AND 6
            {$excludeClause}
        WHERE s.is_active = 1
        GROUP BY s.id, cs.class_id
    ";

    $params = array_merge([$weekStart, $weekEnd], $excludeParams);
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();

    // 일괄 INSERT/UPDATE
    $insertStmt = $db->prepare('
        INSERT INTO junior_weekly_summary
        (student_id, class_id, week_start, week_end, required_days, completed_days,
         zoom_total, posture_total, homework_count, mission_count, leader_count, is_steady_king)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            class_id = VALUES(class_id),
            required_days = VALUES(required_days),
            completed_days = VALUES(completed_days),
            zoom_total = VALUES(zoom_total),
            posture_total = VALUES(posture_total),
            homework_count = VALUES(homework_count),
            mission_count = VALUES(mission_count),
            leader_count = VALUES(leader_count),
            is_steady_king = VALUES(is_steady_king),
            calculated_at = CURRENT_TIMESTAMP
    ');

    $count = 0;
    $steadyCount = 0;
    foreach ($results as $row) {
        $hwDays = (int)$row['hw_days'];
        $isSteady = ($requiredDays > 0 && $hwDays >= $requiredDays) ? 1 : 0;
        if ($isSteady) $steadyCount++;

        $insertStmt->execute([
            $row['student_id'], $row['class_id'],
            $weekStart, $weekEnd, $requiredDays, $hwDays,
            (int)$row['zoom_total'], (int)$row['posture_total'],
            (int)$row['homework_count'], (int)$row['mission_count'],
            (int)$row['leader_count'], $isSteady,
        ]);
        $count++;
    }

    echo "학생 {$count}명 처리 (필요일수:{$requiredDays}, 꾸준왕:{$steadyCount}명)\n";
}

/**
 * 전체 기간 백필 (2026-01-05부터 현재까지)
 */
function backfillAll(): void {
    echo "=== 전체 주간 백필 시작 ===\n";

    $start = new DateTime('2026-01-05'); // 데이터 시작일 (월요일)
    $now = new DateTime();

    $current = clone $start;
    $weekCount = 0;

    while ($current <= $now) {
        processWeek($current->format('Y-m-d'));
        $current->modify('+7 days');
        $weekCount++;
    }

    echo "=== 완료: {$weekCount}주 처리 ===\n";
}
