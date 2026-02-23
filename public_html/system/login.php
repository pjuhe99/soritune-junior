<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>시스템 관리자 - 소리튠 주니어</title>
    <link rel="stylesheet" href="/css/common.css?v=20260213c">
    <link rel="stylesheet" href="/css/toast.css?v=20260213c">
    <link rel="stylesheet" href="/css/admin.css?v=20260213c">
</head>
<body class="login-page">
    <div class="login-box">
        <div class="login-card">
            <div class="login-logo">
                <div class="login-logo-text" style="color:#37474F;">시스템 관리</div>
                <div class="login-logo-sub">소리튠 주니어 영어학교</div>
            </div>
            <div class="form-group">
                <label class="form-label">아이디</label>
                <input type="text" id="login-id" class="form-input" placeholder="관리자 아이디" autocomplete="username">
            </div>
            <div class="form-group">
                <label class="form-label">비밀번호</label>
                <input type="password" id="login-pw" class="form-input" placeholder="비밀번호" autocomplete="current-password">
            </div>
            <button class="btn btn-block btn-lg" id="btn-login" style="background:#37474F; color:#fff; margin-top:8px;">로그인</button>
        </div>
    </div>
    <!-- IP 자동 로그인 안내 배너 -->
    <div id="auto-login-banner" class="auto-login-banner" style="display:none;">
        <div class="auto-login-content">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#37474F" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            <div>
                <div class="auto-login-text">인증된 네트워크가 감지되었습니다</div>
                <div class="auto-login-sub">버튼을 눌러 자동으로 로그인하세요</div>
            </div>
        </div>
        <button class="btn btn-block" id="btn-auto-login" style="background:#37474F; color:#fff; margin-top:12px;">
            자동 로그인
        </button>
    </div>

    <style>
    .auto-login-banner {
        max-width: 380px;
        margin: 0 auto 20px;
        padding: 16px 20px;
        background: linear-gradient(135deg, #E8F5E9, #C8E6C9);
        border: 1.5px solid #A5D6A7;
        border-radius: 16px;
        box-shadow: 0 2px 12px rgba(76,175,80,0.15);
    }
    .auto-login-content {
        display: flex;
        align-items: flex-start;
        gap: 12px;
    }
    .auto-login-content svg { flex-shrink: 0; margin-top: 2px; }
    .auto-login-text { font-weight: 700; font-size: 14px; color: #333; }
    .auto-login-sub { font-size: 12px; color: #666; margin-top: 2px; }
    </style>

    <script src="/js/toast.js?v=20260213c"></script>
    <script src="/js/common.js?v=20260213c"></script>
    <script>
    document.getElementById('btn-login').addEventListener('click', doLogin);
    document.getElementById('login-pw').addEventListener('keyup', e => { if (e.key === 'Enter') doLogin(); });

    async function doLogin() {
        const loginId = document.getElementById('login-id').value.trim();
        const pw = document.getElementById('login-pw').value.trim();
        if (!loginId || !pw) { Toast.warning('아이디와 비밀번호를 입력해 주세요'); return; }

        App.showLoading();
        const result = await App.post('/api/system.php?action=login', { login_id: loginId, password: pw });
        App.hideLoading();

        if (result.success) {
            Toast.success('로그인 되었습니다');
            window.location.href = '/system/';
        }
    }

    // IP 자동 로그인 체크
    async function checkAutoLogin() {
        const r = await App.get('/api/system.php?action=check_ip_auto_login');
        if (r.success && r.allowed) {
            // 허용된 IP인 경우 자동으로 로그인 시도
            App.showLoading();
            const result = await App.post('/api/system.php?action=ip_auto_login');
            App.hideLoading();
            if (result.success) {
                Toast.success('자동 로그인 되었습니다');
                window.location.href = '/system/';
                return true;
            }
        }
        return false;
    }

    (async () => {
        const params = new URLSearchParams(location.search);
        const isLogout = params.get('logout') === '1';

        // 로그아웃 후 접속이면 토스트 표시
        if (isLogout) {
            Toast.info('로그아웃 되었습니다');
            history.replaceState(null, '', '/system/login.php');
        }

        const r = await App.get('/api/system.php?action=check_session');
        if (r.logged_in) {
            window.location.href = '/system/';
            return;
        }

        // IP 자동 로그인 시도
        const autoLoggedIn = await checkAutoLogin();
        if (!autoLoggedIn) {
            // 자동 로그인 실패 시 배너 표시 (혹시 모를 경우를 대비)
            // 배너는 제거하고 일반 로그인 폼만 표시
        }
    })();
    </script>
</body>
</html>
