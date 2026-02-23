<?php
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#2196F3">
    <title>코치 대시보드 - 소리튠 주니어</title>
    <link rel="stylesheet" href="/css/common.css?v=20260219d">
    <link rel="stylesheet" href="/css/toast.css?v=20260219d">
    <link rel="stylesheet" href="/css/coach.css?v=20260222">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="/css/admin-dock.css?v=20260219d">
</head>
<body class="coach-page">
    <div class="app-container" id="app">
        <!-- 로그인 -->
        <div id="view-login">
            <div class="coach-login-container">
                <div class="coach-login-logo">
                    <div style="font-size:48px; margin-bottom:8px;">👩‍🏫</div>
                    <div style="font-size:24px; font-weight:800; color:#2196F3;">코치쌤 로그인</div>
                    <div style="font-size:13px; color:#9E9E9E; margin-top:4px;">소리튠 주니어 영어학교</div>
                </div>

                <!-- 시스템관리자 대행 로그인 패널 -->
                <div id="impersonate-panel" style="display:none;">
                    <div style="background:linear-gradient(135deg,#E8F5E9,#C8E6C9); border:1.5px solid #A5D6A7; border-radius:14px; padding:14px; margin-bottom:16px;">
                        <div style="font-weight:700; font-size:14px; color:#2E7D32; margin-bottom:4px;">시스템관리자 모드</div>
                        <div style="font-size:12px; color:#4CAF50;">코치쌤을 선택하면 자동으로 로그인됩니다</div>
                    </div>
                    <div id="coach-list-panel" style="display:flex; flex-direction:column; gap:8px;"></div>
                    <div style="border-top:1px solid #eee; margin:16px 0 12px; padding-top:12px;">
                        <div style="font-size:12px; color:#999; margin-bottom:8px;">또는 직접 로그인</div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">아이디</label>
                    <input type="text" id="login-id" class="form-input" placeholder="아이디를 입력하세요" autocomplete="username">
                </div>
                <div class="form-group">
                    <label class="form-label">비밀번호</label>
                    <input type="password" id="login-pw" class="form-input" placeholder="비밀번호를 입력하세요" autocomplete="current-password">
                </div>
                <button class="btn btn-primary btn-block btn-lg" id="btn-login" style="background:#2196F3; margin-top:8px;">로그인</button>
            </div>
        </div>

        <!-- 대시보드 -->
        <div id="view-dashboard" class="hidden">
            <div class="app-header">
                <h1 id="coach-title">코치 대시보드</h1>
                <div style="display:flex; align-items:center; gap:12px;">
                    <button class="back-btn" id="btn-change-pw" title="비밀번호 변경">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    </button>
                    <button class="back-btn" id="btn-coach-logout">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    </button>
                </div>
            </div>

            <!-- 비밀번호 변경 모달 -->
            <div id="pw-change-modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; z-index:9999; background:rgba(0,0,0,0.5); align-items:center; justify-content:center;">
                <div style="background:#fff; border-radius:16px; padding:24px; width:90%; max-width:360px; box-shadow:0 8px 32px rgba(0,0,0,0.2);">
                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:20px;">
                        <div style="font-size:18px; font-weight:700; color:#333;">비밀번호 변경</div>
                        <button id="pw-modal-close" style="background:none; border:none; font-size:24px; color:#999; cursor:pointer; padding:0; line-height:1;">&times;</button>
                    </div>
                    <div class="form-group" style="margin-bottom:12px;">
                        <label class="form-label">현재 비밀번호</label>
                        <input type="password" id="pw-current" class="form-input" placeholder="현재 비밀번호" autocomplete="current-password">
                    </div>
                    <div class="form-group" style="margin-bottom:12px;">
                        <label class="form-label">새 비밀번호</label>
                        <input type="password" id="pw-new" class="form-input" placeholder="새 비밀번호 (4자 이상)" autocomplete="new-password">
                    </div>
                    <div class="form-group" style="margin-bottom:20px;">
                        <label class="form-label">새 비밀번호 확인</label>
                        <input type="password" id="pw-confirm" class="form-input" placeholder="새 비밀번호 다시 입력" autocomplete="new-password">
                    </div>
                    <button class="btn btn-primary btn-block" id="btn-pw-submit" style="background:#2196F3;">변경하기</button>
                </div>
            </div>

            <!-- 반 선택 (다중 반 담당 시) -->
            <div id="class-selector" class="hidden" style="padding:12px 16px; background:#E3F2FD;">
                <select id="class-select" class="form-input" style="padding:8px 12px;"></select>
            </div>

            <div style="padding:16px;">
                <!-- 탭 -->
                <div class="tabs" id="coach-tabs">
                    <button class="tab-btn active" data-tab="overview">반 현황</button>
                    <button class="tab-btn" data-tab="homework">과제 현황</button>
                    <button class="tab-btn" data-tab="checklist">체크리스트</button>
                    <button class="tab-btn" data-tab="qr">QR 출석</button>
                    <button class="tab-btn" data-tab="profile">학생 프로필</button>
                    <button class="tab-btn" data-tab="ace">테스트 평가</button>
                </div>

                <!-- 탭1: 반 현황 -->
                <div class="tab-content active" id="tab-overview">
                    <div id="overview-list"></div>
                </div>

                <!-- 탭2: 체크리스트 -->
                <div class="tab-content" id="tab-checklist">
                    <div class="checklist-date-nav">
                        <button class="checklist-date-btn" id="date-prev">◀</button>
                        <div class="checklist-current-date" id="checklist-date"></div>
                        <button class="checklist-date-btn" id="date-next">▶</button>
                    </div>
                    <div id="checklist-content"></div>
                    <div class="checklist-actions">
                        <button class="btn btn-primary" id="btn-save-checklist" style="background:#2196F3;">저장</button>
                    </div>
                </div>

                <!-- 탭3: QR 출석 -->
                <div class="tab-content" id="tab-qr">
                    <div class="qr-section" id="qr-content"></div>
                </div>

                <!-- 탭4: 학생 프로필 -->
                <div class="tab-content" id="tab-profile">
                    <div id="profile-search" style="margin-bottom:16px;">
                        <select id="profile-student-select" class="form-input">
                            <option value="">학생을 선택하세요</option>
                        </select>
                    </div>
                    <div id="profile-content"></div>
                </div>

                <!-- 탭5: 소리과제 현황 -->
                <div class="tab-content" id="tab-homework">
                    <div id="hw-content"></div>
                </div>

                <!-- 탭6: ACE 평가 -->
                <div class="tab-content" id="tab-ace">
                    <div id="ace-content"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="/js/toast.js?v=20260219d"></script>
    <script src="/js/common.js?v=20260219d"></script>
    <script src="/js/fingerprint.js?v=20260219d"></script>
    <script>
    // 시스템관리자 대행 로그인 체크 (시스템관리자 세션이 있으면 항상 표시)
    (async () => {
        try {
            const r = await App.get('/api/system.php?action=check_session');
            if (r.logged_in) {
                const panel = document.getElementById('impersonate-panel');
                const listEl = document.getElementById('coach-list-panel');
                panel.style.display = 'block';

                const cr = await App.get('/api/system.php?action=coach_list');
                if (cr.success && cr.coaches) {
                    listEl.innerHTML = cr.coaches.map(c => `
                        <button onclick="impersonateCoach(${c.id})" style="display:flex; align-items:center; gap:10px; width:100%; padding:12px 14px; border:1.5px solid #E3F2FD; border-radius:12px; background:#fff; cursor:pointer; text-align:left; transition:all .15s; font-family:inherit;">
                            <div style="width:36px; height:36px; background:#E3F2FD; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; color:#1565C0; flex-shrink:0;">${c.name.charAt(0)}</div>
                            <div style="flex:1; min-width:0;">
                                <div style="font-weight:700; font-size:14px; color:#333;">${c.name}</div>
                                <div style="font-size:11px; color:#999; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${c.class_names || '반 미배정'}</div>
                            </div>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                        </button>
                    `).join('');
                }
            }
        } catch(e) {}
    })();

    async function impersonateCoach(coachId) {
        App.showLoading();
        const result = await App.post('/api/system.php?action=impersonate_coach', { coach_id: coachId });
        App.hideLoading();
        if (result.success) {
            Toast.success(result.message);
            setTimeout(() => location.reload(), 500);
        }
    }
    </script>
    <script src="/js/coach.js?v=20260222"></script>
    <script src="/js/admin-dock.js?v=20260220c" data-adock-active="coach"></script>
</body>
</html>
