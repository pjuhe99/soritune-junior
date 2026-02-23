<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>시스템관리자 등록 - 소리튠 주니어</title>
    <link rel="stylesheet" href="/css/common.css">
    <link rel="stylesheet" href="/css/toast.css">
    <link rel="stylesheet" href="/css/admin.css">
    <style>
    .invite-container {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        background: linear-gradient(135deg, #37474F 0%, #263238 100%);
    }
    .invite-card {
        background: #fff;
        border-radius: 20px;
        padding: 32px 24px;
        max-width: 380px;
        width: 100%;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    }
    .invite-header {
        text-align: center;
        margin-bottom: 24px;
    }
    .invite-icon {
        width: 56px; height: 56px;
        background: linear-gradient(135deg, #E8F5E9, #C8E6C9);
        border-radius: 16px;
        display: flex; align-items: center; justify-content: center;
        margin: 0 auto 12px;
    }
    .invite-title {
        font-size: 18px;
        font-weight: 800;
        color: #333;
    }
    .invite-sub {
        font-size: 13px;
        color: #757575;
        margin-top: 4px;
    }
    .invite-expires {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: #FFF3E0;
        color: #E65100;
        font-size: 12px;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 20px;
        margin-top: 8px;
    }
    .invite-error {
        text-align: center;
        padding: 40px 20px;
    }
    .invite-error-icon {
        width: 64px; height: 64px;
        background: #FFEBEE;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        margin: 0 auto 16px;
    }
    .invite-error-title {
        font-size: 16px;
        font-weight: 700;
        color: #D32F2F;
        margin-bottom: 8px;
    }
    .invite-error-sub {
        font-size: 13px;
        color: #757575;
    }
    .invite-success {
        text-align: center;
        padding: 40px 20px;
    }
    .invite-success-icon {
        width: 64px; height: 64px;
        background: #E8F5E9;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        margin: 0 auto 16px;
    }
    .invite-success-title {
        font-size: 16px;
        font-weight: 700;
        color: #2E7D32;
        margin-bottom: 8px;
    }
    .invite-success-sub {
        font-size: 13px;
        color: #757575;
    }
    </style>
</head>
<body>
    <div class="invite-container">
        <div class="invite-card">
            <!-- 로딩 -->
            <div id="invite-loading" style="text-align:center; padding:40px 0;">
                <div class="loading-spinner" style="margin:0 auto;"></div>
                <p style="margin-top:12px; color:#757575; font-size:13px;">초대 링크를 확인하고 있습니다...</p>
            </div>

            <!-- 에러 -->
            <div id="invite-error" style="display:none;">
                <div class="invite-error">
                    <div class="invite-error-icon">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#D32F2F" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                    </div>
                    <div class="invite-error-title" id="error-title">유효하지 않은 링크</div>
                    <div class="invite-error-sub" id="error-sub">초대 링크가 만료되었거나 이미 사용되었습니다.</div>
                    <a href="/system/login.php" class="btn btn-block" style="background:#37474F; color:#fff; margin-top:20px; text-decoration:none;">로그인 페이지로 이동</a>
                </div>
            </div>

            <!-- 등록 폼 -->
            <div id="invite-form" style="display:none;">
                <div class="invite-header">
                    <div class="invite-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#2E7D32" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                    </div>
                    <div class="invite-title">시스템관리자 등록</div>
                    <div class="invite-sub">소리튠 주니어 영어학교</div>
                    <div class="invite-expires">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <span id="expires-text">남은 시간: --:--</span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">아이디 *</label>
                    <input type="text" id="reg-login" class="form-input" placeholder="영문, 숫자 조합" autocomplete="username">
                </div>
                <div class="form-group">
                    <label class="form-label">비밀번호 * (6자 이상)</label>
                    <input type="password" id="reg-password" class="form-input" placeholder="비밀번호" autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label class="form-label">비밀번호 확인 *</label>
                    <input type="password" id="reg-password2" class="form-input" placeholder="비밀번호 재입력" autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label class="form-label">이름 *</label>
                    <input type="text" id="reg-name" class="form-input" placeholder="관리자 이름">
                </div>
                <button class="btn btn-block btn-lg" id="btn-register" style="background:#37474F; color:#fff; margin-top:8px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-2px; margin-right:4px;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    계정 생성
                </button>
            </div>

            <!-- 성공 -->
            <div id="invite-success" style="display:none;">
                <div class="invite-success">
                    <div class="invite-success-icon">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#2E7D32" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    </div>
                    <div class="invite-success-title">계정이 생성되었습니다!</div>
                    <div class="invite-success-sub">시스템관리자 페이지로 이동합니다.</div>
                </div>
            </div>
        </div>
    </div>

    <script src="/js/toast.js"></script>
    <script src="/js/common.js"></script>
    <script>
    (async () => {
        const params = new URLSearchParams(location.search);
        const token = params.get('token');

        const loading = document.getElementById('invite-loading');
        const errorDiv = document.getElementById('invite-error');
        const formDiv = document.getElementById('invite-form');
        const successDiv = document.getElementById('invite-success');

        if (!token) {
            loading.style.display = 'none';
            errorDiv.style.display = 'block';
            document.getElementById('error-title').textContent = '토큰이 없습니다';
            document.getElementById('error-sub').textContent = '올바른 초대 링크를 사용해 주세요.';
            return;
        }

        // 토큰 확인
        const result = await App.get('/api/system.php?action=verify_invite', { token });

        loading.style.display = 'none';

        if (!result.success) {
            errorDiv.style.display = 'block';
            document.getElementById('error-title').textContent = '유효하지 않은 링크';
            document.getElementById('error-sub').textContent = result.message || '초대 링크가 만료되었거나 이미 사용되었습니다.';
            return;
        }

        formDiv.style.display = 'block';

        // 남은 시간 카운트다운
        const expiresAt = new Date(result.expires_at).getTime();
        const timerEl = document.getElementById('expires-text');

        function updateTimer() {
            const now = Date.now();
            const diff = expiresAt - now;
            if (diff <= 0) {
                timerEl.textContent = '만료됨';
                document.getElementById('btn-register').disabled = true;
                document.getElementById('btn-register').style.opacity = '0.5';
                return;
            }
            const min = Math.floor(diff / 60000);
            const sec = Math.floor((diff % 60000) / 1000);
            timerEl.textContent = `남은 시간: ${min}분 ${sec.toString().padStart(2, '0')}초`;
        }
        updateTimer();
        setInterval(updateTimer, 1000);

        // 등록 버튼
        document.getElementById('btn-register').addEventListener('click', async () => {
            const loginId = document.getElementById('reg-login').value.trim();
            const password = document.getElementById('reg-password').value.trim();
            const password2 = document.getElementById('reg-password2').value.trim();
            const name = document.getElementById('reg-name').value.trim();

            if (!loginId || !password || !name) {
                Toast.warning('모든 항목을 입력해 주세요');
                return;
            }
            if (password.length < 6) {
                Toast.warning('비밀번호는 6자 이상이어야 합니다');
                return;
            }
            if (password !== password2) {
                Toast.warning('비밀번호가 일치하지 않습니다');
                return;
            }

            App.showLoading();
            const res = await App.post('/api/system.php?action=register_via_invite', {
                token, login_id: loginId, password, name
            });
            App.hideLoading();

            if (res.success) {
                formDiv.style.display = 'none';
                successDiv.style.display = 'block';
                setTimeout(() => {
                    window.location.href = '/system/';
                }, 1500);
            }
        });

        // 엔터키
        document.querySelectorAll('#invite-form input').forEach(input => {
            input.addEventListener('keyup', e => {
                if (e.key === 'Enter') document.getElementById('btn-register').click();
            });
        });
    })();
    </script>
</body>
</html>
