<?php
require_once __DIR__ . '/../config.php';
$token = trim($_GET['token'] ?? '');
$clientIp = getClientIP();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>관리자 계정 생성 - 소리튠 주니어</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/variable/pretendardvariable-dynamic-subset.min.css">
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --primary: #22C55E;
            --primary-hover: #16A34A;
            --error: #EF4444;
            --success: #22C55E;
            --bg: #F5F5F5;
            --border: #E5E7EB;
            --text: #111827;
            --text-secondary: #6B7280;
        }

        body {
            font-family: 'Pretendard Variable', Pretendard, -apple-system, BlinkMacSystemFont, system-ui, Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            line-height: 1.5;
        }

        .card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
            max-width: 440px;
            width: 100%;
            padding: 40px 32px;
        }

        .card-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .card-header .site-name {
            font-size: 14px;
            font-weight: 600;
            color: var(--primary);
            letter-spacing: -0.02em;
            margin-bottom: 8px;
        }

        .card-header h1 {
            font-size: 22px;
            font-weight: 700;
            color: var(--text);
            letter-spacing: -0.03em;
        }

        .info-box {
            background: #F9FAFB;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 24px;
            font-size: 13px;
            color: var(--text-secondary);
            line-height: 1.7;
        }

        .info-box .info-row {
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }

        .info-box .info-row + .info-row {
            margin-top: 4px;
        }

        .info-box .info-label {
            color: var(--text);
            font-weight: 600;
            white-space: nowrap;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 6px;
        }

        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-size: 15px;
            font-family: inherit;
            color: var(--text);
            background: #fff;
            outline: none;
            transition: border-color 0.2s;
        }

        .form-group input:focus {
            border-color: var(--primary);
        }

        .form-group input::placeholder {
            color: #D1D5DB;
        }

        .form-group .field-hint {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 4px;
        }

        .btn-submit {
            width: 100%;
            height: 48px;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            transition: background 0.2s, opacity 0.2s;
            margin-top: 8px;
        }

        .btn-submit:hover {
            background: var(--primary-hover);
        }

        .btn-submit:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Loading state */
        .loading-container {
            text-align: center;
            padding: 40px 0;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid var(--border);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto 16px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .loading-text {
            font-size: 14px;
            color: var(--text-secondary);
        }

        /* Error message */
        .error-container {
            text-align: center;
        }

        .error-icon {
            width: 56px;
            height: 56px;
            background: var(--error);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
        }

        .error-icon svg {
            width: 28px;
            height: 28px;
            color: #fff;
        }

        .error-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 8px;
        }

        .error-message {
            font-size: 14px;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        /* Success message */
        .success-container {
            text-align: center;
        }

        .success-icon {
            width: 56px;
            height: 56px;
            background: var(--success);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
        }

        .success-icon svg {
            width: 28px;
            height: 28px;
            color: #fff;
        }

        .success-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 8px;
        }

        .success-message {
            font-size: 14px;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        .success-redirect {
            margin-top: 16px;
            font-size: 13px;
            color: var(--text-secondary);
        }

        /* Toast notification */
        .toast {
            position: fixed;
            top: 24px;
            left: 50%;
            transform: translateX(-50%) translateY(-100px);
            background: var(--error);
            color: #fff;
            padding: 12px 24px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            transition: transform 0.3s ease;
            max-width: 400px;
            text-align: center;
        }

        .toast.show {
            transform: translateX(-50%) translateY(0);
        }

        /* Hidden utility */
        .hidden {
            display: none !important;
        }
    </style>
</head>
<body>
    <div class="card">
        <!-- Loading State -->
        <div id="loadingState" class="loading-container">
            <div class="spinner"></div>
            <div class="loading-text">토큰을 확인하고 있습니다...</div>
        </div>

        <!-- Error State -->
        <div id="errorState" class="error-container hidden">
            <div class="error-icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </div>
            <div class="error-title">유효하지 않은 링크</div>
            <div id="errorMessage" class="error-message">이 설정 링크는 만료되었거나 유효하지 않습니다.</div>
        </div>

        <!-- Form State -->
        <div id="formState" class="hidden">
            <div class="card-header">
                <div class="site-name">소리튠 주니어</div>
                <h1>관리자 계정 생성</h1>
            </div>

            <div id="tokenInfo" class="info-box"></div>

            <form id="setupForm" autocomplete="off">
                <div class="form-group">
                    <label for="login_id">아이디</label>
                    <input type="text" id="login_id" name="login_id" placeholder="영문, 숫자 3자 이상" minlength="3" required>
                    <div class="field-hint">영문, 숫자 조합 3자 이상</div>
                </div>

                <div class="form-group">
                    <label for="password">비밀번호</label>
                    <input type="password" id="password" name="password" placeholder="6자 이상 입력" minlength="6" required>
                    <div class="field-hint">6자 이상</div>
                </div>

                <div class="form-group">
                    <label for="password_confirm">비밀번호 확인</label>
                    <input type="password" id="password_confirm" name="password_confirm" placeholder="비밀번호를 다시 입력" minlength="6" required>
                </div>

                <div class="form-group">
                    <label for="name">이름</label>
                    <input type="text" id="name" name="name" placeholder="관리자 이름" required>
                </div>

                <button type="submit" class="btn-submit" id="submitBtn">계정 생성</button>
            </form>
        </div>

        <!-- Success State -->
        <div id="successState" class="success-container hidden">
            <div class="success-icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            </div>
            <div class="success-title">관리자 계정이 생성되었습니다</div>
            <div class="success-message">이제 생성된 계정으로 로그인할 수 있습니다.</div>
            <div class="success-redirect">잠시 후 로그인 페이지로 이동합니다...</div>
        </div>
    </div>

    <!-- Toast -->
    <div id="toast" class="toast"></div>

    <script>
        const API_PATH = '/api/system.php';
        const TOKEN = <?php echo json_encode($token); ?>;
        const CLIENT_IP = <?php echo json_encode($clientIp); ?>;

        const $loading = document.getElementById('loadingState');
        const $error = document.getElementById('errorState');
        const $errorMsg = document.getElementById('errorMessage');
        const $form = document.getElementById('formState');
        const $success = document.getElementById('successState');
        const $toast = document.getElementById('toast');
        const $setupForm = document.getElementById('setupForm');
        const $submitBtn = document.getElementById('submitBtn');
        const $tokenInfo = document.getElementById('tokenInfo');

        function showState(state) {
            [$loading, $error, $form, $success].forEach(el => el.classList.add('hidden'));
            state.classList.remove('hidden');
        }

        function showToast(message, duration = 3000) {
            $toast.textContent = message;
            $toast.classList.add('show');
            setTimeout(() => $toast.classList.remove('show'), duration);
        }

        function formatDateTime(dateStr) {
            if (!dateStr) return '';
            const d = new Date(dateStr);
            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            const hour = String(d.getHours()).padStart(2, '0');
            const min = String(d.getMinutes()).padStart(2, '0');
            return `${year}-${month}-${day} ${hour}:${min}`;
        }

        async function validateToken() {
            if (!TOKEN) {
                showState($error);
                $errorMsg.textContent = '토큰이 제공되지 않았습니다.';
                return;
            }

            try {
                const res = await fetch(`${API_PATH}?action=validate_setup_token&token=${encodeURIComponent(TOKEN)}`);
                const data = await res.json();

                if (!data.success) {
                    showState($error);
                    $errorMsg.textContent = data.message || '이 설정 링크는 만료되었거나 유효하지 않습니다.';
                    return;
                }

                // Populate token info
                const info = data.data || data;
                let infoHtml = '';

                if (info.expires_at) {
                    infoHtml += `<div class="info-row"><span class="info-label">만료:</span> <span>${formatDateTime(info.expires_at)}에 만료됩니다</span></div>`;
                }
                if (info.created_by_name) {
                    infoHtml += `<div class="info-row"><span class="info-label">생성자:</span> <span>${info.created_by_name}</span></div>`;
                }
                infoHtml += `<div class="info-row"><span class="info-label">현재 IP:</span> <span>${CLIENT_IP} (자동 등록됨)</span></div>`;

                $tokenInfo.innerHTML = infoHtml;
                showState($form);

            } catch (err) {
                showState($error);
                $errorMsg.textContent = '서버에 연결할 수 없습니다. 잠시 후 다시 시도해주세요.';
            }
        }

        $setupForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const loginId = document.getElementById('login_id').value.trim();
            const password = document.getElementById('password').value;
            const passwordConfirm = document.getElementById('password_confirm').value;
            const name = document.getElementById('name').value.trim();

            // Client-side validation
            if (loginId.length < 3) {
                showToast('아이디는 3자 이상이어야 합니다.');
                return;
            }

            if (password.length < 6) {
                showToast('비밀번호는 6자 이상이어야 합니다.');
                return;
            }

            if (password !== passwordConfirm) {
                showToast('비밀번호가 일치하지 않습니다.');
                return;
            }

            if (!name) {
                showToast('이름을 입력해주세요.');
                return;
            }

            $submitBtn.disabled = true;
            $submitBtn.textContent = '생성 중...';

            try {
                const res = await fetch(`${API_PATH}?action=use_setup_token`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        token: TOKEN,
                        login_id: loginId,
                        password: password,
                        name: name
                    })
                });

                const data = await res.json();

                if (!data.success) {
                    showToast(data.message || '계정 생성에 실패했습니다.');
                    $submitBtn.disabled = false;
                    $submitBtn.textContent = '계정 생성';
                    return;
                }

                showState($success);

                setTimeout(() => {
                    window.location.href = '/system/';
                }, 3000);

            } catch (err) {
                showToast('서버에 연결할 수 없습니다. 잠시 후 다시 시도해주세요.');
                $submitBtn.disabled = false;
                $submitBtn.textContent = '계정 생성';
            }
        });

        // Start validation on load
        validateToken();
    </script>
</body>
</html>
