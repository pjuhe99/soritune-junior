<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#673AB7">
    <title>관리자 - 소리튠 주니어</title>
    <link rel="stylesheet" href="/css/common.css?v=20260223c">
    <link rel="stylesheet" href="/css/toast.css?v=20260223c">
    <link rel="stylesheet" href="/css/admin.css?v=20260223c">
    <link rel="stylesheet" href="/css/admin-dock.css?v=20260223c">
    <style>
    /* Admin login styles */
    .admin-login-container {
        display:flex; flex-direction:column; align-items:center; justify-content:center;
        min-height:100vh; padding:24px; background:linear-gradient(135deg,#EDE7F6,#F3E5F5);
    }
    .admin-login-card {
        background:#fff; border-radius:20px; padding:32px 24px; width:100%; max-width:380px;
        box-shadow:0 8px 32px rgba(103,58,183,.12);
    }
    .admin-login-logo { text-align:center; margin-bottom:28px; }
    .admin-login-logo-icon { font-size:48px; margin-bottom:8px; }
    .admin-login-logo-title { font-size:24px; font-weight:800; color:#673AB7; }
    .admin-login-logo-sub { font-size:13px; color:#9E9E9E; margin-top:4px; }
    .admin-login-tabs {
        display:flex; gap:0; margin-bottom:24px; background:#F5F5F5; border-radius:12px; padding:3px;
    }
    .admin-login-tab {
        flex:1; padding:10px 0; border:none; background:none; border-radius:10px;
        font-size:14px; font-weight:600; color:#9E9E9E; cursor:pointer; transition:all .2s;
        font-family:inherit;
    }
    .admin-login-tab.active { background:#673AB7; color:#fff; box-shadow:0 2px 8px rgba(103,58,183,.3); }
    .admin-login-divider { border:none; border-top:1px solid #eee; margin:20px 0; }
    .admin-auto-login-msg {
        text-align:center; padding:20px; color:#9E9E9E; font-size:14px;
    }
    .admin-auto-login-msg .spinner {
        display:inline-block; width:24px; height:24px; border:3px solid #E0E0E0;
        border-top-color:#673AB7; border-radius:50%; animation:spin .8s linear infinite;
        margin-bottom:8px;
    }
    @keyframes spin { to { transform:rotate(360deg); } }

    /* Dashboard card grid for parent */
    .child-card {
        background:#fff; border-radius:16px; padding:16px; box-shadow:0 2px 12px rgba(0,0,0,.06);
        cursor:pointer; transition:all .2s; border:2px solid transparent;
    }
    .child-card:hover { transform:translateY(-2px); border-color:#673AB7; box-shadow:0 4px 20px rgba(103,58,183,.12); }
    .child-card-header { display:flex; align-items:center; gap:12px; margin-bottom:12px; }
    .child-avatar {
        width:44px; height:44px; border-radius:50%; background:#EDE7F6; color:#673AB7;
        display:flex; align-items:center; justify-content:center; font-size:18px; font-weight:800; flex-shrink:0;
    }
    .child-name { font-size:16px; font-weight:700; }
    .child-class { font-size:12px; color:#9E9E9E; }
    .child-rewards {
        display:grid; grid-template-columns:repeat(5,1fr); gap:4px; text-align:center;
    }
    .child-reward-item { padding:6px 2px; border-radius:8px; background:#F5F5F5; }
    .child-reward-count { font-size:16px; font-weight:800; }
    .child-reward-name { font-size:9px; color:#9E9E9E; }
    </style>
</head>
<body class="admin-page">
    <div class="app-container" id="app">
        <!-- Auto-login spinner -->
        <div id="view-loading" class="admin-auto-login-msg" style="min-height:100vh; display:flex; flex-direction:column; align-items:center; justify-content:center;">
            <div class="spinner"></div>
            <div>로그인 확인 중...</div>
        </div>

        <!-- Login View -->
        <div id="view-login" style="display:none;">
            <div class="admin-login-container">
                <div class="admin-login-card">
                    <div class="admin-login-logo">
                        <div class="admin-login-logo-icon">👨‍👩‍👧‍👦</div>
                        <div class="admin-login-logo-title">소리튠 주니어</div>
                        <div class="admin-login-logo-sub">학부모 로그인</div>
                    </div>

                    <!-- 시스템관리자 대행 로그인 패널 -->
                    <div id="impersonate-admin-panel" style="display:none;">
                        <div style="background:linear-gradient(135deg,#E8F5E9,#C8E6C9); border:1.5px solid #A5D6A7; border-radius:14px; padding:14px; margin-bottom:16px;">
                            <div style="font-weight:700; font-size:14px; color:#2E7D32; margin-bottom:4px;">시스템관리자 모드</div>
                            <div style="font-size:12px; color:#4CAF50;">관리쌤 또는 부모님을 선택하면 자동으로 로그인됩니다</div>
                        </div>
                        <div id="imp-admin-list" style="display:flex; flex-direction:column; gap:6px; max-height:300px; overflow-y:auto;"></div>
                        <div style="border-top:1px solid #eee; margin:16px 0 12px; padding-top:12px;">
                            <div style="font-size:12px; color:#999; margin-bottom:8px;">또는 직접 로그인</div>
                        </div>
                    </div>

                    <!-- Phone Login (통합) -->
                    <div id="form-phone">
                        <div class="form-group">
                            <label class="form-label">전화번호</label>
                            <input type="tel" id="phone-input" class="form-input" placeholder="전화번호 또는 뒷 4자리" autocomplete="tel" inputmode="tel">
                        </div>
                        <div style="font-size:12px; color:#9E9E9E; margin-bottom:12px;">
                            소리튠영어에 등록된 전화번호를 입력해 주세요
                        </div>
                        <button class="btn btn-block btn-lg" id="btn-phone-login" style="background:#673AB7; color:#fff;">
                            로그인
                        </button>
                    </div>

                    <div style="margin-top:20px; padding-top:16px; border-top:1px solid #E0E0E0; text-align:center;">
                        <a href="/teacher/" style="display:inline-flex; align-items:center; gap:6px; color:#FF9800; font-size:14px; font-weight:600; text-decoration:none;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>
                            관리쌤 로그인은 여기로
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dashboard View -->
        <div id="view-dashboard" style="display:none;">
            <div class="app-header" style="background:#673AB7;">
                <h1 id="admin-title">관리자</h1>
                <div style="display:flex; align-items:center; gap:12px;">
                    <button class="back-btn" id="btn-unread-badge" style="position:relative;" title="메시지">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        <span id="unread-count-badge" style="display:none; position:absolute; top:-4px; right:-4px; background:#F44336; color:#fff; border-radius:10px; padding:1px 5px; font-size:10px; font-weight:700; min-width:16px; text-align:center;"></span>
                    </button>
                    <button class="back-btn" id="btn-logout">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    </button>
                </div>
            </div>
            <div class="app-body" style="padding:16px;">
                <div id="dashboard-content">
                    <div class="loading-spinner"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="/js/toast.js?v=20260223c"></script>
    <script src="/js/common.js?v=20260223c"></script>
    <script src="/js/fingerprint.js?v=20260223c"></script>
    <script>
    /**
     * 부모/관리쌤 통합 SPA
     */
    const AdminApp = (() => {
        let adminInfo = null;
        let fingerprint = null;
        let currentView = 'loading';

        // ============================================
        // 초기화
        // ============================================
        async function init() {
            bindEvents();

            // 핑거프린트 생성
            try {
                fingerprint = await DeviceFingerprint.generate();
            } catch (e) {
                console.warn('Fingerprint generation failed:', e);
            }

            // 0차: IP 기반 시스템 관리자 자동 로그인 체크
            try {
                const ipCheckResult = await App.get('/api/system.php?action=check_ip_auto_login');
                if (ipCheckResult.allowed && ipCheckResult.ip) {
                    // 허용된 IP인 경우 시스템 관리자로 자동 로그인
                    const ipLoginResult = await App.post('/api/system.php?action=ip_auto_login', {});
                    if (ipLoginResult.success) {
                        // 시스템 관리자 세션이 생성되었으므로, 관리쌤/부모 대행 로그인 패널 표시를 위해 페이지 새로고침
                        // 실제로는 아래의 일반 관리자 로그인 프로세스로 넘어갑니다
                    }
                }
            } catch (e) {
                console.warn('IP auto-login check failed:', e);
            }

            // 1차: 세션 확인
            try {
                const sessionResult = await App.get('/api/admin.php?action=check_session');
                if (sessionResult.logged_in) {
                    adminInfo = sessionResult.admin;
                    showDashboard();
                    return;
                }
            } catch (e) {
                console.warn('Session check failed:', e);
            }

            // 2차: 핑거프린트 자동 로그인
            try {
                if (fingerprint) {
                    const autoResult = await App.post('/api/admin.php?action=auto_login', {
                        fingerprint: fingerprint
                    });
                    if (autoResult.logged_in) {
                        adminInfo = autoResult.admin;
                        showDashboard();
                        return;
                    }
                }
            } catch (e) {
                console.warn('Auto login failed:', e);
            }

            // 로그인 폼 표시
            showLogin();
        }

        function bindEvents() {
            // 전화번호 로그인
            document.getElementById('btn-phone-login').addEventListener('click', doPhoneLogin);
            document.getElementById('phone-input').addEventListener('keyup', e => { if (e.key === 'Enter') doPhoneLogin(); });

            // 로그아웃
            document.getElementById('btn-logout').addEventListener('click', () => {
                App.confirm('로그아웃 하시겠습니까?', doLogout);
            });

            // 알림 배지 클릭 — 메시지 스레드 목록 표시
            document.getElementById('btn-unread-badge').addEventListener('click', loadMsgThreadList);
        }

        // 메시지 스레드 목록 (말풍선 아이콘 클릭 시)
        async function loadMsgThreadList() {
            const container = document.getElementById('dashboard-content');
            container.innerHTML = '<div style="text-align:center; padding:40px;"><div class="loading-spinner" style="display:inline-block;"></div></div>';

            const [threadsResult, availResult] = await Promise.all([
                App.get('/api/admin.php?action=msg_threads'),
                App.get('/api/admin.php?action=msg_available_coaches'),
            ]);
            if (!threadsResult.success) return;

            const threads = threadsResult.threads || [];
            const available = (availResult.success ? availResult.available : []) || [];

            // 진행 중인 대화 목록
            const threadsHtml = threads.map(t => {
                const unread = parseInt(t.unread_count) || 0;
                const preview = (t.last_message || '').substring(0, 30) + ((t.last_message || '').length > 30 ? '...' : '');
                const timeStr = formatMsgTimeAdmin(t.last_message_at);
                return `
                    <div class="card" style="cursor:pointer; padding:14px;" onclick="AdminApp.openParentChat(${t.student_id})">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <div class="child-avatar">${(t.student_name||'?').charAt(0)}</div>
                            <div style="flex:1; min-width:0;">
                                <div style="font-weight:700; font-size:14px;">${escapeHtmlAdmin(t.student_name)}</div>
                                <div style="font-size:11px; color:#999;">${escapeHtmlAdmin(t.class_name)} / ${escapeHtmlAdmin(t.coach_name || '')} 선생님</div>
                                <div style="font-size:12px; color:#757575; margin-top:2px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${escapeHtmlAdmin(preview)}</div>
                            </div>
                            <div style="text-align:right; flex-shrink:0;">
                                <div style="font-size:10px; color:#999;">${timeStr}</div>
                                ${unread > 0 ? `<div style="background:#F44336; color:#fff; border-radius:12px; padding:2px 8px; font-size:11px; font-weight:700; margin-top:4px;">${unread}건</div>` : ''}
                            </div>
                        </div>
                    </div>
                `;
            }).join('');

            // 새 대화 시작 버튼 목록
            const newChatHtml = available.map(a => `
                <button class="card new-chat-btn" data-student-id="${a.student_id}" style="width:100%; padding:14px; cursor:pointer; border:1.5px dashed #CE93D8; background:#FAFAFA; text-align:left;">
                    <div style="display:flex; align-items:center; gap:12px;">
                        <div style="width:42px; height:42px; border-radius:50%; background:#F3E5F5; color:#8E24AA; display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0;">💬</div>
                        <div style="flex:1; min-width:0;">
                            <div style="font-weight:700; font-size:14px; color:#673AB7;">${escapeHtmlAdmin(a.coach_name)} 선생님과 대화 시작하기</div>
                            <div style="font-size:11px; color:#999; margin-top:2px;">${escapeHtmlAdmin(a.student_name)} · ${escapeHtmlAdmin(a.class_name)}</div>
                        </div>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                    </div>
                </button>
            `).join('');

            container.innerHTML = `
                <button class="btn btn-secondary btn-sm" id="btn-back-from-threads" style="margin-bottom:16px;">← 목록으로</button>
                <div style="font-size:18px; font-weight:700; margin-bottom:4px;">💬 메시지</div>
                <div style="font-size:12px; color:#BDBDBD; margin-bottom:12px;">주말에는 답변이 어려울 수 있습니다</div>
                ${threads.length === 0 && available.length === 0
                    ? '<div class="empty-state"><div class="empty-state-text">대화할 수 있는 선생님이 없습니다</div></div>'
                    : `<div style="display:flex; flex-direction:column; gap:8px;">
                        ${threads.length > 0 ? threadsHtml : '<div style="text-align:center; padding:20px; color:#999; font-size:14px;">진행 중인 대화가 없어요</div>'}
                        ${newChatHtml}
                    </div>`
                }
            `;

            document.getElementById('btn-back-from-threads').addEventListener('click', loadStudents);
            container.querySelectorAll('.new-chat-btn').forEach(btn => {
                btn.addEventListener('click', () => openParentChat(parseInt(btn.dataset.studentId)));
            });
        }

        function formatMsgTimeAdmin(dateStr) {
            if (!dateStr) return '';
            const d = new Date(dateStr.replace(' ', 'T'));
            const now = new Date();
            const diff = now - d;
            if (diff < 60000) return '방금';
            if (diff < 3600000) return Math.floor(diff/60000) + '분 전';
            if (diff < 86400000) return d.getHours() + ':' + String(d.getMinutes()).padStart(2,'0');
            if (diff < 172800000) return '어제';
            return (d.getMonth()+1) + '/' + d.getDate();
        }

        // ============================================
        // 뷰 전환
        // ============================================
        function showView(name) {
            currentView = name;
            document.getElementById('view-loading').style.display = name === 'loading' ? 'flex' : 'none';
            document.getElementById('view-login').style.display = name === 'login' ? '' : 'none';
            document.getElementById('view-dashboard').style.display = name === 'dashboard' ? '' : 'none';
        }

        function showLogin() {
            showView('login');
        }

        function showDashboard() {
            // admin_teacher는 전용 페이지(/teacher/)로 리다이렉트
            if (adminInfo && adminInfo.admin_role === 'admin_teacher') {
                window.location.href = '/teacher/';
                return;
            }
            showView('dashboard');
            const roleLabels = { admin_teacher: '관리쌤', parent: '부모님' };
            const roleSuffix = roleLabels[adminInfo.admin_role] || '';
            const name = adminInfo.admin_name || '';
            document.getElementById('admin-title').textContent =
                name.endsWith(roleSuffix) ? name : `${name} ${roleSuffix}`;
            loadStudents();

            // 부모: 알림 폴링 시작
            if (adminInfo.admin_role === 'parent') {
                startUnreadPolling();
            }
        }

        // ============================================
        // 전화번호 로그인
        // ============================================
        async function doPhoneLogin() {
            const phone = document.getElementById('phone-input').value.trim();
            if (!phone) {
                Toast.warning('전화번호를 입력해 주세요');
                return;
            }

            App.showLoading();
            const deviceInfo = DeviceFingerprint.getDeviceInfo();
            const result = await App.post('/api/admin.php?action=phone_login', {
                phone: phone,
                fingerprint: fingerprint || '',
                device_info: deviceInfo,
            });
            App.hideLoading();

            if (result.success) {
                adminInfo = result.admin;
                Toast.success(`${result.admin.name} 환영합니다!`);
                showDashboard();
            }
        }


        // ============================================
        // 로그아웃
        // ============================================
        async function doLogout() {
            await App.post('/api/admin.php?action=logout', {
                fingerprint: fingerprint || '',
            });
            window.location.href = '/';
        }

        // ============================================
        // 학생 목록 로드
        // ============================================
        async function loadStudents() {
            const container = document.getElementById('dashboard-content');

            // 부모인 경우 기존 로직
            if (adminInfo.admin_role === 'parent') {
                const result = await App.get('/api/admin.php?action=my_students');
                if (!result.success) return;

                if (result.students.length === 0) {
                    container.innerHTML = '<div class="empty-state"><div class="empty-state-text">연결된 학생이 없습니다</div></div>';
                    return;
                }

                container.innerHTML = `
                    <div style="display:grid; gap:12px;">
                        ${result.students.map(s => `
                            <div class="child-card" data-id="${s.id}">
                                <div class="child-card-header">
                                    <div class="child-avatar">${s.name.charAt(0)}</div>
                                    <div style="flex:1;">
                                        <div class="child-name">${s.name}</div>
                                        <div class="child-class">${s.class_name || ''} ${s.grade ? '/ ' + s.grade : ''}</div>
                                    </div>
                                    ${App.coinBadge(s.total_coins)}
                                </div>
                            </div>
                        `).join('')}
                    </div>
                `;

                container.querySelectorAll('.child-card').forEach(card => {
                    card.addEventListener('click', () => loadStudentDashboard(parseInt(card.dataset.id)));
                });
            }
            // 관리쌤인 경우 새로운 대시보드
            else if (adminInfo.admin_role === 'admin_teacher') {
                loadTeacherDashboard();
            }
        }

        // ============================================
        // 관리쌤 대시보드 (담당 반 목록 + 과제율)
        // ============================================
        async function loadTeacherDashboard() {
            const container = document.getElementById('dashboard-content');
            const result = await App.get('/api/admin.php?action=teacher_dashboard');
            if (!result.success) return;

            if (result.classes.length === 0) {
                container.innerHTML = '<div class="empty-state"><div class="empty-state-text">담당 반이 없습니다</div></div>';
                return;
            }

            container.innerHTML = `
                <div style="margin-bottom:12px; font-size:13px; color:#9E9E9E;">
                    오늘 날짜: ${App.formatDate(result.date, 'YYYY-MM-DD (ddd)')}
                </div>
                <div style="display:grid; gap:12px;">
                    ${result.classes.map(c => `
                        <div class="card" data-class-id="${c.id}" style="cursor:pointer; border-left:4px solid ${c.color || '#FF7E17'}; transition:all .2s;">
                            <div style="display:flex; align-items:center; gap:12px; margin-bottom:12px;">
                                <div style="flex:1;">
                                    <div style="font-size:18px; font-weight:700; color:#333;">${c.display_name}</div>
                                    <div style="font-size:13px; color:#9E9E9E; margin-top:2px;">
                                        ${c.coach_name || '코치 미배정'} / 학생 ${c.total_students}명
                                    </div>
                                </div>
                                <div style="text-align:center;">
                                    <div style="font-size:28px; font-weight:800; color:${c.completion_rate >= 80 ? '#4CAF50' : c.completion_rate >= 50 ? '#FF9800' : '#F44336'};">
                                        ${c.completion_rate}%
                                    </div>
                                    <div style="font-size:11px; color:#9E9E9E;">과제율</div>
                                </div>
                            </div>
                            <div style="height:6px; background:#F5F5F5; border-radius:3px; overflow:hidden;">
                                <div style="height:100%; background:${c.completion_rate >= 80 ? '#4CAF50' : c.completion_rate >= 50 ? '#FF9800' : '#F44336'}; width:${c.completion_rate}%; transition:width .3s;"></div>
                            </div>
                            <div style="font-size:12px; color:#757575; margin-top:8px;">
                                ${c.completed_students}/${c.total_students}명 완료
                            </div>
                        </div>
                    `).join('')}
                </div>

                <button class="btn btn-block" id="btn-all-ranking" style="background:linear-gradient(135deg,#FF7E17,#FF9800); color:#fff; font-weight:700; margin-top:16px; padding:14px; border-radius:14px; font-size:14px;">
                    전체 반 과제율 랭킹 보러가기
                </button>
                <div id="all-class-ranking" style="display:none; margin-top:16px;"></div>
            `;

            container.querySelectorAll('[data-class-id]').forEach(card => {
                card.addEventListener('click', () => loadTeacherClassDetail(parseInt(card.dataset.classId)));
            });

            document.getElementById('btn-all-ranking').addEventListener('click', () => {
                const rc = document.getElementById('all-class-ranking');
                if (rc.style.display === 'none') {
                    rc.style.display = 'block';
                    document.getElementById('btn-all-ranking').textContent = '전체 반 과제율 랭킹 접기';
                    loadAllClassRanking(result.date);
                } else {
                    rc.style.display = 'none';
                    document.getElementById('btn-all-ranking').textContent = '전체 반 과제율 랭킹 보러가기';
                }
            });
        }

        // ============================================
        // 관리쌤용 반 상세 (생활미션 체크 + 학생별 카드 현황)
        // ============================================
        async function loadTeacherClassDetail(classId) {
            const container = document.getElementById('dashboard-content');
            App.showLoading();
            const result = await App.get('/api/admin.php?action=teacher_class_detail&class_id=' + classId);
            App.hideLoading();

            if (!result.success) return;

            const { class: classInfo, students, date } = result;

            // 체크리스트 행 생성
            const checklistRows = students.map(s => {
                const chk = (field, val) => '<input type="checkbox" class="check-field" data-field="' + field + '"' + (val ? ' checked' : '') + '>';
                return '<tr data-student-id="' + s.id + '">'
                    + '<td><div style="font-weight:600;">' + s.name + '</div><div style="font-size:11px; color:#999;">' + (s.grade || '') + '</div></td>'
                    + '<td style="text-align:center;">' + chk('zoom_attendance', s.zoom_attendance) + '</td>'
                    + '<td style="text-align:center;">' + chk('posture_king', s.posture_king) + '</td>'
                    + '<td style="text-align:center;">' + chk('sound_homework', s.sound_homework) + '</td>'
                    + '<td style="text-align:center;">' + chk('band_mission', s.band_mission) + '</td>'
                    + '<td style="text-align:center;">' + chk('leader_king', s.leader_king) + '</td>'
                    + '<td style="text-align:center;">' + App.coinBadge(s.total_coins) + '</td>'
                    + '</tr>';
            }).join('');

            // 학생별 카드 현황 생성
            const studentCards = students.map(s => {
                const rewardItems = (s.rewards || []).map(r =>
                    '<div class="child-reward-item" style="border-top:3px solid ' + r.color + ';">'
                    + '<div class="child-reward-count" style="color:' + r.color + ';">' + r.quantity + '</div>'
                    + '<div class="child-reward-name">' + r.name_ko + '</div>'
                    + '</div>'
                ).join('');
                return '<div class="card" onclick="AdminApp.loadStudentDashboard(' + s.id + ')" style="cursor:pointer;">'
                    + '<div style="display:flex; align-items:center; gap:12px; margin-bottom:12px;">'
                    + '<div class="child-avatar" style="width:40px; height:40px; font-size:16px;">' + s.name.charAt(0) + '</div>'
                    + '<div style="flex:1;">'
                    + '<div style="font-weight:700; font-size:15px;">' + s.name + '</div>'
                    + '<div style="font-size:12px; color:#9E9E9E;">' + (s.grade || '') + '</div>'
                    + '</div>'
                    + App.coinBadge(s.total_coins, 'lg')
                    + '</div>'
                    + '<div class="child-rewards">' + rewardItems + '</div>'
                    + '</div>';
            }).join('');

            container.innerHTML = `
                <button class="btn btn-secondary btn-sm" id="btn-back-classes" style="margin-bottom:16px;">\u2190 반 목록</button>

                <div class="card" style="border-left:4px solid ${classInfo.color || '#FF7E17'}; margin-bottom:16px;">
                    <div style="font-size:20px; font-weight:700; color:#333; margin-bottom:4px;">${classInfo.display_name}</div>
                    <div style="font-size:13px; color:#9E9E9E;">${classInfo.coach_name || '코치 미배정'} / ${date}</div>
                </div>

                <div style="overflow-x:auto; margin-bottom:16px;">
                    <table class="data-table" style="min-width:600px;">
                        <thead>
                            <tr>
                                <th style="min-width:80px;">이름</th>
                                <th style="text-align:center; min-width:60px;">줌출석</th>
                                <th style="text-align:center; min-width:60px;">자세왕</th>
                                <th style="text-align:center; min-width:60px;">소리과제</th>
                                <th style="text-align:center; min-width:60px;">밴드미션</th>
                                <th style="text-align:center; min-width:60px;">리더왕</th>
                                <th style="text-align:center; min-width:80px;">총 코인</th>
                            </tr>
                        </thead>
                        <tbody id="checklist-tbody">
                            ${checklistRows}
                        </tbody>
                    </table>
                </div>

                <button class="btn btn-primary btn-block" id="btn-save-checklist" style="background:#673AB7;">저장</button>

                <div style="margin-top:24px;">
                    <div style="font-size:16px; font-weight:700; margin-bottom:12px;">학생별 카드 현황</div>
                    <div style="display:grid; gap:12px;">
                        ${studentCards}
                    </div>
                </div>
            `;

            document.getElementById('btn-back-classes').addEventListener('click', loadStudents);
            document.getElementById('btn-save-checklist').addEventListener('click', async () => {
                const items = [];
                document.querySelectorAll('#checklist-tbody tr').forEach(tr => {
                    const studentId = parseInt(tr.dataset.studentId);
                    const item = { student_id: studentId };

                    tr.querySelectorAll('.check-field').forEach(checkbox => {
                        const field = checkbox.dataset.field;
                        item[field] = checkbox.checked ? 1 : 0;
                    });

                    items.push(item);
                });

                App.showLoading();
                const saveResult = await App.post('/api/admin.php?action=teacher_save_checklist', {
                    class_id: classId,
                    date: date,
                    items: items,
                });
                App.hideLoading();

                if (saveResult.success) {
                    Toast.success('체크리스트가 저장되었습니다');
                    loadTeacherClassDetail(classId); // 새로고침
                }
            });
        }

        // ============================================
        // 전체 반 과제율 랭킹
        // ============================================
        let rankingDate = null;

        async function loadAllClassRanking(date) {
            rankingDate = date || new Date().toISOString().split('T')[0];
            const container = document.getElementById('all-class-ranking');
            if (!container) return;

            container.innerHTML = '<div style="text-align:center; padding:20px;"><div class="loading-spinner" style="display:inline-block;"></div></div>';

            const result = await App.get('/api/coach.php?action=class_assignment_ranking', { date: rankingDate });
            if (!result.success) {
                container.innerHTML = '<div style="text-align:center; padding:16px; color:#F44336;">데이터를 불러올 수 없습니다</div>';
                return;
            }

            const medals = ['\u{1F947}', '\u{1F948}', '\u{1F949}'];
            const dayNames = ['일','월','화','수','목','금','토'];
            const d = new Date(rankingDate + 'T00:00:00');
            const dateLabel = `${d.getMonth()+1}/${d.getDate()} (${dayNames[d.getDay()]})`;

            container.innerHTML = `
                <div class="card" style="padding:0; overflow:hidden; border-radius:16px;">
                    <div style="padding:14px 16px; background:linear-gradient(135deg,#FFF3E0,#FFE0B2);">
                        <div style="font-weight:800; font-size:16px; color:#E65100; margin-bottom:10px;">전체 반 과제율 랭킹</div>
                        <div style="display:flex; align-items:center; gap:6px;">
                            <button class="btn btn-sm" id="adm-ranking-prev" style="background:#fff; color:#333; border:1px solid #ddd; padding:4px 8px;">◀</button>
                            <span style="font-weight:700; font-size:14px; color:#333; min-width:100px; text-align:center;">${dateLabel}</span>
                            <button class="btn btn-sm" id="adm-ranking-next" style="background:#fff; color:#333; border:1px solid #ddd; padding:4px 8px;">▶</button>
                            <button class="btn btn-sm" id="adm-ranking-today" style="background:#FF7E17; color:#fff; padding:4px 10px; font-size:11px;">오늘</button>
                        </div>
                    </div>
                    <div>
                        ${result.classes.map(c => {
                            const rate = c.completion_rate;
                            const rateColor = rate >= 80 ? '#4CAF50' : rate >= 50 ? '#FF9800' : rate > 0 ? '#F44336' : '#BDBDBD';
                            const medal = c.rank <= 3 ? medals[c.rank - 1] : '';
                            const bgColor = c.rank === 1 ? 'linear-gradient(135deg,#FFF8E1,#FFE082)' :
                                           c.rank === 2 ? 'linear-gradient(135deg,#ECEFF1,#CFD8DC)' :
                                           c.rank === 3 ? 'linear-gradient(135deg,#EFEBE9,#D7CCC8)' : '#fff';
                            return `
                                <div style="display:flex; align-items:center; gap:10px; padding:12px 16px; border-bottom:1px solid #f0f0f0; background:${bgColor};">
                                    <div style="width:28px; text-align:center; font-size:${c.rank <= 3 ? '20px' : '14px'}; font-weight:700; color:${c.rank <= 3 ? '#333' : '#999'};">
                                        ${medal || c.rank}
                                    </div>
                                    <div style="flex:1; min-width:0;">
                                        <div style="font-weight:700; font-size:14px; color:#333;">${c.class_name}</div>
                                        <div style="font-size:11px; color:#999;">${c.coach_name || ''} / ${c.total_students}명</div>
                                    </div>
                                    <div style="text-align:right;">
                                        <div style="font-size:20px; font-weight:800; color:${rateColor};">${rate}%</div>
                                        <div style="font-size:10px; color:#999;">${c.checked_count}/${c.total_students}</div>
                                    </div>
                                </div>
                            `;
                        }).join('')}
                    </div>
                </div>
            `;

            document.getElementById('adm-ranking-prev').addEventListener('click', () => {
                const dd = new Date(rankingDate);
                dd.setDate(dd.getDate() - 1);
                loadAllClassRanking(dd.toISOString().split('T')[0]);
            });
            document.getElementById('adm-ranking-next').addEventListener('click', () => {
                const dd = new Date(rankingDate);
                dd.setDate(dd.getDate() + 1);
                loadAllClassRanking(dd.toISOString().split('T')[0]);
            });
            document.getElementById('adm-ranking-today').addEventListener('click', () => {
                loadAllClassRanking(new Date().toISOString().split('T')[0]);
            });
        }

        // ============================================
        // 학생 대시보드
        // ============================================
        async function loadStudentDashboard(studentId) {
            App.showLoading();
            const result = await App.get(`/api/admin.php?action=student_dashboard&student_id=${studentId}`);
            App.hideLoading();

            if (!result.success) return;

            const { student, rewards, total_coins, checklists, ace_reports } = result;
            const container = document.getElementById('dashboard-content');

            const fields = ['zoom_attendance', 'posture_king', 'sound_homework', 'band_mission', 'leader_king'];
            const labels = ['줌출석', '자세왕', '소리과제', '밴드미션', '리더왕'];

            container.innerHTML = `
                <button class="btn btn-secondary btn-sm" id="btn-back-list" style="margin-bottom:16px;">← 목록으로</button>

                <!-- 공지 섹션 -->
                <div id="ann-section-${studentId}" style="margin-bottom:16px;"></div>

                <div class="card" style="border-radius:16px;">
                    <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px;">
                        <div class="child-avatar" style="width:52px; height:52px; font-size:22px;">${student.name.charAt(0)}</div>
                        <div style="flex:1;">
                            <div style="font-size:20px; font-weight:700;">${student.name}</div>
                            <div style="font-size:13px; color:#9E9E9E;">${student.class_name || ''} / ${student.coach_name || ''}</div>
                        </div>
                        ${App.coinBadge(total_coins, 'lg')}
                    </div>

                    <div class="child-rewards">
                        ${rewards.map(r => `
                            <div class="child-reward-item" style="border-top:3px solid ${r.color};">
                                <div class="child-reward-count" style="color:${r.color};">${r.quantity}</div>
                                <div class="child-reward-name">${r.name_ko}</div>
                            </div>
                        `).join('')}
                    </div>
                </div>

                ${ace_reports && ace_reports.length > 0 ? `
                    <div class="card" style="margin-top:16px; border-radius:16px;">
                        <div style="font-size:16px; font-weight:700; margin-bottom:12px;">📊 성장 리포트</div>
                        <div style="display:flex; flex-direction:column; gap:8px;">
                            ${ace_reports.map(r => {
                                const levelColors = { 1: '#4CAF50', 2: '#FF9800', 3: '#9C27B0' };
                                const levelNames = { 1: 'ACE 1', 2: 'ACE 2', 3: 'ACE 3' };
                                const color = levelColors[r.ace_level] || '#2196F3';
                                const badge = r.result === 'pass' ? 'PASS ✅' : '재도전 🔄';
                                const date = r.report_sent_at ? r.report_sent_at.slice(0,10) : '';
                                return `<a href="/ace-report/?token=${r.report_token}" target="_blank" style="display:flex; align-items:center; gap:12px; padding:12px 14px; background:#FAFAFA; border-radius:12px; border-left:4px solid ${color}; text-decoration:none; color:inherit;">
                                    <div style="flex:1;">
                                        <div style="font-weight:700; font-size:14px; color:#333;">${levelNames[r.ace_level] || 'ACE'} · ${badge}</div>
                                        <div style="font-size:11px; color:#999; margin-top:2px;">${date}</div>
                                    </div>
                                    <div style="padding:6px 12px; background:${color}; color:#fff; border-radius:8px; font-size:12px; font-weight:700;">보기</div>
                                </a>`;
                            }).join('')}
                        </div>
                    </div>
                ` : ''}

                ${checklists.length > 0 ? `
                    <div class="card" style="margin-top:16px; border-radius:16px;">
                        <div style="font-size:16px; font-weight:700; margin-bottom:12px;">최근 체크리스트</div>
                        <div style="overflow-x:auto;">
                            <table class="data-table" style="min-width:400px;">
                                <thead>
                                    <tr>
                                        <th>날짜</th>
                                        ${labels.map(l => `<th style="text-align:center;">${l}</th>`).join('')}
                                    </tr>
                                </thead>
                                <tbody>
                                    ${checklists.map(c => `
                                        <tr>
                                            <td>${App.formatDate(c.check_date, 'MM/DD')}</td>
                                            ${fields.map(f => `<td style="text-align:center;">${c[f] ? '✅' : '⬜'}</td>`).join('')}
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>
                ` : ''}
            `;

            document.getElementById('btn-back-list').addEventListener('click', loadStudents);

            // 공지사항 로드
            loadAnnouncementsForStudent(studentId);
        }

        // ============================================
        // 메시지 (학부모 ↔ 코치 1:1 채팅)
        // ============================================
        let parentMsgPollingTimer = null;

        async function openParentChat(studentId) {
            const container = document.getElementById('dashboard-content');
            container.innerHTML = '<div style="text-align:center; padding:40px;"><div class="loading-spinner" style="display:inline-block;"></div></div>';

            // 스레드 찾기 또는 자동 생성 (첫 메시지 없이 UI만 표시)
            const threadsResult = await App.get('/api/admin.php?action=msg_threads');
            if (!threadsResult.success) return;

            const threads = threadsResult.threads || [];
            const existingThread = threads.find(t => parseInt(t.student_id) === studentId);

            if (existingThread) {
                renderParentChatView(parseInt(existingThread.thread_id), studentId);
            } else {
                // 스레드가 아직 없으면 빈 채팅뷰 표시
                renderParentChatView(null, studentId);
            }
        }

        async function renderParentChatView(threadId, studentId) {
            const container = document.getElementById('dashboard-content');
            let messages = [];
            let threadInfo = {};

            if (threadId) {
                const result = await App.get(`/api/admin.php?action=msg_thread_detail&thread_id=${threadId}`);
                if (result.success) {
                    messages = result.messages || [];
                    threadInfo = result.thread || {};
                }
            } else {
                // 학생 정보만 가져오기
                const stuResult = await App.get(`/api/admin.php?action=student_dashboard&student_id=${studentId}`);
                if (stuResult.success) {
                    threadInfo = { student_name: stuResult.student.name, class_name: stuResult.student.class_name, coach_name: stuResult.student.coach_name };
                }
            }

            container.innerHTML = `
                <div style="margin-bottom:12px;">
                    <button class="btn btn-secondary btn-sm" id="btn-chat-back">← 목록으로</button>
                    <span style="font-weight:700; margin-left:8px;">${threadInfo.student_name || ''}</span>
                    <span style="font-size:12px; color:#999; margin-left:4px;">${threadInfo.class_name || ''} / ${threadInfo.coach_name || ''}</span>
                </div>
                <div id="parent-chat-area" style="max-height:60vh; overflow-y:auto; padding:8px; background:#F5F5F5; border-radius:12px; margin-bottom:12px;">
                    ${messages.length === 0 ? '<div style="text-align:center; padding:40px; color:#999;">선생님에게 메시지를 보내 보세요!</div>' :
                        messages.map(m => renderParentBubble(m)).join('')}
                </div>
                <div style="display:flex; gap:8px; align-items:flex-end;">
                    <label style="cursor:pointer; flex-shrink:0; padding:10px; background:#EDE7F6; border-radius:10px;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#673AB7" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        <input type="file" id="parent-img-input" accept="image/jpeg,image/png,image/webp" style="display:none;">
                    </label>
                    <div style="flex:1; position:relative;">
                        <textarea id="parent-msg-input" placeholder="메시지를 입력하세요" rows="1" style="width:100%; padding:10px 14px; border:1.5px solid #E0E0E0; border-radius:12px; font-size:14px; resize:none; font-family:inherit; box-sizing:border-box;"></textarea>
                        <div id="parent-img-preview" style="display:none; margin-top:4px;"></div>
                    </div>
                    <button class="btn" id="parent-send-btn" style="background:#673AB7; color:#fff; padding:10px 16px; border-radius:12px; font-weight:700; flex-shrink:0;">전송</button>
                </div>
            `;

            const chatArea = document.getElementById('parent-chat-area');
            chatArea.scrollTop = chatArea.scrollHeight;

            // 읽음 처리 후 배지 즉시 갱신
            if (threadId) updateUnreadBadge();

            document.getElementById('btn-chat-back').addEventListener('click', loadStudents);

            // 이미지 미리보기
            document.getElementById('parent-img-input').addEventListener('change', (e) => {
                const file = e.target.files[0];
                const preview = document.getElementById('parent-img-preview');
                if (file) {
                    const url = URL.createObjectURL(file);
                    preview.style.display = 'block';
                    preview.innerHTML = `<div style="display:inline-block; position:relative;"><img src="${url}" style="max-height:60px; border-radius:8px;"><button onclick="document.getElementById('parent-img-input').value=''; document.getElementById('parent-img-preview').style.display='none';" style="position:absolute; top:-6px; right:-6px; background:#F44336; color:#fff; border:none; border-radius:50%; width:20px; height:20px; font-size:12px; cursor:pointer; line-height:20px;">&times;</button></div>`;
                } else {
                    preview.style.display = 'none';
                }
            });

            // 전송
            const sendFn = () => sendParentMsg(studentId);
            document.getElementById('parent-send-btn').addEventListener('click', sendFn);
            document.getElementById('parent-msg-input').addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendFn(); }
            });

            // 폴링
            if (threadId) startParentMsgPolling(threadId);
        }

        async function sendParentMsg(studentId) {
            const textEl = document.getElementById('parent-msg-input');
            const imageEl = document.getElementById('parent-img-input');
            const body = textEl.value.trim();
            const file = imageEl.files[0];

            if (!body && !file) return;

            const fd = new FormData();
            fd.append('student_id', studentId);
            fd.append('body', body);
            if (file) fd.append('image', file);

            const result = await App.post('/api/admin.php?action=msg_send', fd);
            if (result.success && result.message) {
                const chatArea = document.getElementById('parent-chat-area');
                const empty = chatArea.querySelector('[style*="text-align:center"]');
                if (empty && chatArea.children.length === 1) chatArea.innerHTML = '';
                chatArea.insertAdjacentHTML('beforeend', renderParentBubble(result.message));
                chatArea.scrollTop = chatArea.scrollHeight;
                textEl.value = '';
                imageEl.value = '';
                document.getElementById('parent-img-preview').style.display = 'none';
                document.getElementById('parent-img-preview').innerHTML = '';

                // 스레드가 방금 생성된 경우 폴링 시작
                if (result.thread_id) startParentMsgPolling(result.thread_id);
            }
        }

        function renderParentBubble(msg) {
            const isMine = msg.sender_type === 'parent';
            const align = isMine ? 'flex-end' : 'flex-start';
            const bgColor = isMine ? '#673AB7' : '#fff';
            const textColor = isMine ? '#fff' : '#333';
            const borderStyle = isMine ? '' : 'border:1px solid #E0E0E0;';
            const time = msg.created_at ? msg.created_at.slice(11, 16) : '';
            const imgHtml = msg.image_path ? `<img src="/api/admin.php?action=msg_image&path=${encodeURIComponent(msg.image_path)}" style="max-width:200px; border-radius:8px; margin-bottom:4px; cursor:pointer; display:block;" onclick="window.open(this.src)">` : '';
            const bodyHtml = msg.body ? `<div style="word-break:break-word;">${escapeHtmlAdmin(msg.body)}</div>` : '';
            return `
                <div data-msg-id="${msg.id}" style="display:flex; flex-direction:column; align-items:${align}; margin-bottom:8px;">
                    ${!isMine ? `<div style="font-size:11px; color:#999; margin-bottom:2px;">${escapeHtmlAdmin(msg.sender_name)}</div>` : ''}
                    <div style="max-width:75%; padding:10px 14px; border-radius:14px; background:${bgColor}; color:${textColor}; font-size:14px; ${borderStyle}">
                        ${imgHtml}${bodyHtml}
                    </div>
                    <div style="font-size:10px; color:#BDBDBD; margin-top:2px;">${time}</div>
                </div>
            `;
        }

        function startParentMsgPolling(threadId) {
            stopParentMsgPolling();
            parentMsgPollingTimer = setInterval(async () => {
                if (document.hidden) return;
                const chatArea = document.getElementById('parent-chat-area');
                if (!chatArea) { stopParentMsgPolling(); return; }
                const result = await App.get(`/api/admin.php?action=msg_thread_detail&thread_id=${threadId}&limit=5`, null);
                if (result.success && result.messages) {
                    const existingIds = new Set([...chatArea.querySelectorAll('[data-msg-id]')].map(el => el.dataset.msgId));
                    const newMsgs = result.messages.filter(m => !existingIds.has(String(m.id)));
                    if (newMsgs.length > 0) {
                        newMsgs.forEach(m => {
                            chatArea.insertAdjacentHTML('beforeend', renderParentBubble(m));
                        });
                        chatArea.scrollTop = chatArea.scrollHeight;
                        updateUnreadBadge();
                    }
                }
            }, 15000);
        }

        function stopParentMsgPolling() {
            if (parentMsgPollingTimer) { clearInterval(parentMsgPollingTimer); parentMsgPollingTimer = null; }
        }

        function escapeHtmlAdmin(str) {
            if (!str) return '';
            return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        // ============================================
        // 공지사항 (학부모 측 — 읽기 전용)
        // ============================================

        async function loadAnnouncementsForStudent(studentId) {
            const sectionEl = document.getElementById(`ann-section-${studentId}`);
            if (!sectionEl) return;

            const result = await App.get(`/api/admin.php?action=announcements&student_id=${studentId}`);
            if (!result.success) return;

            const anns = result.announcements || [];
            if (anns.length === 0) return; // 공지 없으면 섹션 숨김

            sectionEl.innerHTML = `
                <div class="card" style="border-radius:16px; border-left:4px solid #FF9800;">
                    <div style="font-size:16px; font-weight:700; margin-bottom:12px; color:#E65100;">📢 알림판</div>
                    <div style="display:flex; flex-direction:column; gap:8px;">
                        ${anns.map(a => {
                            const isRead = parseInt(a.is_read);
                            const pinBadge = parseInt(a.is_pinned) ? '<span style="background:#FF9800; color:#fff; padding:1px 6px; border-radius:6px; font-size:10px; font-weight:700; margin-left:6px;">고정</span>' : '';
                            const unreadDot = !isRead ? '<span style="display:inline-block; width:8px; height:8px; background:#2196F3; border-radius:50%; margin-right:6px;"></span>' : '';
                            return `
                                <div class="ann-item" style="padding:10px; background:#FAFAFA; border-radius:10px; cursor:pointer;" data-ann-id="${a.id}">
                                    <div style="display:flex; align-items:center;">
                                        ${unreadDot}
                                        <span style="font-weight:600; font-size:14px;">${escapeHtmlAdmin(a.title)}</span>
                                        ${pinBadge}
                                    </div>
                                    <div class="ann-body-preview" style="font-size:12px; color:#999; margin-top:4px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                        ${escapeHtmlAdmin(a.body).substring(0, 50)}${a.body.length > 50 ? '...' : ''}
                                    </div>
                                    <div class="ann-body-full" style="display:none; font-size:13px; color:#555; margin-top:8px; white-space:pre-wrap;">
                                        ${escapeHtmlAdmin(a.body)}
                                        ${a.image_path ? `<br><img src="/api/admin.php?action=ann_image&path=${encodeURIComponent(a.image_path)}" style="max-width:100%; border-radius:8px; margin-top:8px; cursor:pointer;" onclick="window.open(this.src)">` : ''}
                                    </div>
                                    <div style="font-size:10px; color:#BDBDBD; margin-top:4px;">${a.class_name || ''} · ${a.created_at ? a.created_at.slice(0, 16) : ''}</div>
                                </div>
                            `;
                        }).join('')}
                    </div>
                </div>
            `;

            // 공지 클릭 시 펼치기/접기 + 읽음 처리
            sectionEl.querySelectorAll('.ann-item').forEach(item => {
                item.addEventListener('click', async () => {
                    const preview = item.querySelector('.ann-body-preview');
                    const full = item.querySelector('.ann-body-full');
                    const annId = item.dataset.annId;

                    if (full.style.display === 'none') {
                        full.style.display = 'block';
                        preview.style.display = 'none';
                        // 읽음 처리
                        await App.get(`/api/admin.php?action=announcement_detail&announcement_id=${annId}`);
                        // 파란 점 제거
                        const dot = item.querySelector('[style*="background:#2196F3"]');
                        if (dot) dot.remove();
                    } else {
                        full.style.display = 'none';
                        preview.style.display = 'block';
                    }
                });
            });
        }

        // ============================================
        // 알림 배지 폴링
        // ============================================
        let unreadPollingTimer = null;

        function startUnreadPolling() {
            updateUnreadBadge();
            unreadPollingTimer = setInterval(() => {
                if (!document.hidden) updateUnreadBadge();
            }, 60000);
        }

        async function updateUnreadBadge() {
            const result = await App.get('/api/admin.php?action=msg_unread_total', null);
            if (!result.success) return;
            const total = result.unread_messages || 0;
            const badge = document.getElementById('unread-count-badge');
            if (badge) {
                if (total > 0) {
                    badge.textContent = total > 99 ? '99+' : total;
                    badge.style.display = 'block';
                } else {
                    badge.style.display = 'none';
                }
            }
        }

        // ============================================
        // 시작
        // ============================================
        document.addEventListener('DOMContentLoaded', () => {
            init();
        });

        return { loadStudents, loadStudentDashboard, openParentChat };
    })();
    </script>
    <script>
    // 시스템관리자 대행 로그인 패널 (시스템세션 감지 시 표시)
    (async () => {
        try {
            const r = await App.get('/api/system.php?action=check_session');
            if (!r.logged_in) return;
            const panel = document.getElementById('impersonate-admin-panel');
            const listEl = document.getElementById('imp-admin-list');
            panel.style.display = 'block';

            const ar = await App.get('/api/system.php?action=admin_list');
            if (ar.success && ar.admins) {
                const roleLabels = { admin_teacher: '관리쌤', parent: '부모님' };
                const roleColors = { admin_teacher: '#FF9800', parent: '#673AB7' };
                listEl.innerHTML = ar.admins.map(a => `
                    <button onclick="impersonateAdmin(${a.id})" style="display:flex; align-items:center; gap:10px; width:100%; padding:10px 12px; border:1.5px solid #F5F5F5; border-radius:10px; background:#fff; cursor:pointer; text-align:left; transition:all .15s; font-family:inherit;">
                        <div style="width:32px; height:32px; background:${roleColors[a.role] || '#999'}; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; color:#fff; font-size:13px; flex-shrink:0;">${a.name.charAt(0)}</div>
                        <div style="flex:1; min-width:0;">
                            <div style="font-weight:600; font-size:13px; color:#333;">${a.name}</div>
                            <div style="font-size:11px; color:#999; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${roleLabels[a.role] || a.role}${a.class_names ? ' / ' + a.class_names : ''}</div>
                        </div>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#ccc" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                    </button>
                `).join('');
                if (ar.admins.length === 0) {
                    listEl.innerHTML = '<div style="text-align:center; padding:12px; color:#999; font-size:13px;">등록된 관리쌤/부모님이 없습니다</div>';
                }
            }
        } catch(e) {}
    })();

    async function impersonateAdmin(adminId) {
        App.showLoading();
        const result = await App.post('/api/system.php?action=impersonate_admin', { admin_id: adminId });
        App.hideLoading();
        if (result.success) {
            Toast.success(result.message);
            setTimeout(() => location.reload(), 500);
        }
    }
    </script>
    <script src="/js/admin-dock.js?v=20260223c" data-adock-active="admin"></script>
</body>
</html>
