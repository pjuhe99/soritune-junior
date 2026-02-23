<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#FF6B1A">
    <title>로그인 - 소리튠 주니어</title>
    <link rel="icon" type="image/svg+xml" href="/images/favicon.svg">
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/variable/pretendardvariable-dynamic-subset.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/css/common.css?v=20260220d">
    <link rel="stylesheet" href="/css/toast.css?v=20260220d">
    <style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body {
        font-family:'Pretendard Variable',Pretendard,-apple-system,BlinkMacSystemFont,sans-serif;
        min-height:100vh; min-height:100dvh;
        background-color:#FF6B1A;
        display:flex; align-items:flex-start; justify-content:center;
        padding:60px 16px 24px;
        transition:background-color .4s ease;
    }
    .login-wrap {
        width:100%; max-width:420px;
    }
    /* 로고 */
    .login-logo {
        text-align:center; margin-bottom:24px; color:#fff;
    }
    .login-logo-icon {
        width:56px; height:56px; border-radius:16px; background:rgba(255,255,255,.2);
        display:inline-flex; align-items:center; justify-content:center;
        font-size:28px; font-weight:900; color:#fff; backdrop-filter:blur(8px);
        margin-bottom:8px;
    }
    .login-logo-title {
        font-size:22px; font-weight:800; letter-spacing:-.5px;
    }
    .login-logo-sub {
        font-size:13px; opacity:.8; margin-top:2px;
    }
    /* 카드 */
    .login-card {
        background:#fff; border-radius:20px; padding:24px 20px 28px;
        box-shadow:0 12px 40px rgba(0,0,0,.15);
    }
    /* 역할 탭 */
    .role-tabs {
        display:flex; gap:4px; padding:4px; background:#F5F5F5; border-radius:12px;
        margin-bottom:24px;
    }
    .role-tab {
        flex:1; padding:10px 4px; border:none; border-radius:10px;
        background:transparent; cursor:pointer; font-size:13px; font-weight:600;
        color:#999; transition:all .25s; text-align:center;
        font-family:inherit;
    }
    .role-tab.active {
        background:#fff; color:var(--tab-color,#333);
        box-shadow:0 2px 8px rgba(0,0,0,.08);
    }
    .role-tab[data-role="student"] { --tab-color:#FF6B1A; }
    .role-tab[data-role="parent"] { --tab-color:#673AB7; }
    .role-tab[data-role="teacher"] { --tab-color:#FF9800; }
    .role-tab[data-role="coach"] { --tab-color:#2196F3; }
    /* 폼 공통 */
    .role-form { display:none; }
    .role-form.active { display:block; }
    .form-title {
        font-size:16px; font-weight:700; margin-bottom:16px; color:#333;
    }
    .form-group { margin-bottom:14px; }
    .form-label {
        display:block; font-size:13px; font-weight:600; color:#555;
        margin-bottom:6px;
    }
    .form-input {
        width:100%; padding:12px 14px; border:1.5px solid #E0E0E0;
        border-radius:10px; font-size:15px; font-family:inherit;
        transition:border-color .2s; outline:none; background:#FAFAFA;
    }
    .form-input:focus {
        border-color:#FF6B1A; background:#fff;
        box-shadow:0 0 0 3px rgba(255,107,26,.1);
    }
    .form-hint {
        font-size:12px; color:#9E9E9E; margin-bottom:14px;
    }
    .btn-login {
        width:100%; padding:14px; border:none; border-radius:12px;
        font-size:16px; font-weight:700; color:#fff; cursor:pointer;
        transition:all .2s; font-family:inherit;
    }
    .btn-login:active { transform:scale(.98); }
    .btn-login[data-role="student"] { background:#FF6B1A; }
    .btn-login[data-role="parent"] { background:#673AB7; }
    .btn-login[data-role="teacher"] { background:#FF9800; }
    .btn-login[data-role="coach"] { background:#2196F3; }
    .btn-login:disabled { opacity:.5; cursor:not-allowed; }
    /* 반 선택 그리드 */
    .class-grid {
        display:grid; grid-template-columns:repeat(2,1fr); gap:8px;
        margin-bottom:16px;
    }
    .class-btn {
        padding:12px 8px; border:2px solid #E8E8E8; border-radius:12px;
        background:#fff; cursor:pointer; text-align:center; transition:all .2s;
        font-family:inherit;
    }
    .class-btn:hover { border-color:#FF6B1A; background:#FFF3E0; }
    .class-btn.selected {
        border-color:#FF6B1A; background:#FFF3E0;
        box-shadow:0 0 0 3px rgba(255,107,26,.15);
    }
    .class-btn-name {
        font-size:15px; font-weight:700; color:#333;
    }
    .class-btn-count {
        font-size:11px; color:#999; margin-top:2px;
    }
    /* 학생 로그인 2단계 */
    .student-step2 { display:none; }
    .student-step2.active { display:block; }
    .step-back {
        display:inline-flex; align-items:center; gap:4px;
        background:none; border:none; color:#999; font-size:13px;
        cursor:pointer; margin-bottom:12px; padding:0; font-family:inherit;
    }
    .step-back:hover { color:#555; }
    /* 홈 링크 */
    .login-home {
        text-align:center; margin-top:16px;
    }
    .login-home a {
        color:rgba(255,255,255,.8); font-size:13px; text-decoration:none;
    }
    .login-home a:hover { color:#fff; text-decoration:underline; }
    </style>
</head>
<body>
    <div class="login-wrap">
        <div class="login-logo">
            <div class="login-logo-icon">S</div>
            <div class="login-logo-title">SoriTune Junior</div>
            <div class="login-logo-sub">소리튠 주니어 로그인</div>
        </div>

        <div class="login-card">
            <!-- 역할 선택 탭 -->
            <div class="role-tabs">
                <button class="role-tab active" data-role="student">학생</button>
                <button class="role-tab" data-role="parent">학부모</button>
                <button class="role-tab" data-role="teacher">관리쌤</button>
                <button class="role-tab" data-role="coach">담임쌤</button>
            </div>

            <!-- 학생 로그인 -->
            <div class="role-form active" id="form-student">
                <div id="student-step1">
                    <div class="form-title">나의 반을 골라봐!</div>
                    <div class="class-grid" id="class-grid">
                        <div style="grid-column:1/-1;text-align:center;color:#999;padding:20px;font-size:14px;">
                            가져오는 중...
                        </div>
                    </div>
                </div>
                <div class="student-step2" id="student-step2">
                    <button class="step-back" id="btn-step-back">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                        다른 반 선택
                    </button>
                    <div class="form-title" id="selected-class-name">본인 확인</div>
                    <div class="form-group">
                        <label class="form-label">이름</label>
                        <input type="text" class="form-input" id="stu-name" placeholder="이름을 써줘" autocomplete="off">
                    </div>
                    <div class="form-group">
                        <label class="form-label">전화번호 뒷 4자리</label>
                        <input type="tel" class="form-input" id="stu-phone" maxlength="4" placeholder="0000" inputmode="numeric" autocomplete="off">
                    </div>
                    <div class="form-hint">소리튠영어에 등록된 전화번호 뒷 4자리를 넣어줘</div>
                    <button class="btn-login" data-role="student" id="btn-student-login">로그인</button>
                </div>
            </div>

            <!-- 학부모 로그인 -->
            <div class="role-form" id="form-parent">
                <div class="form-title">학부모 로그인</div>
                <div class="form-group">
                    <label class="form-label">전화번호</label>
                    <input type="tel" class="form-input" id="parent-phone" placeholder="전화번호 또는 뒷 4자리" autocomplete="tel" inputmode="tel">
                </div>
                <div class="form-hint">소리튠영어에 등록된 전화번호를 입력하세요</div>
                <button class="btn-login" data-role="parent" id="btn-parent-login">로그인</button>
            </div>

            <!-- 관리쌤 로그인 -->
            <div class="role-form" id="form-teacher">
                <div class="form-title">관리쌤 로그인</div>
                <div class="form-group">
                    <label class="form-label">전화번호 뒷 4자리</label>
                    <input type="tel" class="form-input" id="teacher-phone" maxlength="4" placeholder="0000" inputmode="numeric" autocomplete="tel">
                </div>
                <div class="form-hint">소리튠영어에 등록된 전화번호 뒷 4자리를 입력하세요</div>
                <button class="btn-login" data-role="teacher" id="btn-teacher-login">로그인</button>
            </div>

            <!-- 담임쌤 로그인 -->
            <div class="role-form" id="form-coach">
                <div class="form-title">담임쌤 로그인</div>
                <div class="form-group">
                    <label class="form-label">아이디</label>
                    <input type="text" class="form-input" id="coach-id" placeholder="아이디를 입력하세요" autocomplete="username">
                </div>
                <div class="form-group">
                    <label class="form-label">비밀번호</label>
                    <input type="password" class="form-input" id="coach-pw" placeholder="비밀번호를 입력하세요" autocomplete="current-password">
                </div>
                <button class="btn-login" data-role="coach" id="btn-coach-login">로그인</button>
            </div>
        </div>

        <div class="login-home">
            <a href="/">← 🏠 홈</a>
        </div>
    </div>

    <script src="/js/toast.js?v=20260220d"></script>
    <script src="/js/common.js?v=20260220d"></script>
    <script src="/js/fingerprint.js?v=20260220d"></script>
    <script>
    (async function() {
        let fingerprint = null;
        let selectedClassId = null;
        let classesData = [];

        // 핑거프린트 생성
        try {
            fingerprint = await DeviceFingerprint.generate();
        } catch(e) {}

        // 반 목록 미리 로드
        loadClasses();

        // ============================================
        // 역할 탭 전환
        // ============================================
        document.querySelectorAll('.role-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                const role = tab.dataset.role;
                document.querySelectorAll('.role-tab').forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                document.querySelectorAll('.role-form').forEach(f => f.classList.remove('active'));
                document.getElementById('form-' + role).classList.add('active');

                // 배경색 전환 (transition으로 부드럽게)
                const colors = {
                    student: '#FF6B1A',
                    parent:  '#673AB7',
                    teacher: '#FF9800',
                    coach:   '#2196F3',
                };
                document.body.style.backgroundColor = colors[role];

                // focus 색상
                const focusColors = {
                    student:'#FF6B1A', parent:'#673AB7', teacher:'#FF9800', coach:'#2196F3'
                };
                document.documentElement.style.setProperty('--focus-color', focusColors[role]);
            });
        });

        // focus 색상 동적 적용
        const style = document.createElement('style');
        style.textContent = '.form-input:focus { border-color:var(--focus-color,#FF6B1A) !important; box-shadow:0 0 0 3px color-mix(in srgb, var(--focus-color,#FF6B1A) 15%, transparent) !important; }';
        document.head.appendChild(style);

        // ============================================
        // 학생 로그인
        // ============================================
        async function loadClasses() {
            try {
                const result = await App.get('/api/student.php?action=classes');
                if (result.success && result.classes) {
                    classesData = result.classes;
                    renderClasses();
                }
            } catch(e) {
                document.getElementById('class-grid').innerHTML =
                    '<div style="grid-column:1/-1;text-align:center;color:#E53935;padding:20px;font-size:14px;">🔧 잠깐 문제가 생겼어. 다시 해볼까?</div>';
            }
        }

        function renderClasses() {
            const grid = document.getElementById('class-grid');
            if (!classesData.length) {
                grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;color:#999;padding:20px;font-size:14px;">아직 반이 없어!</div>';
                return;
            }
            grid.innerHTML = classesData.map(c => `
                <button class="class-btn" data-class-id="${c.id}">
                    <div class="class-btn-name">${c.display_name}</div>
                    ${c.coach_name ? `<div class="class-btn-count">${c.coach_name}</div>` : ''}
                </button>
            `).join('');

            grid.querySelectorAll('.class-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    selectedClassId = parseInt(btn.dataset.classId);
                    const className = btn.querySelector('.class-btn-name').textContent;
                    document.getElementById('selected-class-name').textContent = className + ' 반 본인 확인';
                    document.getElementById('student-step1').style.display = 'none';
                    const step2 = document.getElementById('student-step2');
                    step2.classList.add('active');
                    document.getElementById('stu-name').value = '';
                    document.getElementById('stu-phone').value = '';
                    setTimeout(() => document.getElementById('stu-name').focus(), 200);
                });
            });
        }

        document.getElementById('btn-step-back').addEventListener('click', () => {
            selectedClassId = null;
            document.getElementById('student-step2').classList.remove('active');
            document.getElementById('student-step1').style.display = 'block';
        });

        document.getElementById('btn-student-login').addEventListener('click', doStudentLogin);
        document.getElementById('stu-phone').addEventListener('keyup', e => { if (e.key === 'Enter') doStudentLogin(); });

        async function doStudentLogin() {
            const name = document.getElementById('stu-name').value.trim();
            const phoneLast4 = document.getElementById('stu-phone').value.trim();

            if (!name) { Toast.warning('이름을 써줘!'); document.getElementById('stu-name').focus(); return; }
            if (!phoneLast4 || phoneLast4.length !== 4) { Toast.warning('전화번호 뒷 4자리를 넣어줘!'); document.getElementById('stu-phone').focus(); return; }
            if (!selectedClassId) { Toast.warning('반을 먼저 골라줘!'); return; }

            App.showLoading();

            // 1단계: 이름 + 뒷4자리로 학생 찾기
            const result = await App.post('/api/student.php?action=resolve_name', {
                class_id: selectedClassId,
                name: name,
                phone_last4: phoneLast4,
            });

            if (!result.success) { App.hideLoading(); return; }

            // 2단계: 로그인
            const deviceInfo = DeviceFingerprint.getDeviceInfo();
            const loginResult = await App.post('/api/student.php?action=qr_login', {
                class_id: selectedClassId,
                student_id: result.student.id,
                fingerprint: fingerprint || '',
                device_info: deviceInfo,
            });

            App.hideLoading();

            if (loginResult.success) {
                Toast.success(loginResult.student.name + ', 반가워!');
                setTimeout(() => { window.location.href = '/'; }, 500);
            }
        }

        // ============================================
        // 학부모 로그인
        // ============================================
        document.getElementById('btn-parent-login').addEventListener('click', doParentLogin);
        document.getElementById('parent-phone').addEventListener('keyup', e => { if (e.key === 'Enter') doParentLogin(); });

        async function doParentLogin() {
            const phone = document.getElementById('parent-phone').value.trim();
            if (!phone) { Toast.warning('전화번호를 입력해 주세요'); return; }

            App.showLoading();
            const deviceInfo = DeviceFingerprint.getDeviceInfo();
            const result = await App.post('/api/admin.php?action=phone_login', {
                phone: phone,
                fingerprint: fingerprint || '',
                device_info: deviceInfo,
            });
            App.hideLoading();

            if (result.success) {
                Toast.success(result.admin.name + ' 환영합니다!');
                setTimeout(() => { window.location.href = '/admin/'; }, 500);
            }
        }

        // ============================================
        // 관리쌤 로그인
        // ============================================
        document.getElementById('btn-teacher-login').addEventListener('click', doTeacherLogin);
        document.getElementById('teacher-phone').addEventListener('keyup', e => { if (e.key === 'Enter') doTeacherLogin(); });

        async function doTeacherLogin() {
            const phoneLast4 = document.getElementById('teacher-phone').value.trim();
            if (!phoneLast4 || phoneLast4.length !== 4) { Toast.warning('전화번호 뒷 4자리를 입력해 주세요'); return; }

            App.showLoading();
            const deviceInfo = DeviceFingerprint.getDeviceInfo();
            const result = await App.post('/api/coach.php?action=teacher_login', {
                phone_last4: phoneLast4,
                fingerprint: fingerprint || '',
                device_info: deviceInfo,
            });
            App.hideLoading();

            if (result.success) {
                Toast.success(result.message);
                setTimeout(() => { window.location.href = '/teacher/'; }, 500);
            }
        }

        // ============================================
        // 담임쌤 로그인
        // ============================================
        document.getElementById('btn-coach-login').addEventListener('click', doCoachLogin);
        document.getElementById('coach-pw').addEventListener('keyup', e => { if (e.key === 'Enter') doCoachLogin(); });

        async function doCoachLogin() {
            const loginId = document.getElementById('coach-id').value.trim();
            const password = document.getElementById('coach-pw').value.trim();
            if (!loginId || !password) { Toast.warning('아이디와 비밀번호를 입력해 주세요'); return; }

            App.showLoading();
            const deviceInfo = DeviceFingerprint.getDeviceInfo();
            const result = await App.post('/api/coach.php?action=login', {
                login_id: loginId,
                password: password,
                fingerprint: fingerprint || '',
                device_info: deviceInfo,
            });
            App.hideLoading();

            if (result.success) {
                Toast.success(result.admin.name + ' 코치님 환영합니다!');
                setTimeout(() => { window.location.href = '/coach/'; }, 500);
            }
        }
    })();
    </script>
</body>
</html>
