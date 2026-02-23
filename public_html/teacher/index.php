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
    <meta name="theme-color" content="#FF9800">
    <title>관리쌤 대시보드 - 소리튠 주니어</title>
    <link rel="stylesheet" href="/css/common.css?v=20260220b">
    <link rel="stylesheet" href="/css/toast.css?v=20260220b">
    <link rel="stylesheet" href="/css/teacher.css?v=20260220b">
    <link rel="stylesheet" href="/css/admin-dock.css?v=20260220b">
</head>
<body class="teacher-page">
    <div class="app-container" id="app">
        <!-- 로그인 -->
        <div id="view-login">
            <div class="teacher-login-container">
                <div class="teacher-login-logo">
                    <div style="font-size:48px; margin-bottom:8px;">📋</div>
                    <div style="font-size:24px; font-weight:800; color:#FF9800;">관리쌤 로그인</div>
                    <div style="font-size:13px; color:#9E9E9E; margin-top:4px;">소리튠 주니어 영어학교</div>
                </div>

                <!-- 시스템관리자 대행 로그인 패널 -->
                <div id="impersonate-panel" style="display:none;">
                    <div style="background:linear-gradient(135deg,#E8F5E9,#C8E6C9); border:1.5px solid #A5D6A7; border-radius:14px; padding:14px; margin-bottom:16px;">
                        <div style="font-weight:700; font-size:14px; color:#2E7D32; margin-bottom:4px;">시스템관리자 모드</div>
                        <div style="font-size:12px; color:#4CAF50;">관리쌤을 선택하면 자동으로 로그인됩니다</div>
                    </div>
                    <div id="teacher-list-panel" style="display:flex; flex-direction:column; gap:8px;"></div>
                    <div style="border-top:1px solid #eee; margin:16px 0 12px; padding-top:12px;">
                        <div style="font-size:12px; color:#999; margin-bottom:8px;">또는 직접 로그인</div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">전화번호 뒷 4자리</label>
                    <input type="tel" id="login-phone" class="form-input" placeholder="뒷 4자리를 입력하세요" maxlength="4" inputmode="numeric" autocomplete="tel">
                </div>
                <div style="font-size:12px; color:#9E9E9E; margin-bottom:12px;">
                    소리튠영어에 등록된 전화번호 뒷 4자리를 입력해 주세요
                </div>
                <button class="btn btn-primary btn-block btn-lg" id="btn-login" style="background:#FF9800; margin-top:8px;">로그인</button>
            </div>
        </div>

        <!-- 대시보드 -->
        <div id="view-dashboard" class="hidden">
            <div class="app-header">
                <h1 id="teacher-title">관리쌤 대시보드</h1>
                <button class="back-btn" id="btn-teacher-logout">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                </button>
            </div>

            <!-- 반 선택 (다중 반 담당 시) -->
            <div id="class-selector" class="hidden" style="padding:12px 16px; background:#FFF3E0;">
                <select id="class-select" class="form-input" style="padding:8px 12px;"></select>
            </div>

            <div style="padding:16px;">
                <!-- 탭 -->
                <div class="tabs" id="teacher-tabs">
                    <button class="tab-btn active" data-tab="overview">반 현황</button>
                    <button class="tab-btn" data-tab="checklist">체크리스트</button>
                    <button class="tab-btn" data-tab="profile">학생 프로필</button>
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
                        <button class="btn btn-primary" id="btn-save-checklist" style="background:#FF9800;">저장</button>
                    </div>
                </div>

                <!-- 탭3: 학생 프로필 -->
                <div class="tab-content" id="tab-profile">
                    <div id="profile-search" style="margin-bottom:16px;">
                        <select id="profile-student-select" class="form-input">
                            <option value="">학생을 선택하세요</option>
                        </select>
                    </div>
                    <div id="profile-content"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="/js/toast.js?v=20260220b"></script>
    <script src="/js/common.js?v=20260220b"></script>
    <script src="/js/fingerprint.js?v=20260220b"></script>
    <script>
    // 시스템관리자 대행 로그인 체크
    (async () => {
        try {
            const r = await App.get('/api/system.php?action=check_session');
            if (r.logged_in) {
                const panel = document.getElementById('impersonate-panel');
                const listEl = document.getElementById('teacher-list-panel');
                panel.style.display = 'block';

                const cr = await App.get('/api/system.php?action=admin_list');
                if (cr.success && cr.admins) {
                    const teachers = cr.admins.filter(a => a.role === 'admin_teacher');
                    listEl.innerHTML = teachers.map(c => `
                        <button onclick="impersonateTeacher(${c.id})" style="display:flex; align-items:center; gap:10px; width:100%; padding:12px 14px; border:1.5px solid #FFF3E0; border-radius:12px; background:#fff; cursor:pointer; text-align:left; transition:all .15s; font-family:inherit;">
                            <div style="width:36px; height:36px; background:#FFF3E0; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; color:#E65100; flex-shrink:0;">${c.name.charAt(0)}</div>
                            <div style="flex:1; min-width:0;">
                                <div style="font-weight:700; font-size:14px; color:#333;">${c.name}</div>
                                <div style="font-size:11px; color:#999; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${c.class_names || '반 미배정'}</div>
                            </div>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                        </button>
                    `).join('');
                    if (teachers.length === 0) {
                        listEl.innerHTML = '<div style="text-align:center; padding:12px; color:#999; font-size:13px;">등록된 관리쌤이 없습니다</div>';
                    }
                }
            }
        } catch(e) {}
    })();

    async function impersonateTeacher(adminId) {
        App.showLoading();
        const result = await App.post('/api/system.php?action=impersonate_admin', { admin_id: adminId });
        App.hideLoading();
        if (result.success) {
            Toast.success(result.message);
            setTimeout(() => location.reload(), 500);
        }
    }
    </script>
    <script src="/js/teacher.js?v=20260220h"></script>
    <script src="/js/admin-dock.js?v=20260220c" data-adock-active="teacher"></script>
</body>
</html>
