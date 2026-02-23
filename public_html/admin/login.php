<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>관리자 로그인 - 소리튠 주니어</title>
    <link rel="stylesheet" href="/css/common.css">
    <link rel="stylesheet" href="/css/toast.css">
    <link rel="stylesheet" href="/css/admin.css">
</head>
<body class="login-page">
    <div class="login-box">
        <div class="login-card">
            <div class="login-logo">
                <div class="login-logo-text">소리튠 주니어</div>
                <div class="login-logo-sub">부모님 로그인</div>
            </div>
            <div class="form-group">
                <label class="form-label">아이디</label>
                <input type="text" id="login-id" class="form-input" placeholder="아이디" autocomplete="username">
            </div>
            <div class="form-group">
                <label class="form-label">비밀번호</label>
                <input type="password" id="login-pw" class="form-input" placeholder="비밀번호" autocomplete="current-password">
            </div>
            <button class="btn btn-block btn-lg" id="btn-login" style="background:#673AB7; color:#fff; margin-top:8px;">로그인</button>
            <div style="margin-top:20px; padding-top:16px; border-top:1px solid #E0E0E0; text-align:center;">
                <a href="/teacher/" style="display:inline-flex; align-items:center; gap:6px; color:#FF9800; font-size:14px; font-weight:600; text-decoration:none;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>
                    관리쌤 로그인은 여기로
                </a>
            </div>
        </div>
    </div>
    <script src="/js/toast.js"></script>
    <script src="/js/common.js"></script>
    <script>
    document.getElementById('btn-login').addEventListener('click', doLogin);
    document.getElementById('login-pw').addEventListener('keyup', e => { if (e.key === 'Enter') doLogin(); });

    async function doLogin() {
        const loginId = document.getElementById('login-id').value.trim();
        const pw = document.getElementById('login-pw').value.trim();
        if (!loginId || !pw) { Toast.warning('아이디와 비밀번호를 입력해 주세요'); return; }

        App.showLoading();
        const result = await App.post('/api/admin.php?action=login', { login_id: loginId, password: pw });
        App.hideLoading();

        if (result.success) {
            Toast.success('로그인 되었습니다');
            // admin_teacher는 전용 페이지로 (login API는 role 키 사용)
            if (result.admin && (result.admin.role === 'admin_teacher' || result.admin.admin_role === 'admin_teacher')) {
                window.location.href = '/teacher/';
            } else {
                window.location.href = '/admin/';
            }
        }
    }

    // 이미 로그인된 경우 리다이렉트
    (async () => {
        const r = await App.get('/api/admin.php?action=check_session');
        if (r.logged_in) {
            if (r.admin && r.admin.admin_role === 'admin_teacher') {
                window.location.href = '/teacher/';
            } else {
                window.location.href = '/admin/';
            }
        }
    })();
    </script>
</body>
</html>
