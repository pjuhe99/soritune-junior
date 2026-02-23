<?php
/**
 * QR 스캔 랜딩 페이지
 * 학생이 QR을 스캔하면 이 페이지에서 직접 로그인 + 출석 처리
 * - 핑거프린트 자동 로그인
 * - 형제 선택
 * - 반 선택 → 이름 + 전화번호 뒷자리 로그인
 * - 출석 기록
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/session.php';
require_once dirname(__DIR__) . '/auth.php';

$code = trim($_GET['code'] ?? '');

// 세션 코드가 없으면 메인으로
if (!$code) {
    header('Location: /');
    exit;
}

// 세션 검증
$qrSession = verifyQRSession($code);

// OG 메타태그 데이터
$siteName = getSetting('site_name', '소리튠 주니어 영어학교');
$siteUrl = getSetting('site_url', 'https://j.soritune.com');
$ogTitle = $siteName . ' 출석체크';
$ogDescription = '';
$ogImage = $siteUrl . '/images/og/qr_background.png';

$isActive = $qrSession && $qrSession['status'] === 'active';
$isExpiredOrClosed = $qrSession && $qrSession['status'] !== 'active';

if ($qrSession) {
    $ogDescription = $qrSession['class_name'] . ' 반 출석체크';
}

// 반 목록 (활성 세션일 때만 로드)
$classes = [];
if ($isActive) {
    $db = getDB();
    $stmt = $db->prepare('SELECT id, name, display_name, logo_image, color FROM junior_classes WHERE is_active = 1 ORDER BY sort_order');
    $stmt->execute();
    $classes = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">

    <!-- OG 메타태그 (카카오 미리보기) -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= e($ogTitle) ?>">
    <meta property="og:description" content="<?= e($ogDescription) ?>">
    <meta property="og:image" content="<?= e($ogImage) ?>">
    <meta property="og:url" content="<?= e($siteUrl . '/qr/scan.php?code=' . $code) ?>">

    <title><?= e($ogTitle) ?></title>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/variable/pretendardvariable-dynamic-subset.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/css/common.css?v=20260213h">
    <link rel="stylesheet" href="/css/toast.css?v=20260213h">
    <style>
        * { box-sizing: border-box; }
        body { margin:0; font-family:'Pretendard Variable',Pretendard,-apple-system,sans-serif; background: linear-gradient(135deg, #FF7E17 0%, #FF5722 100%); min-height:100vh; }
        .scan-app { max-width:480px; margin:0 auto; padding:20px 16px; min-height:100vh; }

        /* Header */
        .scan-header { text-align:center; color:#fff; padding:20px 0 24px; }
        .scan-logo { font-size:44px; margin-bottom:4px; }
        .scan-title { font-size:22px; font-weight:800; letter-spacing:-0.5px; }
        .scan-class-badge { display:inline-block; margin-top:8px; padding:6px 18px; background:rgba(255,255,255,.2); border-radius:20px; font-size:14px; font-weight:600; backdrop-filter:blur(4px); }

        /* Card */
        .scan-card { background:#fff; border-radius:24px; padding:28px 24px; box-shadow:0 12px 40px rgba(0,0,0,.15); }

        /* States */
        .scan-state { display:none; }
        .scan-state.active { display:block; animation:fadeIn .3s ease; }
        @keyframes fadeIn { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:translateY(0); } }

        /* Section header */
        .scan-section-title { font-size:18px; font-weight:800; color:#333; text-align:center; margin-bottom:4px; }
        .scan-section-desc { font-size:13px; color:#9E9E9E; text-align:center; margin-bottom:20px; }

        /* Class grid */
        .scan-class-grid { display:grid; grid-template-columns: repeat(4, 1fr); gap:8px; }
        .scan-class-item {
            display:flex; flex-direction:column; align-items:center; gap:5px;
            padding:10px 4px; border:2px solid #F0F0F0; border-radius:14px;
            cursor:pointer; transition:all .2s; background:#fff; -webkit-tap-highlight-color:transparent;
        }
        .scan-class-item:active { transform:scale(.96); }
        .scan-class-item.qr-home { border-color:#4CAF50; background:#E8F5E9; }
        .scan-class-item.qr-home::after { content:'코치님 반'; display:block; font-size:9px; color:#4CAF50; font-weight:700; margin-top:-2px; }
        .scan-class-item.selected { border-color:#FF6B1A; background:#FFF3E0; box-shadow:0 2px 8px rgba(255,107,26,.2); }
        .scan-class-logo { width:36px; height:36px; border-radius:50%; object-fit:cover; background:#f5f5f5; }
        .scan-class-logo-text { width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:800; font-size:14px; }
        .scan-class-name { font-size:10px; font-weight:600; color:#555; text-align:center; line-height:1.2; word-break:keep-all; }

        /* Login form */
        .scan-field { margin-bottom:14px; }
        .scan-field label { display:block; font-size:13px; font-weight:600; color:#555; margin-bottom:6px; }
        .scan-field input {
            width:100%; padding:13px 14px; border:1.5px solid #E0E0E0; border-radius:12px;
            font-size:15px; font-family:inherit; outline:none; transition:border .2s;
        }
        .scan-field input:focus { border-color:#FF6B1A; }

        .scan-btn {
            width:100%; padding:15px; background:linear-gradient(135deg,#FF6B1A,#FF5722);
            color:#fff; border:none; border-radius:14px; font-size:16px; font-weight:700;
            cursor:pointer; font-family:inherit; transition:all .2s;
        }
        .scan-btn:active { transform:scale(.98); }
        .scan-btn:disabled { background:#ccc; transform:none; }
        .scan-btn-back {
            display:inline-flex; align-items:center; gap:4px;
            background:none; border:none; color:#9E9E9E; font-size:13px;
            cursor:pointer; margin-top:14px; font-family:inherit; padding:6px 0;
        }

        /* Sibling list */
        .scan-sibling-item {
            display:flex; align-items:center; gap:12px; padding:14px 16px;
            border:1.5px solid #F0F0F0; border-radius:14px; cursor:pointer;
            transition:all .15s; margin-bottom:8px; -webkit-tap-highlight-color:transparent;
        }
        .scan-sibling-item:active { background:#FFF3E0; border-color:#FF6B1A; }
        .scan-sibling-item.current { border-color:#4CAF50; background:#E8F5E9; }
        .scan-sibling-avatar {
            width:42px; height:42px; background:linear-gradient(135deg,#FF7E17,#FF5722);
            border-radius:50%; display:flex; align-items:center; justify-content:center;
            color:#fff; font-weight:700; font-size:17px; flex-shrink:0;
        }

        /* Success */
        .scan-success { text-align:center; padding:16px 0; }
        .scan-success-icon { font-size:64px; margin-bottom:12px; }
        .scan-success-title { font-size:22px; font-weight:800; color:#333; margin-bottom:6px; }
        .scan-success-desc { color:#777; margin-bottom:28px; font-size:14px; }
        .scan-success .scan-btn { display:inline-block; text-decoration:none; text-align:center; width:auto; padding:14px 40px; }

        /* Error */
        .scan-error { text-align:center; padding:16px 0; }
        .scan-error-icon { font-size:56px; margin-bottom:12px; }
        .scan-error .scan-btn { display:inline-block; text-decoration:none; text-align:center; width:auto; padding:14px 40px; }

        /* Loading */
        .scan-loading { text-align:center; padding:40px 0; }
        .scan-loading-spinner {
            width:36px; height:36px; border:3px solid #f0f0f0; border-top:3px solid #FF6B1A;
            border-radius:50%; animation:spin .8s linear infinite; margin:0 auto 14px;
        }
        @keyframes spin { to { transform:rotate(360deg); } }
        .scan-loading-text { color:#999; font-size:14px; }
    </style>
</head>
<body>
    <div class="scan-app">
        <div class="scan-header">
            <div class="scan-logo">📸</div>
            <div class="scan-title">출석체크</div>
            <?php if ($qrSession): ?>
            <div class="scan-class-badge"><?= e($qrSession['class_name']) ?> 반</div>
            <?php endif; ?>
        </div>

        <div class="scan-card">
<?php if (!$qrSession): ?>
            <!-- 세션 없음 -->
            <div class="scan-state active" id="st-error">
                <div class="scan-error">
                    <div class="scan-error-icon">🔍</div>
                    <div class="scan-section-title">출석 세션을 찾을 수 없습니다</div>
                    <div class="scan-section-desc">QR 코드가 올바르지 않거나 만료되었습니다</div>
                    <a href="/" class="scan-btn">홈으로 이동</a>
                </div>
            </div>
<?php elseif ($isExpiredOrClosed): ?>
            <!-- 만료/종료 -->
            <div class="scan-state active" id="st-error">
                <div class="scan-error">
                    <div class="scan-error-icon"><?= $qrSession['status'] === 'expired' ? '⏰' : '🔒' ?></div>
                    <div class="scan-section-title"><?= $qrSession['status'] === 'expired' ? '세션이 만료되었습니다' : '세션이 종료되었습니다' ?></div>
                    <div class="scan-section-desc"><?= e($qrSession['class_name']) ?> 반 출석체크가 <?= $qrSession['status'] === 'expired' ? '만료' : '종료' ?>되었습니다</div>
                    <a href="/" class="scan-btn">홈으로 이동</a>
                </div>
            </div>
<?php else: ?>
            <!-- 활성 세션 → 인터랙티브 로그인 -->
            <!-- Loading -->
            <div class="scan-state active" id="st-loading">
                <div class="scan-loading">
                    <div class="scan-loading-spinner"></div>
                    <div class="scan-loading-text">확인 중...</div>
                </div>
            </div>

            <!-- Error (JS) -->
            <div class="scan-state" id="st-error-js">
                <div class="scan-error">
                    <div class="scan-error-icon" id="js-error-icon">⚠️</div>
                    <div class="scan-section-title" id="js-error-title">오류</div>
                    <div class="scan-section-desc" id="js-error-desc"></div>
                    <a href="/" class="scan-btn">홈으로 이동</a>
                </div>
            </div>

            <!-- Sibling Selection -->
            <div class="scan-state" id="st-siblings">
                <div class="scan-section-title">누구인가요?</div>
                <div class="scan-section-desc">출석할 사람을 선택해 주세요</div>
                <div id="sibling-list"></div>
            </div>

            <!-- Class Selection -->
            <div class="scan-state" id="st-class">
                <div class="scan-section-title">나의 반을 선택해!</div>
                <div class="scan-class-grid" id="class-grid"></div>
            </div>

            <!-- Login Form -->
            <div class="scan-state" id="st-login">
                <div class="scan-section-title" id="login-class-title">본인 확인</div>
                <div class="scan-section-desc">이름과 전화번호 뒷자리를 입력해 주세요</div>
                <div class="scan-field">
                    <label>이름</label>
                    <input type="text" id="scan-name" placeholder="이름을 입력하세요" autocomplete="off">
                </div>
                <div class="scan-field">
                    <label>전화번호 뒷 4자리</label>
                    <input type="tel" id="scan-phone" maxlength="4" placeholder="0000" inputmode="numeric" autocomplete="off">
                </div>
                <button class="scan-btn" id="btn-scan-login">출석하기</button>
                <div style="text-align:center;">
                    <button class="scan-btn-back" id="btn-back-class">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                        다른 반 선택
                    </button>
                </div>
            </div>

            <!-- Success -->
            <div class="scan-state" id="st-success">
                <div class="scan-success">
                    <div class="scan-success-icon">✅</div>
                    <div class="scan-success-title" id="success-name">출석 완료!</div>
                    <div class="scan-success-desc" id="success-desc">출석이 기록되었습니다</div>
                    <a href="/" class="scan-btn">마이페이지 보기</a>
                </div>
            </div>
<?php endif; ?>
        </div>
    </div>

<?php if ($isActive): ?>
    <script src="/js/toast.js?v=20260213h"></script>
    <script src="/js/fingerprint.js?v=20260213h"></script>
    <script src="/js/common.js?v=20260213h"></script>
    <script>
    (async function() {
        const SESSION_CODE = '<?= e($code) ?>';
        const QR_CLASS_ID = <?= (int)$qrSession['class_id'] ?>;
        const CLASSES = <?= json_encode($classes, JSON_UNESCAPED_UNICODE) ?>;
        let selectedClassId = null;
        let fp = '';

        function showState(id) {
            document.querySelectorAll('.scan-state').forEach(s => s.classList.remove('active'));
            const el = document.getElementById('st-' + id);
            if (el) el.classList.add('active');
        }

        function showError(icon, title, desc) {
            document.getElementById('js-error-icon').textContent = icon;
            document.getElementById('js-error-title').textContent = title;
            document.getElementById('js-error-desc').textContent = desc;
            showState('error-js');
        }

        // 1. 핑거프린트 생성
        try { fp = await DeviceFingerprint.generate(); } catch(e) {}

        // 2. 이미 로그인된 세션 확인
        try {
            const session = await App.get('/api/student.php?action=check_session');
            if (session.logged_in) {
                // 형제 확인 (phone_last4 + 성씨)
                if (await checkSiblingsAndShow()) return;
                await recordAttendance();
                return;
            }
        } catch(e) {}

        // 3. 핑거프린트 자동 로그인 시도
        if (fp) {
            try {
                const autoResult = await App.post('/api/student.php?action=auto_login', { fingerprint: fp });
                if (autoResult.success && autoResult.found) {
                    if (autoResult.auto_login) {
                        // 단일 학생 → 형제 확인 후 출석
                        if (await checkSiblingsAndShow()) return;
                        await recordAttendance();
                        return;
                    } else if (autoResult.students && autoResult.students.length > 1) {
                        // 복수 디바이스 매칭 → 선택 화면
                        showSiblings(autoResult.students);
                        return;
                    }
                }
            } catch(e) {}
        }

        // 4. 자동 로그인 실패 → 반 선택
        showClassGrid();

        // ── 출석 기록 ──
        async function recordAttendance() {
            showState('loading');
            try {
                const result = await App.post('/api/attendance.php?action=record', {
                    session_code: SESSION_CODE,
                    fingerprint: fp
                });
                if (result.success) {
                    const name = result.student_name || '';
                    document.getElementById('success-name').textContent = name + '님 출석 완료!';
                    document.getElementById('success-desc').textContent =
                        result.already ? '이미 출석이 기록되어 있습니다' : '출석이 성공적으로 기록되었습니다';
                    showState('success');
                } else {
                    showError('⚠️', '출석 오류', result.error || '출석 처리에 실패했습니다');
                }
            } catch(e) {
                showError('❌', '네트워크 오류', '인터넷 연결을 확인해 주세요');
            }
        }

        // ── 형제 확인 (phone_last4 + 성씨) ──
        async function checkSiblingsAndShow() {
            try {
                const r = await App.get('/api/student.php?action=get_siblings');
                if (r.success && r.siblings && r.siblings.length > 1) {
                    showSiblings(r.siblings, r.current_student_id);
                    return true;
                }
            } catch(e) {}
            return false;
        }

        // ── 형제 선택 ──
        function showSiblings(students, currentStudentId) {
            const list = document.getElementById('sibling-list');
            list.innerHTML = students.map(s => {
                const isCurrent = currentStudentId && s.id == currentStudentId;
                return `
                <div class="scan-sibling-item${isCurrent ? ' current' : ''}" data-student-id="${s.id}">
                    <div class="scan-sibling-avatar">${s.name.charAt(0)}</div>
                    <div style="flex:1;">
                        <div style="font-weight:700; font-size:15px; color:#333;">${s.name}</div>
                        <div style="font-size:12px; color:#999; margin-top:2px;">${s.class_name || ''}${isCurrent ? ' <span style="color:#4CAF50; font-weight:600;">현재 로그인</span>' : ''}</div>
                    </div>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ccc" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                </div>`;
            }).join('');

            list.querySelectorAll('.scan-sibling-item').forEach(item => {
                item.addEventListener('click', async () => {
                    const studentId = parseInt(item.dataset.studentId);
                    showState('loading');
                    try {
                        if (currentStudentId && studentId === currentStudentId) {
                            await recordAttendance();
                        } else {
                            const result = await App.post('/api/student.php?action=choose_student', { student_id: studentId });
                            if (result.success) {
                                await recordAttendance();
                            } else {
                                showError('⚠️', '오류', result.error || '선택에 실패했습니다');
                            }
                        }
                    } catch(e) {
                        showError('❌', '네트워크 오류', '인터넷 연결을 확인해 주세요');
                    }
                });
            });

            showState('siblings');
        }

        // ── 반 선택 그리드 ──
        function showClassGrid() {
            const grid = document.getElementById('class-grid');
            grid.innerHTML = CLASSES.map(c => {
                const isQRClass = c.id == QR_CLASS_ID;
                return `
                    <div class="scan-class-item ${isQRClass ? 'qr-home' : ''}" data-class-id="${c.id}" data-class-name="${c.display_name}">
                        ${c.logo_image
                            ? `<img class="scan-class-logo" src="/images/${c.logo_image}" alt="${c.display_name}" loading="lazy">`
                            : `<div class="scan-class-logo-text" style="background:${c.color || '#FF7E17'};">${c.display_name.charAt(0)}</div>`
                        }
                        <div class="scan-class-name">${c.display_name}</div>
                    </div>
                `;
            }).join('');

            // 클릭 이벤트
            grid.querySelectorAll('.scan-class-item').forEach(item => {
                item.addEventListener('click', () => {
                    selectedClassId = parseInt(item.dataset.classId);
                    const className = item.dataset.className;
                    document.getElementById('login-class-title').textContent = className + ' 반 본인 확인';
                    document.getElementById('scan-name').value = '';
                    document.getElementById('scan-phone').value = '';
                    showState('login');
                    setTimeout(() => document.getElementById('scan-name').focus(), 300);
                });
            });

            showState('class');
        }

        // ── 로그인 ──
        const btnLogin = document.getElementById('btn-scan-login');
        const nameInput = document.getElementById('scan-name');
        const phoneInput = document.getElementById('scan-phone');

        btnLogin.addEventListener('click', doLogin);
        phoneInput.addEventListener('keyup', e => { if (e.key === 'Enter') doLogin(); });

        document.getElementById('btn-back-class').addEventListener('click', () => {
            showClassGrid();
        });

        async function doLogin() {
            const name = nameInput.value.trim();
            const phone = phoneInput.value.trim();

            if (!name) { Toast.warning('이름을 입력해 주세요'); nameInput.focus(); return; }
            if (!phone || phone.length !== 4 || !/^\d{4}$/.test(phone)) {
                Toast.warning('전화번호 뒷 4자리를 정확히 입력해 주세요');
                phoneInput.focus();
                return;
            }

            btnLogin.disabled = true;
            btnLogin.textContent = '처리 중...';

            try {
                const result = await App.post('/api/student.php?action=secure_login', {
                    class_id: selectedClassId,
                    name: name,
                    phone_last4: phone,
                    fingerprint: fp
                });

                if (result.success) {
                    Toast.success('본인 확인 완료!');
                    await recordAttendance();
                } else {
                    Toast.error(result.error || '로그인에 실패했습니다');
                    btnLogin.disabled = false;
                    btnLogin.textContent = '출석하기';
                }
            } catch(e) {
                Toast.error('네트워크 오류가 발생했습니다');
                btnLogin.disabled = false;
                btnLogin.textContent = '출석하기';
            }
        }
    })();
    </script>
<?php endif; ?>
</body>
</html>
