<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>시스템 관리 - 소리튠 주니어</title>
    <link rel="stylesheet" href="/css/common.css?v=20260215g">
    <link rel="stylesheet" href="/css/toast.css?v=20260215g">
    <link rel="stylesheet" href="/css/admin.css?v=20260215g">
    <link rel="stylesheet" href="/css/admin-dock.css?v=20260215g">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <style>
    .date-bar{display:flex;gap:4px;overflow-x:auto;padding:8px 0;-webkit-overflow-scrolling:touch;scrollbar-width:none;}
    .date-bar::-webkit-scrollbar{display:none;}
    .date-chip{flex-shrink:0;display:flex;flex-direction:column;align-items:center;padding:6px 10px;border-radius:12px;border:1.5px solid #E0E0E0;background:#fff;cursor:pointer;min-width:44px;transition:all .15s;font-family:inherit;}
    .date-chip:hover{border-color:#37474F;background:#ECEFF1;}
    .date-chip.active{background:#37474F;border-color:#37474F;}
    .date-chip.active .dc-day,.date-chip.active .dc-num{color:#fff!important;}
    .date-chip.today{border-color:#FF7E17;}
    .date-chip.today.active{border-color:#37474F;}
    .dc-day{font-size:10px;font-weight:600;}
    .dc-num{font-size:14px;font-weight:700;color:#333;}
    .check-toggle{cursor:pointer;display:inline-flex;padding:6px;border-radius:8px;transition:all .15s;}
    .check-toggle:hover{background:#E8F5E9;transform:scale(1.1);}
    .check-toggle.checked{background:#E8F5E9;}
    .check-toggle.loading{opacity:.4;pointer-events:none;}
    .num-stepper{display:inline-flex;align-items:center;gap:2px;background:#F5F5F5;border-radius:10px;padding:2px;}
    .num-stepper button{width:26px;height:26px;border:none;background:#fff;border-radius:7px;cursor:pointer;font-size:15px;font-weight:700;color:#37474F;display:flex;align-items:center;justify-content:center;transition:all .15s;font-family:inherit;line-height:1;}
    .num-stepper button:hover{background:#E0E0E0;}
    .num-stepper button:active{background:#BDBDBD;transform:scale(.9);}
    .num-stepper .stepper-val{min-width:22px;text-align:center;font-weight:700;font-size:15px;color:#333;}
    .num-stepper .stepper-val.has-val{color:#FF7E17;}
    .num-stepper.loading{opacity:.4;pointer-events:none;}
    .assign-ranking{display:flex;gap:8px;margin:8px 0 12px;overflow-x:auto;padding:4px 0;}
    .assign-ranking::-webkit-scrollbar{display:none;}
    .rank-card{display:flex;align-items:center;gap:8px;padding:10px 16px;background:#fff;border-radius:14px;box-shadow:0 2px 8px rgba(0,0,0,.06);flex-shrink:0;transition:transform .15s;}
    .rank-card:hover{transform:translateY(-2px);}
    .rank-1{background:linear-gradient(135deg,#FFF8E1,#FFE082);border:1.5px solid #FFD54F;}
    .rank-2{background:linear-gradient(135deg,#ECEFF1,#CFD8DC);border:1.5px solid #B0BEC5;}
    .rank-3{background:linear-gradient(135deg,#EFEBE9,#D7CCC8);border:1.5px solid #BCAAA4;}
    .rank-medal{font-size:20px;}
    .rank-name{font-weight:700;font-size:14px;color:#333;}
    .rank-score{font-size:12px;color:#757575;white-space:nowrap;}
    @media(max-width:768px){.date-chip.hide-mobile{display:none;}}

    /* 시스템 페이지 PC 너비 강제 */
    .system-page .app-container{max-width:1400px !important;}
    .system-page .admin-stats{display:block;}
    @media(min-width:1025px){
        .dash-kpi{gap:14px;}
        .kpi-card{padding:20px 14px;}
        .kpi-value{font-size:32px;}
        .kpi-label{font-size:13px;}
        .kpi-sub{font-size:11px;}
        .chart-card{padding:20px;}
        .chart-canvas-wrap{height:260px;}
        .dash-charts{gap:16px;margin-bottom:20px;}
        .dash-table th{padding:10px 14px;font-size:12px;}
        .dash-table td{padding:10px 14px;font-size:14px;}
    }

    /* 대시보드 날짜 네비게이션 */
    .dash-date-nav{display:flex;align-items:center;gap:8px;margin-bottom:14px;padding:10px 14px;background:#fff;border-radius:14px;box-shadow:0 2px 8px rgba(0,0,0,.06);}
    .dash-date-btn{width:36px;height:36px;border:none;border-radius:10px;background:#F5F5F5;font-size:14px;font-weight:700;color:#555;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .15s;}
    .dash-date-btn:hover{background:#E0E0E0;}
    .dash-date-btn:active{transform:scale(.93);}
    .dash-date-today{width:auto;padding:0 14px;background:linear-gradient(135deg,#FF7E17,#FF9800);color:#fff;font-size:13px;}
    .dash-date-today:hover{background:linear-gradient(135deg,#F57C00,#FF7E17);}
    .dash-date-input{flex:1;max-width:180px;height:36px;border:2px solid #E0E0E0;border-radius:10px;padding:0 12px;font-size:14px;font-weight:700;color:#333;text-align:center;background:#FAFAFA;transition:border-color .2s;}
    .dash-date-input:focus{outline:none;border-color:#FF7E17;}
    .dash-date-label{font-size:13px;color:#888;margin-left:4px;}

    /* 대시보드 강화 스타일 */
    .dash-section{margin-top:16px;}
    .dash-section-title{font-weight:800;font-size:15px;color:#37474F;margin-bottom:10px;display:flex;align-items:center;gap:6px;}
    .dash-kpi{display:grid;grid-template-columns:repeat(6,1fr);gap:10px;margin-bottom:16px;}
    .kpi-card{background:#fff;border-radius:14px;padding:14px;box-shadow:0 2px 8px rgba(0,0,0,.06);text-align:center;transition:transform .15s;}
    .kpi-card:hover{transform:translateY(-2px);}
    .kpi-value{font-size:28px;font-weight:800;line-height:1.2;}
    .kpi-label{font-size:11px;color:#999;margin-top:4px;}
    .kpi-sub{font-size:10px;color:#BDBDBD;margin-top:2px;}
    .dash-charts{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;}
    .chart-card{background:#fff;border-radius:14px;padding:14px;box-shadow:0 2px 8px rgba(0,0,0,.06);}
    .chart-card-title{font-weight:700;font-size:13px;color:#555;margin-bottom:10px;}
    .chart-canvas-wrap{position:relative;width:100%;height:200px;}
    .dash-table{width:100%;border-collapse:collapse;font-size:13px;}
    .dash-table th{text-align:left;padding:8px 10px;background:#F5F5F5;color:#666;font-weight:700;font-size:11px;border-bottom:2px solid #E0E0E0;}
    .dash-table td{padding:8px 10px;border-bottom:1px solid #F0F0F0;color:#333;}
    .dash-table tr:hover td{background:#FAFAFA;}
    .alert-card{display:flex;align-items:center;gap:10px;padding:12px 14px;border-radius:12px;margin-bottom:8px;border-left:4px solid;}
    .alert-warn{background:#FFF8E1;border-left-color:#FF9800;}
    .alert-danger{background:#FFEBEE;border-left-color:#F44336;}
    .alert-good{background:#E8F5E9;border-left-color:#4CAF50;}
    .alert-icon{font-size:20px;flex-shrink:0;}
    .alert-text{flex:1;min-width:0;}
    .alert-title{font-weight:700;font-size:13px;color:#333;}
    .alert-desc{font-size:11px;color:#888;margin-top:2px;}
    .progress-bar{height:8px;background:#E0E0E0;border-radius:4px;overflow:hidden;flex:1;}
    .progress-fill{height:100%;border-radius:4px;transition:width .4s ease;}
    @media(max-width:900px){
        .dash-kpi{grid-template-columns:repeat(3,1fr);}
    }
    @media(max-width:600px){
        .dash-charts{grid-template-columns:1fr;}
        .dash-kpi{grid-template-columns:repeat(2,1fr);}
    }
    </style>
</head>
<body class="system-page">
    <div class="app-container" id="app">
        <div class="app-header">
            <h1 style="cursor:pointer;" onclick="location.reload()">시스템 관리</h1>
            <button class="back-btn" id="btn-logout">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            </button>
        </div>

        <div style="padding:16px;">
            <div class="tabs" id="system-tabs">
                <button class="tab-btn active" data-tab="dashboard">대시보드</button>
                <button class="tab-btn" data-tab="students">학생 관리 <span class="tab-badge" id="badge-students">-</span></button>
                <button class="tab-btn" data-tab="admins">관리자 <span class="tab-badge" id="badge-admins">-</span></button>
                <button class="tab-btn" data-tab="classes">반 관리 <span class="tab-badge" id="badge-classes">-</span></button>
                <button class="tab-btn" data-tab="assignments">과제 현황</button>
                <button class="tab-btn" data-tab="settings">설정</button>
                <button class="tab-btn" data-tab="audit">감사 로그</button>
            </div>

            <div class="tab-content active" id="tab-dashboard">
                <div class="dash-date-nav" id="dash-date-nav">
                    <button class="dash-date-btn" onclick="SystemApp.dashDateMove(-1)" title="이전 날짜">◀</button>
                    <input type="date" id="dash-date-input" class="dash-date-input" onchange="SystemApp.dashDateChange(this.value)">
                    <button class="dash-date-btn" onclick="SystemApp.dashDateMove(1)" title="다음 날짜">▶</button>
                    <button class="dash-date-btn dash-date-today" onclick="SystemApp.dashDateToday()">오늘</button>
                </div>
                <div class="admin-stats" id="stats"></div>
                <div id="dashboard-charts"></div>
                <div id="dashboard-alerts"></div>
                <div id="dashboard-class-ranking"></div>
                <div id="dashboard-setup" style="margin-top:16px;padding:16px;background:#F8F9FA;border-radius:12px;">
                    <div style="font-weight:700;font-size:15px;margin-bottom:8px;">관리자 계정 관리</div>
                    <p style="font-size:13px;color:#6B7280;margin-bottom:12px;">시스템 관리자 계정을 직접 생성할 수 있는 1시간 유효 링크를 생성합니다. 링크를 통해 아이디/비밀번호를 설정하고 IP가 자동 등록됩니다.</p>
                    <button class="btn btn-primary" onclick="SystemApp.generateSetupLink()">관리자 생성 링크 만들기</button>
                    <div id="setup-link-result" style="margin-top:12px;display:none;"></div>
                </div>
            </div>

            <div class="tab-content" id="tab-students">
                <div class="toolbar">
                    <div class="toolbar-search">
                        <span class="toolbar-search-icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        </span>
                        <input type="text" id="student-search" placeholder="학생 이름 검색...">
                    </div>
                    <div class="toolbar-filter">
                        <select id="student-class-filter">
                            <option value="">전체 반</option>
                        </select>
                    </div>
                    <div class="toolbar-count" id="student-count">
                        총 <span class="count-num">-</span>명
                    </div>
                </div>
                <div class="toolbar" style="margin-bottom:8px;">
                    <button class="btn btn-primary btn-sm" id="btn-add-student" style="background:#37474F;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-2px; margin-right:4px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        학생 추가
                    </button>
                    <button class="btn btn-secondary btn-sm" id="btn-import-students">일괄 등록</button>
                </div>
                <!-- Bulk action bar -->
                <div class="bulk-action-bar" id="student-bulk-bar">
                    <div class="bulk-selected-count"><span id="bulk-count">0</span>명 선택됨</div>
                    <div class="bulk-actions">
                        <button class="bulk-btn bulk-btn-assign" id="bulk-assign">반 배정</button>
                        <button class="bulk-btn bulk-btn-delete" id="bulk-delete">삭제</button>
                        <button class="bulk-btn bulk-btn-cancel" id="bulk-cancel">취소</button>
                    </div>
                </div>
                <div id="student-list-content"></div>
            </div>

            <div class="tab-content" id="tab-admins">
                <div class="toolbar">
                    <div class="filter-chips" id="admin-filter-chips">
                        <button class="filter-chip active" data-role="">전체 <span class="chip-count" id="chip-all">-</span></button>
                        <button class="filter-chip" data-role="coach">코치쌤 <span class="chip-count" id="chip-coach">-</span></button>
                        <button class="filter-chip" data-role="admin_teacher">관리쌤 <span class="chip-count" id="chip-admin_teacher">-</span></button>
                        <button class="filter-chip" data-role="parent">부모 <span class="chip-count" id="chip-parent">-</span></button>
                    </div>
                    <button class="btn btn-primary btn-sm" id="btn-add-admin" style="background:#37474F;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-2px; margin-right:4px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        관리자 추가
                    </button>
                </div>
                <div id="admin-list-content"></div>
            </div>

            <div class="tab-content" id="tab-classes">
                <div class="toolbar">
                    <div class="toolbar-count" id="class-count">
                        총 <span class="count-num">-</span>개 반
                    </div>
                </div>
                <div id="class-list-content"></div>
            </div>

            <div class="tab-content" id="tab-assignments">
                <div class="toolbar" style="flex-wrap:wrap; gap:8px;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <button class="btn btn-sm btn-secondary" id="btn-assign-prev" title="이전 날짜">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                        </button>
                        <input type="date" id="assign-date" class="form-input" style="padding:6px 10px; width:auto;">
                        <button class="btn btn-sm btn-secondary" id="btn-assign-next" title="다음 날짜">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                        </button>
                        <button class="btn btn-sm btn-secondary" id="btn-assign-today">오늘</button>
                    </div>
                    <div class="toolbar-filter">
                        <select id="assign-class-filter">
                            <option value="">전체 반</option>
                        </select>
                    </div>
                    <div style="display:flex; gap:6px;">
                        <button class="btn btn-sm" id="btn-view-daily" style="background:#37474F; color:#fff;">일별</button>
                        <button class="btn btn-sm btn-secondary" id="btn-view-calendar">달력</button>
                    </div>
                </div>
                <div id="assign-content"></div>
            </div>

            <div class="tab-content" id="tab-settings">
                <div id="settings-content"></div>
            </div>

            <div class="tab-content" id="tab-audit">
                <div class="toolbar">
                    <div class="toolbar-search" style="min-width:150px;">
                        <input type="date" id="audit-from" class="form-input" style="padding:8px;">
                    </div>
                    <div class="toolbar-search" style="min-width:150px;">
                        <input type="date" id="audit-to" class="form-input" style="padding:8px;">
                    </div>
                    <button class="btn btn-secondary btn-sm" id="btn-filter-audit">필터</button>
                    <div class="toolbar-count" id="audit-count" style="display:none;">
                        총 <span class="count-num">-</span>건
                    </div>
                </div>
                <div id="audit-content"></div>
            </div>
        </div>
    </div>

    <script src="/js/toast.js?v=20260215g"></script>
    <script src="/js/common.js?v=20260215g"></script>
    <script>
    const SystemApp = (() => {
        let currentRole = '';
        let selectedStudents = new Set();
        let allStudentsData = [];
        let allAdminsData = [];
        let allClassesData = [];
        let studentPage = 1;
        let assignView = 'daily';
        let assignSort = { col: '', dir: 'desc' };
        let assignStateFromHash = null;
        let sessionRole = '';

        async function init() {
            let session = await App.get('/api/system.php?action=check_session');

            // 세션이 없으면 IP 자동 로그인 시도
            if (!session.logged_in) {
                try {
                    const ipCheckResult = await App.get('/api/system.php?action=check_ip_auto_login');
                    if (ipCheckResult.success && ipCheckResult.allowed) {
                        const ipLoginResult = await App.post('/api/system.php?action=ip_auto_login');
                        if (ipLoginResult.success) {
                            // 자동 로그인 성공, 세션 재확인
                            session = await App.get('/api/system.php?action=check_session');
                        }
                    }
                } catch (e) {
                    console.warn('IP auto-login failed:', e);
                }

                // 여전히 로그인되지 않았으면 로그인 페이지로
                if (!session.logged_in) {
                    window.location.href = '/system/login.php';
                    return;
                }
            }

            sessionRole = session.admin?.system_role || '';

            // 로그아웃
            document.getElementById('btn-logout').addEventListener('click', () => {
                App.confirm('로그아웃 하시겠습니까?', async () => {
                    await App.post('/api/system.php?action=logout');
                    window.location.href = '/system/login.php?logout=1';
                });
            });

            // 탭 (URL hash로 상태 유지)
            App.initTabs(document.getElementById('app'));
            document.getElementById('app').addEventListener('tabChange', (e) => {
                const tab = e.detail.tab;
                // URL hash 업데이트 (F5 새로고침 시 복원용)
                if (tab === 'assignments') {
                    updateAssignHash();
                } else {
                    history.replaceState(null, '', '#' + tab);
                }
                if (tab === 'dashboard') loadDashboard();
                else if (tab === 'students') loadStudents();
                else if (tab === 'admins') loadAdmins();
                else if (tab === 'classes') loadClasses();
                else if (tab === 'assignments') loadAssignments();
                else if (tab === 'settings') loadSettings();
                else if (tab === 'audit') loadAudit();
            });

            // 학생 검색
            document.getElementById('student-search').addEventListener('input',
                App.debounce(() => { studentPage = 1; loadStudents(); }, 400));

            // 반 필터
            document.getElementById('student-class-filter').addEventListener('change', () => {
                studentPage = 1;
                loadStudents();
            });

            // 학생 추가
            document.getElementById('btn-add-student').addEventListener('click', showAddStudentForm);
            document.getElementById('btn-import-students').addEventListener('click', showImportForm);

            // 일괄 작업
            document.getElementById('bulk-assign').addEventListener('click', bulkAssignClass);
            document.getElementById('bulk-delete').addEventListener('click', bulkDeleteStudents);
            document.getElementById('bulk-cancel').addEventListener('click', () => {
                selectedStudents.clear();
                updateBulkBar();
                document.querySelectorAll('#student-list-content input[type="checkbox"]').forEach(cb => {
                    cb.checked = false;
                    cb.closest('tr')?.classList.remove('selected');
                });
            });

            // 관리자 필터
            document.querySelectorAll('[data-role]').forEach(chip => {
                chip.addEventListener('click', () => {
                    document.querySelectorAll('[data-role]').forEach(c => c.classList.remove('active'));
                    chip.classList.add('active');
                    currentRole = chip.dataset.role;
                    loadAdmins();
                });
            });

            // 관리자 추가
            document.getElementById('btn-add-admin').addEventListener('click', showAddAdminForm);

            // 감사 필터
            document.getElementById('btn-filter-audit').addEventListener('click', loadAudit);

            // 과제 현황
            document.getElementById('assign-date').value = new Date().toISOString().split('T')[0];
            document.getElementById('btn-assign-prev').addEventListener('click', () => {
                const d = new Date(document.getElementById('assign-date').value);
                if (assignView === 'calendar') {
                    d.setMonth(d.getMonth() - 1);
                } else {
                    d.setDate(d.getDate() - 1);
                }
                document.getElementById('assign-date').value = d.toISOString().split('T')[0];
                loadAssignments();
            });
            document.getElementById('btn-assign-next').addEventListener('click', () => {
                const d = new Date(document.getElementById('assign-date').value);
                if (assignView === 'calendar') {
                    d.setMonth(d.getMonth() + 1);
                } else {
                    d.setDate(d.getDate() + 1);
                }
                document.getElementById('assign-date').value = d.toISOString().split('T')[0];
                loadAssignments();
            });
            document.getElementById('btn-assign-today').addEventListener('click', () => {
                document.getElementById('assign-date').value = new Date().toISOString().split('T')[0];
                loadAssignments();
            });
            document.getElementById('assign-date').addEventListener('change', loadAssignments);
            document.getElementById('assign-class-filter').addEventListener('change', loadAssignments);
            document.getElementById('btn-view-daily').addEventListener('click', () => {
                assignView = 'daily';
                syncViewButtons();
                loadAssignments();
            });
            document.getElementById('btn-view-calendar').addEventListener('click', () => {
                assignView = 'calendar';
                syncViewButtons();
                loadAssignments();
            });

            // 초기 데이터 로드 (URL hash에서 탭 + 파라미터 복원)
            const { tab: hashTab, params: hashParams } = parseHash();
            const validTabs = ['dashboard', 'students', 'admins', 'classes', 'assignments', 'settings', 'audit'];
            if (hashTab === 'assignments' && hashParams) {
                // 과제 현황 상태 복원 준비
                assignStateFromHash = {
                    date: hashParams.get('date'),
                    class_id: hashParams.get('class_id'),
                    view: hashParams.get('view'),
                    sortcol: hashParams.get('sortcol'),
                    sortdir: hashParams.get('sortdir'),
                };
            }
            if (hashTab && validTabs.includes(hashTab)) {
                const tabBtn = document.querySelector(`[data-tab="${hashTab}"]`);
                if (tabBtn) tabBtn.click();
            } else {
                loadDashboard();
            }
        }

        // ============================================
        // 대시보드 (종합)
        // ============================================
        let dashChartTrend = null;
        let dashChartDonut = null;
        let dashChartBar = null;
        let dashRankingDate = null;
        let dashDate = new Date().toISOString().slice(0, 10); // YYYY-MM-DD

        function initDashDate() {
            const input = document.getElementById('dash-date-input');
            if (input) {
                input.value = dashDate;
                input.max = new Date().toISOString().slice(0, 10);
            }
        }

        function dashDateChange(val) {
            if (!val) return;
            dashDate = val;
            const input = document.getElementById('dash-date-input');
            if (input) input.value = dashDate;
            loadDashboard(dashDate);
        }

        function dashDateMove(dir) {
            const d = new Date(dashDate + 'T00:00:00');
            d.setDate(d.getDate() + dir);
            const today = new Date();
            today.setHours(0,0,0,0);
            if (d > today) return;
            dashDate = d.toISOString().slice(0, 10);
            const input = document.getElementById('dash-date-input');
            if (input) input.value = dashDate;
            loadDashboard(dashDate);
        }

        function dashDateToday() {
            dashDate = new Date().toISOString().slice(0, 10);
            const input = document.getElementById('dash-date-input');
            if (input) input.value = dashDate;
            loadDashboard(dashDate);
        }

        async function loadDashboard(date) {
            if (!date) date = dashDate;
            initDashDate();

            const statsEl = document.getElementById('stats');
            const chartsEl = document.getElementById('dashboard-charts');
            const alertsEl = document.getElementById('dashboard-alerts');

            statsEl.innerHTML = '<div style="text-align:center; padding:40px;"><div class="loading-spinner" style="display:inline-block;"></div><div style="color:#999; margin-top:8px; font-size:13px;">대시보드 로딩 중...</div></div>';
            chartsEl.innerHTML = '';
            alertsEl.innerHTML = '';

            let students, admins, classes, enhanced;
            try {
                [students, admins, classes, enhanced] = await Promise.all([
                    App.get('/api/system.php?action=students'),
                    App.get('/api/system.php?action=admins'),
                    App.get('/api/system.php?action=classes'),
                    App.get('/api/system.php?action=dashboard_enhanced&date=' + encodeURIComponent(date)),
                ]);
            } catch (e) {
                console.error('Dashboard load error:', e);
                statsEl.innerHTML = '<div style="text-align:center; padding:40px; color:#F44336;">데이터 로드 중 오류가 발생했습니다. 새로고침 해주세요.</div>';
                return;
            }

            if (!students?.success || !admins?.success || !classes?.success) {
                statsEl.innerHTML = '<div style="text-align:center; padding:40px; color:#F44336;">데이터를 불러올 수 없습니다. 세션이 만료되었을 수 있습니다.</div>';
                return;
            }

            const coachCount = admins.admins?.filter(a => a.role === 'coach').length || 0;
            const teacherCount = admins.admins?.filter(a => a.role === 'admin_teacher').length || 0;
            const parentCount = admins.admins?.filter(a => a.role === 'parent').length || 0;
            const totalStudents = students.total || 0;
            const totalClasses = classes.classes?.length || 0;
            const totalAdmins = admins.admins?.length || 0;

            // 탭 뱃지 업데이트
            document.getElementById('badge-students').textContent = totalStudents;
            document.getElementById('badge-admins').textContent = totalAdmins;
            document.getElementById('badge-classes').textContent = totalClasses;

            // 반 필터 드롭다운 채우기
            if (classes.classes) {
                allClassesData = classes.classes;
                const classFilter = document.getElementById('student-class-filter');
                classFilter.innerHTML = '<option value="">전체 반</option>' +
                    classes.classes.map(c => `<option value="${c.id}">${c.display_name} (${c.student_count}명)</option>`).join('');

                const assignFilter = document.getElementById('assign-class-filter');
                if (assignFilter && assignFilter.options.length <= 1) {
                    assignFilter.innerHTML = '<option value="">전체 반</option>' +
                        classes.classes.map(c => `<option value="${c.id}">${c.display_name}</option>`).join('');
                }
            }

            // 관리자 칩 카운트
            document.getElementById('chip-all').textContent = totalAdmins;
            document.getElementById('chip-coach').textContent = coachCount;
            document.getElementById('chip-admin_teacher').textContent = teacherCount;
            document.getElementById('chip-parent').textContent = parentCount;

            // 선택 날짜 과제 데이터
            const t = enhanced?.success ? enhanced.today : { zoom:0, posture:0, homework:0, mission:0, leader:0, checked_students:0 };
            const todayRate = totalStudents > 0 ? Math.round((t.checked_students / totalStudents) * 100) : 0;
            const todayTotal = t.zoom + t.posture + t.homework + t.mission + t.leader;
            const rateColor = todayRate >= 80 ? '#4CAF50' : todayRate >= 50 ? '#FF9800' : todayRate > 0 ? '#F44336' : '#BDBDBD';
            const isToday = date === new Date().toISOString().slice(0, 10);
            const dateLabel = isToday ? '오늘' : App.formatDateKo ? App.formatDateKo(date + 'T00:00:00') : date;

            // 0%이고 최근 데이터 날짜가 있으면 자동으로 해당 날짜로 이동
            if (isToday && todayRate === 0 && enhanced?.last_data_date && enhanced.last_data_date !== date) {
                dashDate = enhanced.last_data_date;
                const input = document.getElementById('dash-date-input');
                if (input) input.value = dashDate;
                loadDashboard(dashDate);
                return;
            }

            // ========== KPI 카드 ==========
            const shareBtn = sessionRole === 'super_admin' ? `
                <div class="kpi-card" onclick="SystemApp.createInvite()" style="background:linear-gradient(135deg,#E8F5E9,#C8E6C9); cursor:pointer;">
                    <div class="kpi-value" style="font-size:18px; color:#2E7D32;">+ 초대</div>
                    <div class="kpi-label" style="color:#4CAF50;">관리자 초대 링크</div>
                </div>
            ` : '';

            statsEl.innerHTML = `
                <div class="dash-kpi">
                    <div class="kpi-card" style="background:linear-gradient(135deg,${todayRate >= 80 ? '#E8F5E9,#C8E6C9' : todayRate >= 50 ? '#FFF8E1,#FFE082' : '#FFEBEE,#FFCDD2'});">
                        <div class="kpi-value" style="color:${rateColor}; font-size:36px;">${todayRate}%</div>
                        <div class="kpi-label">${dateLabel} 전체 과제율</div>
                        <div class="kpi-sub">${t.checked_students}/${totalStudents}명 참여</div>
                    </div>
                    <div class="kpi-card" onclick="document.querySelector('[data-tab=students]').click()" style="cursor:pointer;">
                        <div class="kpi-value" style="color:#1565C0;">${App.formatNumber(totalStudents)}</div>
                        <div class="kpi-label">전체 학생</div>
                    </div>
                    <div class="kpi-card" onclick="document.querySelector('[data-tab=classes]').click()" style="cursor:pointer;">
                        <div class="kpi-value" style="color:#6A1B9A;">${totalClasses}</div>
                        <div class="kpi-label">반</div>
                    </div>
                    <div class="kpi-card" onclick="document.querySelector('[data-tab=admins]').click()" style="cursor:pointer;">
                        <div class="kpi-value" style="color:#37474F;">${totalAdmins}</div>
                        <div class="kpi-label">관리자</div>
                        <div class="kpi-sub">코치${coachCount} 관리쌤${teacherCount} 부모${parentCount}</div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-value" style="color:#E65100;">${todayTotal}</div>
                        <div class="kpi-label">${dateLabel} 총 완료 항목</div>
                        <div class="kpi-sub">전체 항목 합산</div>
                    </div>
                    ${shareBtn}
                </div>
            `;

            // ========== 차트 영역 ==========
            if (enhanced?.success) {
                chartsEl.innerHTML = `
                    <div class="dash-charts">
                        <div class="chart-card">
                            <div class="chart-card-title">최근 7일 과제 참여율 추세 (${date} 기준)</div>
                            <div class="chart-canvas-wrap"><canvas id="chart-trend"></canvas></div>
                        </div>
                        <div class="chart-card">
                            <div class="chart-card-title">${dateLabel} 항목별 완료 현황</div>
                            <div class="chart-canvas-wrap"><canvas id="chart-donut"></canvas></div>
                        </div>
                    </div>
                    <div class="dash-section">
                        <div class="dash-section-title">반별 과제 완료율 비교</div>
                        <div class="chart-card" style="padding:16px;">
                            <div class="chart-canvas-wrap" style="height:${Math.max(120, (enhanced.classes?.length || 1) * 36)}px;"><canvas id="chart-class-bar"></canvas></div>
                        </div>
                    </div>
                    <div class="dash-section">
                        <div class="dash-section-title">반별 상세 현황표</div>
                        <div class="chart-card" style="padding:0; overflow-x:auto;">
                            <table class="dash-table">
                                <thead>
                                    <tr>
                                        <th>반</th>
                                        <th>코치</th>
                                        <th>학생수</th>
                                        <th>참여</th>
                                        <th>과제율</th>
                                        <th>줌</th>
                                        <th>자세왕</th>
                                        <th>소리</th>
                                        <th>밴드</th>
                                        <th>리더</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${(enhanced.classes || []).map(c => {
                                        const r = c.completion_rate;
                                        const rc = r >= 80 ? '#4CAF50' : r >= 50 ? '#FF9800' : r > 0 ? '#F44336' : '#BDBDBD';
                                        return `<tr>
                                            <td style="font-weight:700;">${c.display_name}</td>
                                            <td style="color:#888;">${c.coach_name || '-'}</td>
                                            <td>${c.total_students}</td>
                                            <td>${c.checked_count}</td>
                                            <td><span style="font-weight:800; color:${rc};">${r}%</span></td>
                                            <td>${c.zoom_done}</td>
                                            <td>${c.posture_done}</td>
                                            <td>${c.homework_done}</td>
                                            <td>${c.mission_done}</td>
                                            <td>${c.leader_done}</td>
                                        </tr>`;
                                    }).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>
                `;

                // 차트 렌더링
                renderDashboardCharts(enhanced);
            }

            // ========== 알림/주의 카드 ==========
            renderDashboardAlerts(enhanced, totalStudents, admins.admins);

            // ========== 랭킹 (대시보드 선택 날짜 기준) ==========
            loadDashboardRanking(date);
        }

        // ============================================
        // 차트 렌더링
        // ============================================
        function renderDashboardCharts(data) {
            // 기존 차트 파괴
            if (dashChartTrend) dashChartTrend.destroy();
            if (dashChartDonut) dashChartDonut.destroy();
            if (dashChartBar) dashChartBar.destroy();

            const dayNames = ['일','월','화','수','목','금','토'];

            // 1) 7일 추세 라인 차트
            const trendCtx = document.getElementById('chart-trend');
            if (trendCtx && data.trend) {
                const labels = data.trend.map(d => {
                    const dt = new Date(d.date + 'T00:00:00');
                    return `${dt.getMonth()+1}/${dt.getDate()}(${dayNames[dt.getDay()]})`;
                });
                dashChartTrend = new Chart(trendCtx, {
                    type: 'line',
                    data: {
                        labels,
                        datasets: [{
                            label: '과제 참여율 (%)',
                            data: data.trend.map(d => d.completion_rate),
                            borderColor: '#FF7E17',
                            backgroundColor: 'rgba(255,126,23,0.1)',
                            borderWidth: 3,
                            tension: 0.3,
                            fill: true,
                            pointBackgroundColor: '#FF7E17',
                            pointRadius: 5,
                            pointHoverRadius: 7,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    afterLabel: (ctx) => {
                                        const d = data.trend[ctx.dataIndex];
                                        return `참여: ${d.checked_students}/${d.total_students}명`;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: { min: 0, max: 100, ticks: { callback: v => v + '%' } },
                            x: { ticks: { font: { size: 10 } } }
                        }
                    }
                });
            }

            // 2) 오늘 항목별 도넛 차트
            const donutCtx = document.getElementById('chart-donut');
            if (donutCtx && data.today) {
                const td = data.today;
                dashChartDonut = new Chart(donutCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['줌출석', '자세왕', '소리과제', '밴드미션', '리더왕'],
                        datasets: [{
                            data: [td.zoom, td.posture, td.homework, td.mission, td.leader],
                            backgroundColor: ['#F44336', '#9C27B0', '#FF7E17', '#2196F3', '#4CAF50'],
                            borderWidth: 2,
                            borderColor: '#fff',
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom', labels: { font: { size: 11 }, padding: 12 } },
                            tooltip: {
                                callbacks: {
                                    label: (ctx) => `${ctx.label}: ${ctx.raw}건`
                                }
                            }
                        }
                    }
                });
            }

            // 3) 반별 수평 바 차트
            const barCtx = document.getElementById('chart-class-bar');
            if (barCtx && data.classes) {
                const sorted = [...data.classes].sort((a, b) => b.completion_rate - a.completion_rate);
                dashChartBar = new Chart(barCtx, {
                    type: 'bar',
                    data: {
                        labels: sorted.map(c => c.display_name),
                        datasets: [{
                            label: '과제율 (%)',
                            data: sorted.map(c => c.completion_rate),
                            backgroundColor: sorted.map(c => {
                                const r = c.completion_rate;
                                return r >= 80 ? 'rgba(76,175,80,0.7)' : r >= 50 ? 'rgba(255,152,0,0.7)' : r > 0 ? 'rgba(244,67,54,0.7)' : 'rgba(189,189,189,0.5)';
                            }),
                            borderRadius: 6,
                            borderSkipped: false,
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    afterLabel: (ctx) => {
                                        const c = sorted[ctx.dataIndex];
                                        return `${c.checked_count}/${c.total_students}명 참여`;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: { min: 0, max: 100, ticks: { callback: v => v + '%' } },
                            y: { ticks: { font: { size: 12, weight: 'bold' } } }
                        }
                    }
                });
            }
        }

        // ============================================
        // 알림/주의 카드
        // ============================================
        function renderDashboardAlerts(enhanced, totalStudents, adminsList) {
            const alertsEl = document.getElementById('dashboard-alerts');
            if (!enhanced?.success) return;

            let alerts = [];
            const t = enhanced.today;
            const todayRate = totalStudents > 0 ? Math.round((t.checked_students / totalStudents) * 100) : 0;

            // 과제율 낮은 반 경고
            (enhanced.classes || []).forEach(c => {
                if (c.total_students > 0 && c.completion_rate === 0) {
                    alerts.push({ type: 'danger', icon: '\u{1F6A8}', title: `${c.display_name} - 과제 미참여`, desc: `담당: ${c.coach_name || '미배정'} / 학생 ${c.total_students}명 전원 미완료` });
                } else if (c.total_students > 0 && c.completion_rate < 50 && c.completion_rate > 0) {
                    alerts.push({ type: 'warn', icon: '\u{26A0}\u{FE0F}', title: `${c.display_name} - 과제율 저조 (${c.completion_rate}%)`, desc: `담당: ${c.coach_name || '미배정'} / ${c.checked_count}/${c.total_students}명 참여` });
                }
            });

            // 전체 과제율 높으면 칭찬
            if (todayRate >= 80) {
                alerts.unshift({ type: 'good', icon: '\u{1F389}', title: `오늘 전체 과제율 ${todayRate}% 달성!`, desc: `전체 ${totalStudents}명 중 ${t.checked_students}명 참여. 우수합니다!` });
            }

            // 최근 로그인 관리자
            const recentLogins = enhanced.recent_logins || [];
            if (recentLogins.length > 0) {
                const roleKo = { coach: '코치', admin_teacher: '관리쌤', parent: '부모' };
                const loginNames = recentLogins.slice(0, 5).map(l => `${l.name}(${roleKo[l.role] || l.role})`).join(', ');
                alerts.push({ type: 'good', icon: '\u{1F4CB}', title: `최근 24시간 활동 관리자 (${recentLogins.length}명)`, desc: loginNames });
            }

            if (alerts.length === 0) return;

            alertsEl.innerHTML = `
                <div class="dash-section">
                    <div class="dash-section-title">\u{1F514} 주요 알림</div>
                    ${alerts.map(a => `
                        <div class="alert-card alert-${a.type}">
                            <div class="alert-icon">${a.icon}</div>
                            <div class="alert-text">
                                <div class="alert-title">${a.title}</div>
                                <div class="alert-desc">${a.desc}</div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;
        }

        // ============================================
        // 대시보드 - 전체 반 과제율 랭킹
        // ============================================
        async function loadDashboardRanking(date) {
            dashRankingDate = date || new Date().toISOString().split('T')[0];

            const container = document.getElementById('dashboard-class-ranking');
            if (!container) return;

            container.innerHTML = '<div style="text-align:center; padding:20px;"><div class="loading-spinner" style="display:inline-block;"></div></div>';

            const result = await App.get('/api/system.php?action=class_assignment_ranking', { date: dashRankingDate });
            if (!result.success) {
                container.innerHTML = '<div style="text-align:center; padding:16px; color:#F44336;">랭킹 데이터를 불러올 수 없습니다</div>';
                return;
            }

            const dayNames = ['일','월','화','수','목','금','토'];
            const d = new Date(dashRankingDate + 'T00:00:00');
            const dateLabel = `${d.getMonth()+1}/${d.getDate()} (${dayNames[d.getDay()]})`;

            container.innerHTML = `
                <div class="dash-section" style="margin-top:16px;">
                    <div class="chart-card" style="padding:0; overflow:hidden;">
                        <div style="padding:14px 16px; background:linear-gradient(135deg,#FFF3E0,#FFE0B2);">
                            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;">
                                <div style="font-weight:800; font-size:16px; color:#E65100;">\u{1F3C6} 전체 반 과제율 랭킹</div>
                            </div>
                            <div style="display:flex; align-items:center; gap:6px;">
                                <button class="btn btn-sm" id="dash-ranking-prev" style="background:#fff; color:#333; border:1px solid #ddd; padding:4px 8px;">\u25C0</button>
                                <span style="font-weight:700; font-size:14px; color:#333; min-width:100px; text-align:center;" id="dash-ranking-date">${dateLabel}</span>
                                <button class="btn btn-sm" id="dash-ranking-next" style="background:#fff; color:#333; border:1px solid #ddd; padding:4px 8px;">\u25B6</button>
                                <button class="btn btn-sm" id="dash-ranking-today" style="background:#FF7E17; color:#fff; padding:4px 10px; font-size:11px;">오늘</button>
                            </div>
                        </div>
                        <div id="dash-ranking-list">
                            ${renderDashRankingList(result.classes)}
                        </div>
                    </div>
                </div>
            `;

            document.getElementById('dash-ranking-prev').addEventListener('click', () => {
                const dd = new Date(dashRankingDate);
                dd.setDate(dd.getDate() - 1);
                loadDashboardRanking(dd.toISOString().split('T')[0]);
            });
            document.getElementById('dash-ranking-next').addEventListener('click', () => {
                const dd = new Date(dashRankingDate);
                dd.setDate(dd.getDate() + 1);
                loadDashboardRanking(dd.toISOString().split('T')[0]);
            });
            document.getElementById('dash-ranking-today').addEventListener('click', () => {
                loadDashboardRanking(new Date().toISOString().split('T')[0]);
            });
        }

        function renderDashRankingList(classes) {
            if (!classes || classes.length === 0) {
                return '<div style="text-align:center; padding:20px; color:#999;">데이터가 없습니다</div>';
            }
            const medals = ['\u{1F947}', '\u{1F948}', '\u{1F949}'];
            return classes.map(c => {
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
            }).join('');
        }

        // ============================================
        // 학생 관리
        // ============================================
        async function loadStudents() {
            const search = document.getElementById('student-search').value.trim();
            const classId = document.getElementById('student-class-filter').value;
            const params = { search, page: studentPage };
            if (classId) params.class_id = classId;

            const result = await App.get('/api/system.php?action=students', params);
            if (!result.success) return;

            allStudentsData = result.students;
            selectedStudents.clear();
            updateBulkBar();

            // 카운트 업데이트
            document.getElementById('student-count').innerHTML = `총 <span class="count-num">${App.formatNumber(result.total)}</span>명`;
            document.getElementById('badge-students').textContent = result.total;

            const container = document.getElementById('student-list-content');
            if (result.students.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#9E9E9E" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                        </div>
                        <div class="empty-state-text">검색 결과가 없습니다</div>
                    </div>`;
                return;
            }

            const totalPages = Math.ceil(result.total / 50);

            container.innerHTML = `
                <table class="data-table">
                    <thead><tr>
                        <th class="col-check"><input type="checkbox" id="select-all-students" title="전체 선택"></th>
                        <th>학생</th>
                        <th>반</th>
                        <th>나이</th>
                        <th>등록일</th>
                        <th class="col-actions">관리</th>
                    </tr></thead>
                    <tbody>
                        ${result.students.map(s => `
                            <tr data-id="${s.id}">
                                <td class="col-check"><input type="checkbox" value="${s.id}" class="student-check"></td>
                                <td>
                                    <div class="student-name-cell" onclick="SystemApp.showStudentProfile(${s.id})" style="cursor:pointer;">
                                        <div class="student-avatar">${s.name.charAt(0)}</div>
                                        <div class="student-info">
                                            <div class="student-primary">${s.name}</div>
                                            ${s.phone_last4 ? `<div class="student-secondary">***-${s.phone_last4}</div>` : ''}
                                        </div>
                                    </div>
                                </td>
                                <td>${s.class_name ? `<span class="role-badge role-badge-coach">${s.class_name}</span>` : '<span style="color:#BDBDBD;">미배정</span>'}</td>
                                <td>${s.grade ? s.grade + '세' : '<span style="color:#BDBDBD;">-</span>'}</td>
                                <td style="font-size:12px; color:#757575;">${App.formatDate(s.created_at, 'YYYY-MM-DD')}</td>
                                <td class="col-actions">
                                    <button class="btn btn-sm btn-secondary" onclick="SystemApp.editStudent(${s.id}, '${escapeHtml(s.name)}')">수정</button>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>

                ${totalPages > 1 ? `
                <div class="pagination">
                    <button class="pagination-btn ${studentPage <= 1 ? 'disabled' : ''}" onclick="SystemApp.goPage(${studentPage - 1})">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                    </button>
                    <div class="pagination-info">${studentPage} / ${totalPages}</div>
                    <button class="pagination-btn ${studentPage >= totalPages ? 'disabled' : ''}" onclick="SystemApp.goPage(${studentPage + 1})">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                    </button>
                </div>
                ` : ''}

                <div class="list-summary">
                    <span>총 <strong>${App.formatNumber(result.total)}</strong>명의 학생</span>
                    <span>페이지 ${result.page} / ${totalPages || 1}</span>
                </div>
            `;

            // 체크박스 이벤트
            bindCheckboxEvents();
        }

        function bindCheckboxEvents() {
            // 전체 선택
            const selectAll = document.getElementById('select-all-students');
            if (selectAll) {
                selectAll.addEventListener('change', () => {
                    const checked = selectAll.checked;
                    document.querySelectorAll('.student-check').forEach(cb => {
                        cb.checked = checked;
                        const row = cb.closest('tr');
                        const id = parseInt(cb.value);
                        if (checked) {
                            selectedStudents.add(id);
                            row?.classList.add('selected');
                        } else {
                            selectedStudents.delete(id);
                            row?.classList.remove('selected');
                        }
                    });
                    updateBulkBar();
                });
            }

            // 개별 체크박스
            document.querySelectorAll('.student-check').forEach(cb => {
                cb.addEventListener('change', () => {
                    const id = parseInt(cb.value);
                    const row = cb.closest('tr');
                    if (cb.checked) {
                        selectedStudents.add(id);
                        row?.classList.add('selected');
                    } else {
                        selectedStudents.delete(id);
                        row?.classList.remove('selected');
                    }
                    updateBulkBar();

                    // 전체 선택 체크박스 동기화
                    const all = document.querySelectorAll('.student-check');
                    const checkedAll = document.querySelectorAll('.student-check:checked');
                    if (selectAll) selectAll.checked = all.length === checkedAll.length;
                });
            });
        }

        function updateBulkBar() {
            const bar = document.getElementById('student-bulk-bar');
            const count = selectedStudents.size;
            document.getElementById('bulk-count').textContent = count;
            if (count > 0) {
                bar.classList.add('show');
            } else {
                bar.classList.remove('show');
            }
        }

        function goPage(page) {
            studentPage = page;
            loadStudents();
        }

        // 일괄 반 배정
        async function bulkAssignClass() {
            if (selectedStudents.size === 0) return;

            const classOptions = allClassesData.map(c =>
                `<option value="${c.id}">${c.display_name}</option>`
            ).join('');

            const content = `
                <p style="margin-bottom:12px; font-size:14px; font-weight:600;">
                    <span style="color:#1565C0;">${selectedStudents.size}명</span>의 학생을 반에 배정합니다
                </p>
                <div class="form-group">
                    <label class="form-label">반 선택</label>
                    <select id="bulk-class-id" class="form-input">
                        <option value="">반을 선택하세요</option>
                        ${classOptions}
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">배정 유형</label>
                    <select id="bulk-primary" class="form-input">
                        <option value="1">본반 (기존 본반 해제 후 배정)</option>
                        <option value="0">타반 (추가 배정)</option>
                    </select>
                </div>
                <button class="btn btn-block" style="background:#37474F; color:#fff; margin-top:8px;" id="btn-do-bulk-assign">배정하기</button>
            `;
            const modal = App.openModal('일괄 반 배정', content);
            modal.querySelector('#btn-do-bulk-assign').onclick = async () => {
                const classId = modal.querySelector('#bulk-class-id').value;
                const isPrimary = parseInt(modal.querySelector('#bulk-primary').value);
                if (!classId) { Toast.warning('반을 선택해 주세요'); return; }

                App.showLoading();
                const result = await App.post('/api/system.php?action=bulk_assign_class', {
                    student_ids: [...selectedStudents],
                    class_id: parseInt(classId),
                    is_primary: isPrimary,
                });
                App.hideLoading();

                if (result.success) {
                    App.closeModal(modal);
                    Toast.success(result.message);
                    selectedStudents.clear();
                    updateBulkBar();
                    loadStudents();
                    loadDashboard();
                }
            };
        }

        // 일괄 삭제
        function bulkDeleteStudents() {
            if (selectedStudents.size === 0) return;

            App.confirm(`선택한 ${selectedStudents.size}명의 학생을 삭제하시겠습니까?`, async () => {
                App.showLoading();
                const result = await App.post('/api/system.php?action=bulk_delete_students', {
                    student_ids: [...selectedStudents],
                });
                App.hideLoading();

                if (result.success) {
                    Toast.success(result.message);
                    selectedStudents.clear();
                    updateBulkBar();
                    loadStudents();
                    loadDashboard();
                }
            });
        }

        function escapeHtml(str) {
            return str.replace(/'/g, "\\'").replace(/"/g, '&quot;');
        }

        function showAddStudentForm() {
            const classOptions = allClassesData.map(c =>
                `<option value="${c.id}">${c.display_name}</option>`
            ).join('');

            const content = `
                <div class="form-group">
                    <label class="form-label">이름 *</label>
                    <input type="text" id="new-student-name" class="form-input" placeholder="학생 이름">
                </div>
                <div class="form-group">
                    <label class="form-label">전화번호</label>
                    <input type="tel" id="new-student-phone" class="form-input" placeholder="010-1234-5678">
                </div>
                <div class="form-group">
                    <label class="form-label">나이</label>
                    <input type="text" id="new-student-grade" class="form-input" placeholder="예: 초3">
                </div>
                <div class="form-group">
                    <label class="form-label">반 배정</label>
                    <select id="new-student-class" class="form-input">
                        <option value="">나중에 배정</option>
                        ${classOptions}
                    </select>
                </div>
                <button class="btn btn-block" style="background:#37474F; color:#fff;" id="btn-save-student">등록</button>
            `;
            const modal = App.openModal('학생 추가', content);
            modal.querySelector('#btn-save-student').onclick = async () => {
                const name = modal.querySelector('#new-student-name').value.trim();
                if (!name) { Toast.warning('이름을 입력해 주세요'); return; }

                const result = await App.post('/api/system.php?action=students', {
                    sub_action: 'create',
                    name,
                    phone: modal.querySelector('#new-student-phone').value.trim(),
                    grade: modal.querySelector('#new-student-grade').value.trim(),
                    class_id: parseInt(modal.querySelector('#new-student-class').value) || 0,
                });
                if (result.success) {
                    App.closeModal(modal);
                    Toast.success(result.message);
                    loadStudents();
                    loadDashboard();
                }
            };
        }

        function showImportForm() {
            const classOptions = allClassesData.map(c =>
                `<option value="${c.id}">${c.display_name}</option>`
            ).join('');

            const content = `
                <p style="margin-bottom:12px; font-size:13px; color:#757575;">
                    한 줄에 학생 한 명씩 입력하세요.<br>
                    형식: 이름, 전화번호, 나이 (쉼표 구분)
                </p>
                <div class="form-group">
                    <label class="form-label">반 배정</label>
                    <select id="import-class" class="form-input">
                        <option value="">나중에 배정</option>
                        ${classOptions}
                    </select>
                </div>
                <textarea id="import-data" class="form-input" rows="10" placeholder="홍길동, 010-1234-5678, 초3&#10;김영희, 010-9876-5432, 초4"></textarea>
                <button class="btn btn-block" style="background:#37474F; color:#fff; margin-top:12px;" id="btn-do-import">일괄 등록</button>
            `;
            const modal = App.openModal('학생 일괄 등록', content);
            modal.querySelector('#btn-do-import').onclick = async () => {
                const raw = modal.querySelector('#import-data').value.trim();
                if (!raw) { Toast.warning('데이터를 입력해 주세요'); return; }

                const students = raw.split('\n').filter(l => l.trim()).map(line => {
                    const parts = line.split(',').map(p => p.trim());
                    return { name: parts[0], phone: parts[1] || '', grade: parts[2] || '' };
                });

                App.showLoading();
                const result = await App.post('/api/system.php?action=import_students', {
                    students,
                    class_id: parseInt(modal.querySelector('#import-class').value) || 0,
                });
                App.hideLoading();

                if (result.success) {
                    App.closeModal(modal);
                    Toast.success(result.message);
                    loadStudents();
                    loadDashboard();
                }
            };
        }

        function editStudent(id, name) {
            // 기존 데이터 로드
            const student = allStudentsData.find(s => s.id === id);
            const classOptions = allClassesData.map(c =>
                `<option value="${c.id}" ${student?.class_name === c.display_name ? 'selected' : ''}>${c.display_name}</option>`
            ).join('');

            const content = `
                <div class="form-group">
                    <label class="form-label">이름</label>
                    <input type="text" id="edit-name" class="form-input" value="${name}">
                </div>
                <div class="form-group">
                    <label class="form-label">전화번호</label>
                    <input type="tel" id="edit-phone" class="form-input" value="${student?.phone || ''}" placeholder="010-1234-5678">
                </div>
                <div class="form-group">
                    <label class="form-label">나이</label>
                    <input type="text" id="edit-grade" class="form-input" value="${student?.grade || ''}" placeholder="예: 10">
                </div>
                <div class="form-group">
                    <label class="form-label">반 변경</label>
                    <select id="edit-class" class="form-input">
                        <option value="">변경 안함</option>
                        ${classOptions}
                    </select>
                </div>
                <div style="display:flex; gap:8px; margin-top:8px;">
                    <button class="btn btn-block" style="background:#1565C0; color:#fff;" id="btn-view-cards">카드 현황</button>
                </div>
                <div style="display:flex; gap:8px; margin-top:12px;">
                    <button class="btn btn-block" style="background:#37474F; color:#fff;" id="btn-update">수정</button>
                    <button class="btn btn-danger btn-block" id="btn-delete" style="background:#F44336; color:#fff;">삭제</button>
                </div>
            `;
            const modal = App.openModal('학생 수정', content);
            modal.querySelector('#btn-view-cards').onclick = () => {
                App.closeModal(modal);
                showStudentProfile(id);
            };
            modal.querySelector('#btn-update').onclick = async () => {
                const updateName = modal.querySelector('#edit-name').value.trim();
                if (!updateName) { Toast.warning('이름을 입력해 주세요'); return; }

                App.showLoading();
                const result = await App.post('/api/system.php?action=students', {
                    sub_action: 'update',
                    id,
                    name: updateName,
                    phone: modal.querySelector('#edit-phone').value.trim(),
                    grade: modal.querySelector('#edit-grade').value.trim(),
                });

                // 반 변경
                const newClassId = parseInt(modal.querySelector('#edit-class').value);
                if (newClassId) {
                    await App.post('/api/system.php?action=assign_class', {
                        student_id: id,
                        class_id: newClassId,
                        is_primary: 1,
                    });
                }
                App.hideLoading();

                if (result.success) {
                    App.closeModal(modal);
                    Toast.success('수정되었습니다');
                    loadStudents();
                }
            };
            modal.querySelector('#btn-delete').onclick = () => {
                App.confirm('정말 삭제하시겠습니까?', async () => {
                    const result = await App.post('/api/system.php?action=students', { sub_action: 'delete', id });
                    if (result.success) {
                        App.closeModal(modal);
                        Toast.success('삭제되었습니다');
                        loadStudents();
                        loadDashboard();
                    }
                });
            };
        }

        // ============================================
        // 관리자
        // ============================================
        async function loadAdmins() {
            const result = await App.get('/api/system.php?action=admins', { role: currentRole });
            if (!result.success) return;

            allAdminsData = result.admins;
            const roleLabels = { coach: '코치쌤', admin_teacher: '관리쌤', parent: '부모' };
            const container = document.getElementById('admin-list-content');

            if (result.admins.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#9E9E9E" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        </div>
                        <div class="empty-state-text">관리자가 없습니다</div>
                    </div>`;
                return;
            }

            container.innerHTML = `
                <table class="data-table">
                    <thead><tr>
                        <th>관리자</th>
                        <th>아이디</th>
                        <th>역할</th>
                        <th>최근 로그인</th>
                        <th class="col-actions">관리</th>
                    </tr></thead>
                    <tbody>
                        ${result.admins.map(a => `
                            <tr>
                                <td>
                                    <div class="student-name-cell">
                                        <div class="student-avatar" style="background:${a.role === 'coach' ? '#E3F2FD' : a.role === 'admin_teacher' ? '#F3E5F5' : '#E8F5E9'}; color:${a.role === 'coach' ? '#1565C0' : a.role === 'admin_teacher' ? '#7B1FA2' : '#2E7D32'};">
                                            ${a.name.charAt(0)}
                                        </div>
                                        <div class="student-info">
                                            <div class="student-primary">${a.name}</div>
                                            ${a.phone ? `<div class="student-secondary">${a.phone}</div>` : ''}
                                        </div>
                                    </div>
                                </td>
                                <td style="font-family:monospace; font-size:12px; color:#757575;">${a.login_id}</td>
                                <td><span class="role-badge role-badge-${a.role}">${roleLabels[a.role] || a.role}</span></td>
                                <td style="font-size:12px; color:#757575;">${a.last_login_at ? App.formatDate(a.last_login_at, 'MM/DD HH:mm') : '<span style="color:#BDBDBD;">없음</span>'}</td>
                                <td class="col-actions" style="white-space:nowrap;">
                                    <button class="btn btn-sm" style="background:${a.role === 'coach' ? '#2196F3' : a.role === 'parent' ? '#4CAF50' : '#673AB7'}; color:#fff; font-size:11px; padding:4px 8px;" onclick="SystemApp.impersonateAdmin(${a.id}, '${a.role}')">접속</button>
                                    <button class="btn btn-sm btn-secondary" onclick="SystemApp.editAdmin(${a.id})">수정</button>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
                <div class="list-summary">
                    <span>총 <strong>${result.admins.length}</strong>명</span>
                    <span>${currentRole ? roleLabels[currentRole] + ' 필터' : '전체 역할'}</span>
                </div>
            `;
        }

        function showAddAdminForm() {
            const classOptions = allClassesData.map(c =>
                `<label style="display:flex; align-items:center; gap:6px; padding:6px 0;">
                    <input type="checkbox" value="${c.id}" class="admin-class-cb" style="width:16px; height:16px; accent-color:#37474F;">
                    ${c.display_name}
                </label>`
            ).join('');

            const content = `
                <div class="form-group">
                    <label class="form-label">아이디 *</label>
                    <input type="text" id="new-admin-login" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">비밀번호 *</label>
                    <input type="password" id="new-admin-pw" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">이름 *</label>
                    <input type="text" id="new-admin-name" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">역할 *</label>
                    <select id="new-admin-role" class="form-input">
                        <option value="coach">코치쌤</option>
                        <option value="admin_teacher">관리쌤</option>
                        <option value="parent">부모</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">전화번호</label>
                    <input type="tel" id="new-admin-phone" class="form-input">
                </div>
                <div class="form-group" id="admin-class-group">
                    <label class="form-label">담당 반 (다중 선택 가능)</label>
                    <div style="max-height:200px; overflow-y:auto; border:1px solid #eee; border-radius:8px; padding:8px 12px;">
                        ${classOptions}
                    </div>
                </div>
                <button class="btn btn-block" style="background:#37474F; color:#fff;" id="btn-save-admin">등록</button>
            `;
            const modal = App.openModal('관리자 추가', content);
            modal.querySelector('#btn-save-admin').onclick = async () => {
                const classIds = [...modal.querySelectorAll('.admin-class-cb:checked')].map(cb => parseInt(cb.value));

                const result = await App.post('/api/system.php?action=admins', {
                    sub_action: 'create',
                    login_id: modal.querySelector('#new-admin-login').value.trim(),
                    password: modal.querySelector('#new-admin-pw').value.trim(),
                    name: modal.querySelector('#new-admin-name').value.trim(),
                    role: modal.querySelector('#new-admin-role').value,
                    phone: modal.querySelector('#new-admin-phone').value.trim(),
                    class_ids: classIds,
                });
                if (result.success) {
                    App.closeModal(modal);
                    Toast.success(result.message);
                    loadAdmins();
                    loadDashboard();
                }
            };
        }

        function editAdmin(id) {
            const admin = allAdminsData.find(a => a.id === id);
            const content = `
                <div class="form-group">
                    <label class="form-label">이름</label>
                    <input type="text" id="edit-admin-name" class="form-input" value="${admin?.name || ''}">
                </div>
                <div class="form-group">
                    <label class="form-label">새 비밀번호 (변경 시에만)</label>
                    <input type="password" id="edit-admin-pw" class="form-input" placeholder="변경 안하면 비워두세요">
                </div>
                <div class="form-group">
                    <label class="form-label">전화번호</label>
                    <input type="tel" id="edit-admin-phone" class="form-input" value="${admin?.phone || ''}">
                </div>
                <button class="btn btn-block" style="background:#37474F; color:#fff;" id="btn-update-admin">수정</button>
            `;
            const modal = App.openModal('관리자 수정', content);
            modal.querySelector('#btn-update-admin').onclick = async () => {
                const data = { sub_action: 'update', id };
                const name = modal.querySelector('#edit-admin-name').value.trim();
                const pw = modal.querySelector('#edit-admin-pw').value.trim();
                const phone = modal.querySelector('#edit-admin-phone').value.trim();
                if (name) data.name = name;
                if (pw) data.password = pw;
                if (phone) data.phone = phone;

                const result = await App.post('/api/system.php?action=admins', data);
                if (result.success) {
                    App.closeModal(modal);
                    Toast.success('수정되었습니다');
                    loadAdmins();
                }
            };
        }

        // ============================================
        // 반 관리 (카드형)
        // ============================================
        async function loadClasses() {
            const result = await App.get('/api/system.php?action=classes');
            if (!result.success) return;

            allClassesData = result.classes;
            const totalStudents = result.classes.reduce((sum, c) => sum + (parseInt(c.student_count) || 0), 0);

            document.getElementById('class-count').innerHTML = `총 <span class="count-num">${result.classes.length}</span>개 반 &middot; 학생 <span class="count-num">${App.formatNumber(totalStudents)}</span>명`;
            document.getElementById('badge-classes').textContent = result.classes.length;

            document.getElementById('class-list-content').innerHTML = `
                <div class="class-cards">
                    ${result.classes.map(c => `
                        <div class="class-card" onclick="SystemApp.showClassDetail(${c.id})" title="클릭하여 상세보기">
                            <img src="/images/${c.logo_image}" class="class-card-logo" alt="${c.display_name}">
                            <div class="class-card-body">
                                <div class="class-card-name">${c.display_name}</div>
                                <div class="class-card-info">
                                    <span class="class-card-count">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                                        ${c.student_count}명
                                    </span>
                                    <span class="class-card-coach">${c.coach_name || '코치 미배정'}</span>
                                </div>
                            </div>
                            <div class="class-card-edit">수정하기</div>
                        </div>
                    `).join('')}
                </div>
            `;
        }

        function editClass(id, name, coachName) {
            const content = `
                <div class="form-group">
                    <label class="form-label">${name} 반</label>
                </div>
                <div class="form-group">
                    <label class="form-label">코치쌤 이름</label>
                    <input type="text" id="edit-coach-name" class="form-input" value="${coachName}">
                </div>
                <button class="btn btn-block" style="background:#37474F; color:#fff;" id="btn-update-class">수정</button>
            `;
            const modal = App.openModal('반 수정', content);
            modal.querySelector('#btn-update-class').onclick = async () => {
                const result = await App.post('/api/system.php?action=classes', {
                    id,
                    coach_name: modal.querySelector('#edit-coach-name').value.trim(),
                });
                if (result.success) {
                    App.closeModal(modal);
                    Toast.success('수정되었습니다');
                    loadClasses();
                }
            };
        }

        // ============================================
        // 설정
        // ============================================
        async function loadSettings() {
            const [settingsResult, tempResult] = await Promise.all([
                App.get('/api/system.php?action=settings'),
                App.get('/api/system.php?action=temp_access_list').catch(() => ({ success: false })),
            ]);
            if (!settingsResult.success) return;

            const settings = settingsResult.settings;
            const tempTokens = tempResult.success ? (tempResult.tokens || []) : [];

            // qr_bg_image 찾기
            const bgSetting = settings.find(s => s.setting_key === 'qr_bg_image');
            const bgValue = bgSetting ? bgSetting.setting_value : '';
            const bgUpdatedBy = bgSetting ? bgSetting.updated_by_name : null;
            const bgUpdatedAt = bgSetting ? bgSetting.updated_at : null;

            // 일반 설정 (qr_bg_image 제외)
            const normalSettings = settings.filter(s => s.setting_key !== 'qr_bg_image');

            // 활성 임시 토큰
            const activeTokens = tempTokens.filter(t => t.is_active && new Date(t.expires_at) > new Date());

            document.getElementById('settings-content').innerHTML = `
                <!-- QR 배경 이미지 섹션 -->
                <div class="settings-section" style="margin-bottom:20px;">
                    <div class="settings-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#37474F" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        QR 미리보기 배경 이미지
                    </div>
                    <div style="background:#F5F5F5; border-radius:14px; padding:16px; margin-bottom:12px;">
                        <div style="display:flex; gap:16px; align-items:flex-start; flex-wrap:wrap;">
                            <div id="bg-preview" style="width:160px; height:100px; border-radius:10px; border:1.5px dashed #ccc; overflow:hidden; display:flex; align-items:center; justify-content:center; background:#fff; flex-shrink:0;">
                                ${bgValue ? `<img src="/images/${bgValue}?t=${Date.now()}" style="width:100%; height:100%; object-fit:cover;" onerror="this.parentElement.innerHTML='<span style=color:#999;font-size:12px>이미지 없음</span>'">` : '<span style="color:#999; font-size:12px;">이미지 없음</span>'}
                            </div>
                            <div style="flex:1; min-width:200px;">
                                <div style="font-size:13px; color:#555; margin-bottom:8px;">
                                    현재: <code style="background:#E8EAF6; padding:2px 6px; border-radius:4px; font-size:12px;">${bgValue || '없음'}</code>
                                </div>
                                ${bgUpdatedBy ? `<div style="font-size:12px; color:#9E9E9E; margin-bottom:10px;">
                                    최종 수정: <strong style="color:#555;">${bgUpdatedBy}</strong> (${App.formatDate(bgUpdatedAt, 'YYYY-MM-DD HH:mm')})
                                </div>` : ''}
                                <div style="display:flex; gap:8px; align-items:center;">
                                    <label style="display:inline-flex; align-items:center; gap:6px; padding:8px 14px; background:#37474F; color:#fff; border-radius:10px; cursor:pointer; font-size:13px; font-weight:600; font-family:inherit; transition:all .15s;">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                        이미지 변경
                                        <input type="file" id="bg-file-input" accept="image/png,image/jpeg,image/webp" style="display:none;">
                                    </label>
                                    <span id="bg-upload-status" style="font-size:12px; color:#999;"></span>
                                </div>
                                <div style="font-size:11px; color:#BDBDBD; margin-top:6px;">PNG, JPG, WebP / 최대 5MB</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 부모 접속 QR 코드 -->
                <div class="settings-section" style="margin-bottom:20px;">
                    <div class="settings-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#37474F" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="3" height="3"/><line x1="21" y1="14" x2="21" y2="21"/><line x1="14" y1="21" x2="21" y2="21"/></svg>
                        부모 접속 QR 코드
                    </div>
                    <div style="background:#F3E5F5; border:1.5px solid #CE93D8; border-radius:12px; padding:12px 14px; margin-bottom:14px; font-size:13px; color:#6A1B9A; line-height:1.5;">
                        부모님이 이 QR을 스캔하면 전화번호 인증 후 자녀 학습 현황을 확인할 수 있습니다.
                        이 QR은 고정 URL이므로 인쇄하여 배포할 수 있습니다.
                    </div>
                    <div style="text-align:center; padding:16px;">
                        <div id="parent-qr-image" style="display:inline-block; background:#fff; padding:16px; border-radius:16px; box-shadow:0 2px 12px rgba(0,0,0,.08);">
                            <img id="parent-qr-img" src="" alt="부모 접속 QR" style="width:200px; height:200px; display:block;">
                        </div>
                        <div style="margin-top:12px; font-size:13px; color:#555;">
                            <code style="background:#EDE7F6; padding:4px 10px; border-radius:6px; font-size:12px; color:#673AB7;">https://j.soritune.com/admin/</code>
                        </div>
                        <div style="margin-top:12px; display:flex; gap:8px; justify-content:center;">
                            <button class="btn btn-sm" id="btn-download-parent-qr" style="background:#673AB7; color:#fff;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-2px; margin-right:4px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                QR 다운로드
                            </button>
                            <button class="btn btn-sm" id="btn-copy-parent-url" style="background:#455A64; color:#fff;">
                                URL 복사
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 일반 설정 -->
                <div class="settings-section" style="margin-bottom:20px;">
                    <div class="settings-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#37474F" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                        시스템 설정
                    </div>
                    ${normalSettings.map(s => {
                        const updatedInfo = s.updated_by_name
                            ? `<div style="font-size:11px; color:#BDBDBD; margin-top:3px;">최종: ${s.updated_by_name} (${App.formatDate(s.updated_at, 'MM-DD HH:mm')})</div>`
                            : '';
                        return `
                        <div class="setting-row">
                            <div class="form-group" style="margin-bottom:0;">
                                <label class="form-label">${s.description || s.setting_key}</label>
                                <div class="setting-key">${s.setting_key} ${updatedInfo}</div>
                                <input type="text" class="form-input setting-input" data-key="${s.setting_key}" value="${s.setting_value || ''}" style="margin-top:6px;">
                            </div>
                        </div>`;
                    }).join('')}
                    <button class="btn btn-block" style="background:#37474F; color:#fff; margin-top:16px;" id="btn-save-settings">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-2px; margin-right:4px;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        설정 저장
                    </button>
                </div>

                <!-- 임시 IP 접속 -->
                <div class="settings-section">
                    <div class="settings-title">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#37474F" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        임시 IP 접속 링크
                    </div>
                    <div style="background:#FFF3E0; border:1.5px solid #FFE0B2; border-radius:12px; padding:12px 14px; margin-bottom:14px; font-size:13px; color:#E65100; line-height:1.5;">
                        IP가 변경되어 시스템에 접속할 수 없을 때, 아래 링크를 생성하여 새 IP를 임시 등록할 수 있습니다.
                        링크는 1회 사용 가능하며, 설정한 시간이 지나면 자동 만료됩니다.
                    </div>
                    <div style="display:flex; gap:8px; align-items:center; margin-bottom:16px;">
                        <select id="temp-hours" class="form-input" style="width:auto; padding:8px 12px;">
                            <option value="1">1시간</option>
                            <option value="2">2시간</option>
                            <option value="4">4시간</option>
                            <option value="8">8시간</option>
                            <option value="24">24시간</option>
                        </select>
                        <button class="btn" style="background:#FF6F00; color:#fff; white-space:nowrap; padding:9px 18px;" id="btn-gen-temp">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-2px; margin-right:4px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            링크 생성
                        </button>
                    </div>
                    <div id="temp-generated" style="display:none; background:#E8F5E9; border:1.5px solid #A5D6A7; border-radius:12px; padding:14px; margin-bottom:16px;">
                        <div style="font-weight:700; font-size:13px; color:#2E7D32; margin-bottom:8px;">생성된 링크</div>
                        <input type="text" id="temp-url" class="form-input" readonly style="font-size:12px; background:#fff; cursor:text; margin-bottom:8px;">
                        <div style="display:flex; gap:6px;">
                            <button class="btn btn-sm" style="background:#4CAF50; color:#fff;" id="btn-copy-temp">복사</button>
                            <span id="temp-expires-info" style="font-size:12px; color:#666; line-height:28px;"></span>
                        </div>
                    </div>
                    ${activeTokens.length > 0 ? `
                    <div style="margin-top:8px;">
                        <div style="font-size:13px; font-weight:600; color:#555; margin-bottom:8px;">활성 토큰 (${activeTokens.length}개)</div>
                        ${activeTokens.map(t => {
                            const isUsed = !!t.ip_address;
                            const statusColor = isUsed ? '#4CAF50' : '#FF9800';
                            const statusText = isUsed ? t.ip_address : '미사용';
                            return `
                            <div style="display:flex; align-items:center; gap:10px; padding:10px 12px; background:#F5F5F5; border-radius:10px; margin-bottom:6px; font-size:12px;">
                                <div style="width:8px; height:8px; border-radius:50%; background:${statusColor}; flex-shrink:0;"></div>
                                <div style="flex:1; min-width:0;">
                                    <div style="font-weight:600; color:#333;">${statusText}</div>
                                    <div style="color:#999;">만료: ${App.formatDate(t.expires_at, 'MM-DD HH:mm')} / 생성: ${t.created_by_name || '-'}</div>
                                </div>
                            </div>`;
                        }).join('')}
                    </div>` : ''}
                </div>
            `;

            // 부모 접속 QR 코드 생성
            (async () => {
                try {
                    const qrResult = await App.get('/api/system.php?action=generate_parent_qr');
                    if (qrResult.success && qrResult.qr_image) {
                        const imgEl = document.getElementById('parent-qr-img');
                        if (imgEl) imgEl.src = qrResult.qr_image;
                    }
                } catch (e) {
                    console.warn('Parent QR generation failed:', e);
                }
            })();

            // 부모 QR 다운로드
            document.getElementById('btn-download-parent-qr').addEventListener('click', () => {
                const img = document.getElementById('parent-qr-img');
                if (!img || !img.src) return;
                const link = document.createElement('a');
                link.download = 'soritune-parent-qr.png';
                link.href = img.src;
                link.click();
            });

            // 부모 URL 복사
            document.getElementById('btn-copy-parent-url').addEventListener('click', () => {
                navigator.clipboard.writeText('https://j.soritune.com/admin/').then(() => {
                    Toast.success('URL이 복사되었습니다');
                }).catch(() => {
                    // fallback
                    const ta = document.createElement('textarea');
                    ta.value = 'https://j.soritune.com/admin/';
                    document.body.appendChild(ta);
                    ta.select();
                    document.execCommand('copy');
                    document.body.removeChild(ta);
                    Toast.success('URL이 복사되었습니다');
                });
            });

            // QR 배경 이미지 업로드 핸들러
            document.getElementById('bg-file-input').addEventListener('change', async (e) => {
                const file = e.target.files[0];
                if (!file) return;

                if (file.size > 5 * 1024 * 1024) {
                    Toast.error('파일 크기는 5MB 이하만 가능합니다');
                    return;
                }

                const statusEl = document.getElementById('bg-upload-status');
                statusEl.textContent = '업로드 중...';
                statusEl.style.color = '#FF9800';

                const formData = new FormData();
                formData.append('bg_image', file);

                try {
                    const resp = await fetch('/api/system.php?action=upload_bg_image', {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin',
                    });
                    const data = await resp.json();

                    if (data.success) {
                        Toast.success(data.message);
                        statusEl.textContent = '완료!';
                        statusEl.style.color = '#4CAF50';
                        // 미리보기 갱신
                        document.getElementById('bg-preview').innerHTML =
                            `<img src="${data.url}?t=${Date.now()}" style="width:100%;height:100%;object-fit:cover;">`;
                        setTimeout(() => loadSettings(), 1000);
                    } else {
                        Toast.error(data.error || '업로드 실패');
                        statusEl.textContent = '실패';
                        statusEl.style.color = '#F44336';
                    }
                } catch (err) {
                    Toast.error('네트워크 오류');
                    statusEl.textContent = '오류';
                    statusEl.style.color = '#F44336';
                }
                e.target.value = '';
            });

            // 설정 저장
            document.getElementById('btn-save-settings').addEventListener('click', async () => {
                const items = [];
                document.querySelectorAll('.setting-input').forEach(input => {
                    items.push({ key: input.dataset.key, value: input.value });
                });

                App.showLoading();
                const result = await App.post('/api/system.php?action=settings', { items });
                App.hideLoading();
                if (result.success) {
                    Toast.success(result.message);
                    loadSettings(); // 최종 수정 정보 갱신
                }
            });

            // 임시 IP 링크 생성
            document.getElementById('btn-gen-temp').addEventListener('click', async () => {
                const hours = parseInt(document.getElementById('temp-hours').value);
                App.showLoading();
                const result = await App.post('/api/system.php?action=generate_temp_access', { hours });
                App.hideLoading();

                if (result.success) {
                    Toast.success(result.message);
                    const genPanel = document.getElementById('temp-generated');
                    genPanel.style.display = 'block';
                    document.getElementById('temp-url').value = result.url;
                    document.getElementById('temp-expires-info').textContent = `만료: ${result.expires_at}`;
                }
            });

            // 복사 버튼
            document.addEventListener('click', (e) => {
                if (e.target.id === 'btn-copy-temp') {
                    const url = document.getElementById('temp-url').value;
                    navigator.clipboard.writeText(url).then(() => {
                        Toast.success('링크가 복사되었습니다');
                    }).catch(() => {
                        document.getElementById('temp-url').select();
                        document.execCommand('copy');
                        Toast.success('링크가 복사되었습니다');
                    });
                }
            });
        }

        // ============================================
        // 감사 로그
        // ============================================
        async function loadAudit() {
            const dateFrom = document.getElementById('audit-from').value;
            const dateTo = document.getElementById('audit-to').value;

            const params = {};
            if (dateFrom) params.date_from = dateFrom;
            if (dateTo) params.date_to = dateTo;

            const result = await App.get('/api/system.php?action=audit_log', params);
            if (!result.success) return;

            // 카운트 뱃지
            const auditCount = document.getElementById('audit-count');
            auditCount.style.display = 'inline-flex';
            auditCount.innerHTML = `총 <span class="count-num">${App.formatNumber(result.total)}</span>건`;

            const container = document.getElementById('audit-content');
            if (result.logs.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#9E9E9E" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        </div>
                        <div class="empty-state-text">감사 로그가 없습니다</div>
                    </div>`;
                return;
            }

            const actionLabels = { create: '생성', update: '수정', delete: '삭제' };

            container.innerHTML = `
                <div class="card" style="padding:0; border-radius:16px; overflow:hidden; box-shadow:0 2px 12px rgba(0,0,0,0.08);">
                    ${result.logs.map(log => `
                        <div class="audit-item">
                            <div class="audit-meta">
                                <span class="audit-action audit-action-${log.action}">${actionLabels[log.action] || log.action}</span>
                                <span class="audit-who">${log.changed_by_name || '시스템'}</span>
                                <span class="audit-when">${App.formatDate(log.created_at, 'MM/DD HH:mm')}</span>
                            </div>
                            <div class="audit-detail">${log.table_name} #${log.record_id} ${log.field_name ? `(${log.field_name})` : ''}</div>
                            ${log.old_value || log.new_value ? `<div class="audit-changes">${log.old_value || '-'} → ${log.new_value || '-'}</div>` : ''}
                            ${log.reason ? `<div style="font-size:12px; color:#757575; margin-top:2px;">사유: ${log.reason}</div>` : ''}
                        </div>
                    `).join('')}
                </div>
                <div class="list-summary">
                    <span>총 <strong>${App.formatNumber(result.total)}</strong>건</span>
                    <span>${dateFrom || dateTo ? '필터 적용됨' : '전체 기간'}</span>
                </div>
            `;
        }

        // ============================================
        // 과제 현황 헬퍼 함수
        // ============================================
        function parseHash() {
            const hash = location.hash.replace('#', '');
            const qIdx = hash.indexOf('?');
            const tab = qIdx >= 0 ? hash.substring(0, qIdx) : hash;
            const params = qIdx >= 0 ? new URLSearchParams(hash.substring(qIdx + 1)) : null;
            return { tab, params };
        }

        function updateAssignHash() {
            const date = document.getElementById('assign-date').value;
            const classId = document.getElementById('assign-class-filter').value;
            const params = new URLSearchParams();
            if (date) params.set('date', date);
            if (classId) params.set('class_id', classId);
            params.set('view', assignView);
            if (assignSort.col) {
                params.set('sortcol', assignSort.col);
                params.set('sortdir', assignSort.dir);
            }
            history.replaceState(null, '', '#assignments?' + params.toString());
        }

        function toggleSort(col) {
            if (assignSort.col === col) {
                assignSort.dir = assignSort.dir === 'asc' ? 'desc' : 'asc';
            } else {
                assignSort.col = col;
                assignSort.dir = 'desc';
            }
            updateAssignHash();
            if (assignView === 'daily') loadAssignmentDaily();
            else loadAssignmentCalendar();
        }

        function sortIndicator(col) {
            if (assignSort.col !== col) return '<span style="opacity:0.3; font-size:10px;">⇅</span>';
            return assignSort.dir === 'asc'
                ? '<span style="color:#37474F; font-weight:700;">↑</span>'
                : '<span style="color:#37474F; font-weight:700;">↓</span>';
        }

        function syncViewButtons() {
            const dailyBtn = document.getElementById('btn-view-daily');
            const calBtn = document.getElementById('btn-view-calendar');
            if (assignView === 'daily') {
                dailyBtn.style.background = '#37474F'; dailyBtn.style.color = '#fff';
                dailyBtn.classList.remove('btn-secondary');
                calBtn.style.background = ''; calBtn.style.color = '';
                calBtn.classList.add('btn-secondary');
            } else {
                calBtn.style.background = '#37474F'; calBtn.style.color = '#fff';
                calBtn.classList.remove('btn-secondary');
                dailyBtn.style.background = ''; dailyBtn.style.color = '';
                dailyBtn.classList.add('btn-secondary');
            }
        }

        function sortData(data, col, dir) {
            if (!col) return data;
            return [...data].sort((a, b) => {
                let va = a[col], vb = b[col];
                if (typeof va === 'string') {
                    va = (va || '').toLowerCase(); vb = (vb || '').toLowerCase();
                    return dir === 'asc' ? va.localeCompare(vb, 'ko') : vb.localeCompare(va, 'ko');
                }
                va = Number(va) || 0; vb = Number(vb) || 0;
                return dir === 'asc' ? va - vb : vb - va;
            });
        }

        // ============================================
        // 날짜 바 + 체크 토글
        // ============================================
        function generateDateBar(selectedDate) {
            const today = new Date();
            today.setHours(0,0,0,0);
            const dn = ['일','월','화','수','목','금','토'];
            const dates = [];
            for (let i = 13; i >= 0; i--) {
                const d = new Date(today);
                d.setDate(d.getDate() - i);
                dates.push(d);
            }
            return '<div class="date-bar" id="date-bar">' + dates.map((d, i) => {
                const ds = d.toISOString().split('T')[0];
                const sel = ds === selectedDate;
                const td = d.getTime() === today.getTime();
                const dow = d.getDay();
                const dc = dow === 0 ? '#F44336' : dow === 6 ? '#1565C0' : '#757575';
                return `<button class="date-chip${sel ? ' active' : ''}${td ? ' today' : ''}${i < 7 ? ' hide-mobile' : ''}" onclick="SystemApp.quickDate('${ds}')"><span class="dc-day" style="color:${sel ? '#fff' : dc}">${dn[dow]}</span><span class="dc-num">${d.getDate()}</span></button>`;
            }).join('') + '</div>';
        }

        function quickDate(dateStr) {
            document.getElementById('assign-date').value = dateStr;
            loadAssignments();
        }

        async function toggleCheck(studentId, classId, date, field, newValue, el) {
            el.classList.add('loading');
            const result = await App.post('/api/system.php?action=toggle_checklist', {
                student_id: studentId, class_id: classId, date, field, value: newValue,
            });
            el.classList.remove('loading');
            if (result.success) {
                const chk = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#4CAF50" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>';
                const x = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#BDBDBD" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
                el.innerHTML = newValue ? chk : x;
                el.className = 'check-toggle' + (newValue ? ' checked' : '');
                el.setAttribute('onclick', `SystemApp.toggleCheck(${studentId},${classId},'${date}','${field}',${newValue ? 0 : 1},this)`);
            }
        }

        async function adjustNum(studentId, classId, date, field, delta, el) {
            const stepper = el.closest('.num-stepper');
            const valEl = stepper.querySelector('.stepper-val');
            const current = parseInt(valEl.textContent) || 0;
            const newVal = Math.max(0, current + delta);
            if (newVal === current && delta < 0) return;

            valEl.textContent = newVal;
            valEl.className = 'stepper-val' + (newVal > 0 ? ' has-val' : '');
            stepper.classList.add('loading');

            const result = await App.post('/api/system.php?action=toggle_checklist', {
                student_id: studentId, class_id: classId, date, field, value: newVal,
            });

            stepper.classList.remove('loading');
            if (!result.success) {
                valEl.textContent = current;
                valEl.className = 'stepper-val' + (current > 0 ? ' has-val' : '');
            }
        }

        // ============================================
        // 초대 링크 생성
        // ============================================
        async function createInvite() {
            App.confirm('시스템관리자 초대 링크를 생성하시겠습니까?\n(1시간 유효, 1회만 사용 가능)', async () => {
                App.showLoading();
                const result = await App.post('/api/system.php?action=create_invite');
                App.hideLoading();

                if (result.success) {
                    const url = result.url;
                    const content = `
                        <div style="text-align:center; margin-bottom:16px;">
                            <div style="width:48px; height:48px; background:#E8F5E9; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 8px;">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#2E7D32" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            </div>
                            <div style="font-weight:700; font-size:15px; color:#333;">초대 링크가 생성되었습니다</div>
                            <div style="font-size:12px; color:#757575; margin-top:4px;">
                                만료: ${App.formatDate(result.expires_at, 'YYYY-MM-DD HH:mm')}
                            </div>
                        </div>
                        <div style="background:#F5F5F5; border-radius:12px; padding:12px; margin-bottom:12px;">
                            <input type="text" id="invite-url" class="form-input" value="${url}" readonly style="font-size:12px; background:#fff; text-align:center;">
                        </div>
                        <div style="display:flex; gap:8px;">
                            <button class="btn btn-block" id="btn-copy-invite" style="background:#37474F; color:#fff;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                복사
                            </button>
                            <button class="btn btn-block" id="btn-share-invite" style="background:#4CAF50; color:#fff;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px; margin-right:4px;"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                                공유
                            </button>
                        </div>
                    `;
                    const modal = App.openModal('초대 링크', content);

                    modal.querySelector('#btn-copy-invite').onclick = async () => {
                        try {
                            await navigator.clipboard.writeText(url);
                            Toast.success('링크가 복사되었습니다');
                        } catch {
                            modal.querySelector('#invite-url').select();
                            document.execCommand('copy');
                            Toast.success('링크가 복사되었습니다');
                        }
                    };

                    modal.querySelector('#btn-share-invite').onclick = async () => {
                        if (navigator.share) {
                            try {
                                await navigator.share({
                                    title: '소리튠 주니어 - 시스템관리자 초대',
                                    text: '시스템관리자 계정을 생성해 주세요 (1시간 유효)',
                                    url: url,
                                });
                            } catch {}
                        } else {
                            try {
                                await navigator.clipboard.writeText(url);
                                Toast.success('링크가 복사되었습니다 (공유 미지원 브라우저)');
                            } catch {
                                modal.querySelector('#invite-url').select();
                                document.execCommand('copy');
                                Toast.success('링크가 복사되었습니다');
                            }
                        }
                    };
                }
            });
        }

        // ============================================
        // 과제 현황
        // ============================================
        async function loadAssignments() {
            // URL hash에서 상태 복원 (첫 로드 시)
            if (assignStateFromHash) {
                if (assignStateFromHash.date) {
                    document.getElementById('assign-date').value = assignStateFromHash.date;
                }
                if (assignStateFromHash.view) {
                    assignView = assignStateFromHash.view;
                    syncViewButtons();
                }
                if (assignStateFromHash.sortcol) {
                    assignSort = {
                        col: assignStateFromHash.sortcol,
                        dir: assignStateFromHash.sortdir || 'desc',
                    };
                }
            }

            // 반 데이터 없으면 먼저 로드
            if (allClassesData.length === 0) {
                const classResult = await App.get('/api/system.php?action=classes');
                if (classResult.success) allClassesData = classResult.classes;
            }

            // 반 필터 드롭다운 채우기
            const classFilter = document.getElementById('assign-class-filter');
            if (classFilter.options.length <= 1 && allClassesData.length > 0) {
                classFilter.innerHTML = '<option value="">전체 반</option>' +
                    allClassesData.map(c => `<option value="${c.id}">${c.display_name}</option>`).join('');
            }

            // class_id 복원 (드롭다운 채운 후)
            if (assignStateFromHash && assignStateFromHash.class_id) {
                classFilter.value = assignStateFromHash.class_id;
            }
            assignStateFromHash = null;

            // URL hash 업데이트
            updateAssignHash();

            if (assignView === 'daily') {
                await loadAssignmentDaily();
            } else {
                await loadAssignmentCalendar();
            }
        }

        async function loadAssignmentDaily() {
            const date = document.getElementById('assign-date').value;
            const classId = document.getElementById('assign-class-filter').value;
            const container = document.getElementById('assign-content');

            const params = { date };
            if (classId) params.class_id = classId;

            const result = await App.get('/api/system.php?action=dashboard_assignments', params);
            if (!result.success) return;

            const checkIcon = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#4CAF50" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>';
            const crossIcon = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#BDBDBD" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';

            const dayNames = ['일', '월', '화', '수', '목', '금', '토'];
            const d = new Date(date + 'T00:00:00');
            const dateLabel = `${d.getMonth()+1}/${d.getDate()} (${dayNames[d.getDay()]})`;
            const dateBar = generateDateBar(date);

            const thSort = (col, label) =>
                `<th onclick="SystemApp.toggleSort('${col}')" style="cursor:pointer; text-align:center; font-size:11px; white-space:nowrap; user-select:none;">${label} ${sortIndicator(col)}</th>`;

            // 숫자 스테퍼 셀 (줌 출석, 자세왕)
            const numCell = (s, field) => {
                const val = parseInt(s[field]) || 0;
                return `<td style="text-align:center;">
                    <div class="num-stepper">
                        <button onclick="SystemApp.adjustNum(${s.id},${classId},'${date}','${field}',-1,this)">−</button>
                        <span class="stepper-val${val > 0 ? ' has-val' : ''}">${val}</span>
                        <button onclick="SystemApp.adjustNum(${s.id},${classId},'${date}','${field}',1,this)">+</button>
                    </div>
                </td>`;
            };

            // 체크박스 토글 셀 (소리 과제, 밴드 미션, 리더왕)
            const boolCell = (s, field) => {
                const val = s[field] ? 1 : 0;
                return `<td style="text-align:center;">
                    <span class="check-toggle${val ? ' checked' : ''}" onclick="SystemApp.toggleCheck(${s.id},${classId},'${date}','${field}',${val ? 0 : 1},this)">${val ? checkIcon : crossIcon}</span>
                </td>`;
            };

            if (classId && result.students) {
                // 일일 점수 계산 + 랭킹
                const withScore = result.students.map(s => ({
                    ...s,
                    score: (parseInt(s.zoom_attendance) || 0) + (parseInt(s.posture_king) || 0)
                         + (s.sound_homework ? 1 : 0) + (s.band_mission ? 1 : 0) + (s.leader_king ? 1 : 0)
                }));
                const ranked = [...withScore].filter(s => s.score > 0).sort((a, b) => b.score - a.score);
                const top3 = ranked.slice(0, 3);
                const medals = ['\u{1F947}', '\u{1F948}', '\u{1F949}'];

                // 클라이언트 정렬 (score 컬럼 추가됨)
                const sorted = sortData(withScore, assignSort.col, assignSort.dir);
                const className = allClassesData.find(c => c.id == classId)?.display_name || '';

                // 요약 통계
                const total = sorted.length;
                const zoomSum = sorted.reduce((s, v) => s + (parseInt(v.zoom_attendance) || 0), 0);
                const postureSum = sorted.reduce((s, v) => s + (parseInt(v.posture_king) || 0), 0);
                const hwCnt = sorted.filter(s => s.sound_homework).length;
                const missionCnt = sorted.filter(s => s.band_mission).length;
                const leaderCnt = sorted.filter(s => s.leader_king).length;

                const rankingHtml = top3.length > 0 ? `
                    <div class="assign-ranking">
                        ${top3.map((s, i) => `
                            <div class="rank-card rank-${i+1}">
                                <span class="rank-medal">${medals[i]}</span>
                                <span class="rank-name">${s.name}</span>
                                <span class="rank-score">${s.score}점</span>
                            </div>
                        `).join('')}
                    </div>
                ` : '';

                container.innerHTML = dateBar + `
                    <div style="margin:12px 0 4px; font-weight:700; font-size:15px;">${className} - ${dateLabel}</div>
                    ${rankingHtml}
                    <div class="admin-stats" style="margin-bottom:12px;">
                        <div class="stat-card" style="padding:10px;">
                            <div class="stat-value" style="font-size:16px; color:#F44336;">${zoomSum}<span style="font-size:11px; color:#999;">회</span></div>
                            <div class="stat-label" style="font-size:10px;">줌 출석</div>
                        </div>
                        <div class="stat-card" style="padding:10px;">
                            <div class="stat-value" style="font-size:16px; color:#9C27B0;">${postureSum}<span style="font-size:11px; color:#999;">회</span></div>
                            <div class="stat-label" style="font-size:10px;">자세왕</div>
                        </div>
                        <div class="stat-card" style="padding:10px;">
                            <div class="stat-value" style="font-size:16px; color:#4CAF50;">${hwCnt}<span style="font-size:11px; color:#999;">/${total}</span></div>
                            <div class="stat-label" style="font-size:10px;">소리 과제</div>
                        </div>
                        <div class="stat-card" style="padding:10px;">
                            <div class="stat-value" style="font-size:16px; color:#FF9800;">${missionCnt}<span style="font-size:11px; color:#999;">/${total}</span></div>
                            <div class="stat-label" style="font-size:10px;">밴드 미션</div>
                        </div>
                        <div class="stat-card" style="padding:10px;">
                            <div class="stat-value" style="font-size:16px; color:#2196F3;">${leaderCnt}<span style="font-size:11px; color:#999;">/${total}</span></div>
                            <div class="stat-label" style="font-size:10px;">리더왕</div>
                        </div>
                    </div>
                    <div style="overflow-x:auto;">
                    <table class="data-table">
                        <thead><tr>
                            <th onclick="SystemApp.toggleSort('name')" style="cursor:pointer; user-select:none;">학생 ${sortIndicator('name')}</th>
                            ${thSort('zoom_attendance', '줌 출석')}
                            ${thSort('posture_king', '자세왕')}
                            ${thSort('sound_homework', '소리 과제')}
                            ${thSort('band_mission', '밴드 미션')}
                            ${thSort('leader_king', '리더왕')}
                            ${thSort('score', '합계')}
                        </tr></thead>
                        <tbody>
                            ${sorted.map(s => `
                                <tr>
                                    <td style="font-weight:600; font-size:13px;">${s.name}</td>
                                    ${numCell(s, 'zoom_attendance')}
                                    ${numCell(s, 'posture_king')}
                                    ${boolCell(s, 'sound_homework')}
                                    ${boolCell(s, 'band_mission')}
                                    ${boolCell(s, 'leader_king')}
                                    <td style="text-align:center; font-weight:700; font-size:14px; color:${s.score > 0 ? '#FF7E17' : '#CCC'};">${s.score}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                    </div>
                    <div class="list-summary">
                        <span>총 <strong>${sorted.length}</strong>명</span>
                        <span style="color:#999; font-size:11px;">숫자: +/- 클릭 | 과제: 체크 클릭 | 실시간 저장</span>
                    </div>
                `;
            } else if (result.classes) {
                // 클라이언트 정렬
                const sorted = sortData(result.classes, assignSort.col, assignSort.dir);
                const overall = result.overall || {};
                container.innerHTML = dateBar + `
                    <div style="margin:12px 0 8px; font-weight:700; font-size:15px;">${dateLabel} 과제 현황</div>
                    <div class="admin-stats" style="margin-bottom:12px;">
                        <div class="stat-card" style="padding:12px;">
                            <div class="stat-value" style="font-size:20px; color:#FF7E17;">${overall.total_checked || 0}<span style="font-size:12px; color:#999;">/${result.total_students}</span></div>
                            <div class="stat-label" style="font-size:11px;">과제 체크 학생</div>
                        </div>
                        <div class="stat-card" style="padding:12px;">
                            <div class="stat-value" style="font-size:20px; color:#F44336;">${overall.total_zoom || 0}</div>
                            <div class="stat-label" style="font-size:11px;">줌 출석</div>
                        </div>
                        <div class="stat-card" style="padding:12px;">
                            <div class="stat-value" style="font-size:20px; color:#4CAF50;">${overall.total_homework || 0}</div>
                            <div class="stat-label" style="font-size:11px;">소리 과제</div>
                        </div>
                        <div class="stat-card" style="padding:12px;">
                            <div class="stat-value" style="font-size:20px; color:#FF9800;">${overall.total_mission || 0}</div>
                            <div class="stat-label" style="font-size:11px;">밴드 미션</div>
                        </div>
                    </div>
                    <div style="overflow-x:auto;">
                    <table class="data-table">
                        <thead><tr>
                            <th onclick="SystemApp.toggleSort('class_name')" style="cursor:pointer; user-select:none;">반 ${sortIndicator('class_name')}</th>
                            ${thSort('checked_count', '체크')}
                            ${thSort('zoom_done', '줌 출석')}
                            ${thSort('posture_done', '자세왕')}
                            ${thSort('homework_done', '소리 과제')}
                            ${thSort('mission_done', '밴드 미션')}
                            ${thSort('leader_done', '리더왕')}
                        </tr></thead>
                        <tbody>
                            ${sorted.map(c => {
                                const pct = c.total_students > 0 ? Math.round((c.checked_count / c.total_students) * 100) : 0;
                                return `
                                    <tr style="cursor:pointer;" onclick="SystemApp.drillClass(${c.class_id})">
                                        <td>
                                            <div style="font-weight:600; font-size:13px;">${c.class_name}</div>
                                            <div style="font-size:11px; color:#999;">${c.coach_name || ''}</div>
                                        </td>
                                        <td style="text-align:center;">
                                            <div style="font-weight:700; color:${pct >= 80 ? '#4CAF50' : pct >= 50 ? '#FF9800' : '#F44336'};">${c.checked_count}/${c.total_students}</div>
                                            <div style="font-size:10px; color:#999;">${pct}%</div>
                                        </td>
                                        <td style="text-align:center; font-size:13px;">${c.zoom_done || 0}</td>
                                        <td style="text-align:center; font-size:13px;">${c.posture_done || 0}</td>
                                        <td style="text-align:center; font-size:13px;">${c.homework_done || 0}</td>
                                        <td style="text-align:center; font-size:13px;">${c.mission_done || 0}</td>
                                        <td style="text-align:center; font-size:13px;">${c.leader_done || 0}</td>
                                    </tr>
                                `;
                            }).join('')}
                        </tbody>
                    </table>
                    </div>
                    <div class="list-summary">
                        <span>반을 클릭하면 학생별 상세를 볼 수 있습니다</span>
                    </div>
                `;
            }

            // 날짜 바 자동 스크롤
            requestAnimationFrame(() => {
                const ac = document.querySelector('.date-chip.active');
                if (ac) ac.scrollIntoView({ inline: 'center', block: 'nearest' });
            });
        }

        async function loadAssignmentCalendar() {
            const date = document.getElementById('assign-date').value;
            const classId = document.getElementById('assign-class-filter').value;
            const container = document.getElementById('assign-content');
            const month = date.substring(0, 7);

            const params = { month };
            if (classId) params.class_id = classId;

            const result = await App.get('/api/system.php?action=dashboard_calendar', params);
            if (!result.success) return;

            const totalStudents = result.total_students;
            const dailyMap = {};
            (result.daily || []).forEach(d => { dailyMap[d.check_date] = d; });

            // 달력 생성
            const year = parseInt(month.split('-')[0]);
            const mon = parseInt(month.split('-')[1]) - 1;
            const firstDay = new Date(year, mon, 1).getDay();
            const lastDate = new Date(year, mon + 1, 0).getDate();
            const today = new Date().toISOString().split('T')[0];

            let calHtml = `
                <div style="margin:12px 0 8px; font-weight:700; font-size:15px;">${year}년 ${mon+1}월 과제 달력</div>
                <div style="background:#fff; border-radius:16px; padding:12px; box-shadow:0 2px 12px rgba(0,0,0,0.06);">
                    <div style="display:grid; grid-template-columns:repeat(7,1fr); gap:2px; text-align:center;">
                        <div style="font-size:11px; font-weight:700; color:#F44336; padding:4px;">일</div>
                        <div style="font-size:11px; font-weight:700; color:#333; padding:4px;">월</div>
                        <div style="font-size:11px; font-weight:700; color:#333; padding:4px;">화</div>
                        <div style="font-size:11px; font-weight:700; color:#333; padding:4px;">수</div>
                        <div style="font-size:11px; font-weight:700; color:#333; padding:4px;">목</div>
                        <div style="font-size:11px; font-weight:700; color:#333; padding:4px;">금</div>
                        <div style="font-size:11px; font-weight:700; color:#1565C0; padding:4px;">토</div>
            `;

            // 빈 칸
            for (let i = 0; i < firstDay; i++) {
                calHtml += '<div></div>';
            }

            for (let day = 1; day <= lastDate; day++) {
                const dateStr = `${year}-${String(mon+1).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
                const data = dailyMap[dateStr];
                const isToday = dateStr === today;
                const pct = data && totalStudents > 0 ? Math.round((data.checked_count / totalStudents) * 100) : 0;

                let bgColor = '#F5F5F5';
                if (data) {
                    if (pct >= 80) bgColor = '#E8F5E9';
                    else if (pct >= 50) bgColor = '#FFF8E1';
                    else if (pct > 0) bgColor = '#FFEBEE';
                }

                calHtml += `
                    <div onclick="SystemApp.calendarDayClick('${dateStr}')"
                         style="padding:4px 2px; border-radius:8px; cursor:pointer; background:${bgColor}; ${isToday ? 'border:2px solid #37474F;' : ''} min-height:48px;">
                        <div style="font-size:12px; font-weight:${isToday ? '800' : '500'}; color:${isToday ? '#37474F' : '#333'};">${day}</div>
                        ${data ? `
                            <div style="font-size:10px; font-weight:700; color:${pct >= 80 ? '#4CAF50' : pct >= 50 ? '#FF9800' : '#F44336'};">${pct}%</div>
                            <div style="font-size:9px; color:#999;">${data.checked_count}명</div>
                        ` : ''}
                    </div>
                `;
            }

            calHtml += '</div></div>';

            // 범례
            calHtml += `
                <div style="display:flex; gap:12px; justify-content:center; margin-top:8px; font-size:11px; color:#757575;">
                    <span><span style="display:inline-block; width:12px; height:12px; border-radius:3px; background:#E8F5E9; vertical-align:-1px;"></span> 80%+</span>
                    <span><span style="display:inline-block; width:12px; height:12px; border-radius:3px; background:#FFF8E1; vertical-align:-1px;"></span> 50-79%</span>
                    <span><span style="display:inline-block; width:12px; height:12px; border-radius:3px; background:#FFEBEE; vertical-align:-1px;"></span> ~49%</span>
                    <span><span style="display:inline-block; width:12px; height:12px; border-radius:3px; background:#F5F5F5; vertical-align:-1px;"></span> 없음</span>
                </div>
            `;

            container.innerHTML = calHtml;
        }

        // ============================================
        // 학생 프로필 (카드 현황)
        // ============================================
        async function showStudentProfile(studentId) {
            App.showLoading();
            const result = await App.get('/api/system.php?action=student_detail', { student_id: studentId });
            App.hideLoading();
            if (!result.success) return;

            const s = result.student;
            const rewards = result.rewards || [];
            const weekly = result.weekly_summary || [];

            const cardHtml = rewards.map(r => `
                <div style="display:flex; align-items:center; gap:12px; padding:10px; background:#FAFAFA; border-radius:12px; border:1.5px solid ${r.color}20;">
                    <div style="width:40px; height:40px; border-radius:10px; background:${r.color}15; display:flex; align-items:center; justify-content:center;">
                        <span style="font-size:18px; font-weight:800; color:${r.color};">${r.quantity}</span>
                    </div>
                    <div style="flex:1;">
                        <div style="font-weight:700; font-size:13px; color:#333;">${r.name_ko}</div>
                        <div style="font-size:11px; color:#999;">${r.coin_value}코인 x ${r.quantity}장 = ${r.coin_value * r.quantity}코인</div>
                    </div>
                </div>
            `).join('');

            const weeklyHtml = weekly.length > 0 ? `
                <div style="margin-top:12px;">
                    <div style="font-weight:700; font-size:13px; margin-bottom:6px;">최근 주간 요약</div>
                    <table class="data-table" style="font-size:12px;">
                        <thead><tr>
                            <th>주간</th><th>필요</th><th>완료</th><th>꾸준왕</th>
                        </tr></thead>
                        <tbody>${weekly.map(w => `
                            <tr>
                                <td style="font-size:11px;">${w.week_start.substring(5)} ~ ${w.week_end.substring(5)}</td>
                                <td style="text-align:center;">${w.required_days}</td>
                                <td style="text-align:center;">${w.completed_days}</td>
                                <td style="text-align:center;">${w.is_steady_king == 1 ? '<span style="color:#4CAF50; font-weight:700;">O</span>' : '<span style="color:#ccc;">-</span>'}</td>
                            </tr>
                        `).join('')}</tbody>
                    </table>
                </div>
            ` : '';

            const content = `
                <div style="text-align:center; margin-bottom:12px;">
                    <div style="width:48px; height:48px; background:#FF7E17; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 8px; color:#fff; font-size:20px; font-weight:700;">${s.name.charAt(0)}</div>
                    <div style="font-size:16px; font-weight:700;">${s.name}</div>
                    <div style="font-size:12px; color:#999;">${s.class_name || '미배정'} ${s.grade ? '| ' + s.grade + '세' : ''}</div>
                </div>
                <div style="background:linear-gradient(135deg,#FFF8E1,#FFE082); border-radius:12px; padding:12px; text-align:center; margin-bottom:12px;">
                    <div style="font-size:11px; color:#F57F17;">총 코인</div>
                    <div style="font-size:28px; font-weight:800; color:#F5A623;">${result.total_coins}</div>
                </div>
                <div style="display:flex; flex-direction:column; gap:6px;">
                    ${cardHtml}
                </div>
                ${weeklyHtml}
                <button class="btn btn-block" style="background:#FF7E17; color:#fff; margin-top:16px;" id="btn-imp-student">학생 화면으로 접속</button>
            `;
            const modal = App.openModal(s.name + ' 카드 현황', content);
            modal.querySelector('#btn-imp-student').onclick = () => {
                App.closeModal(modal);
                impersonateStudent(studentId);
            };
        }

        // ============================================
        // 반 상세 보기
        // ============================================
        async function showClassDetail(classId) {
            App.showLoading();
            const result = await App.get('/api/system.php?action=class_detail', { class_id: classId });
            App.hideLoading();
            if (!result.success) return;

            const c = result.class;
            const students = result.students || [];
            const admins = result.admins || [];
            const roleLabels = { coach: '코치쌤', admin_teacher: '관리쌤', parent: '부모' };

            const adminHtml = admins.length > 0 ? admins.map(a => `
                <div style="display:flex; align-items:center; gap:8px; padding:8px; background:#F5F5F5; border-radius:10px;">
                    <div style="width:32px; height:32px; background:${a.role === 'coach' ? '#E3F2FD' : '#F3E5F5'}; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:13px; color:${a.role === 'coach' ? '#1565C0' : '#7B1FA2'};">${a.name.charAt(0)}</div>
                    <div style="flex:1;">
                        <div style="font-weight:600; font-size:13px;">${a.name}</div>
                        <div style="font-size:11px; color:#999;">${roleLabels[a.role] || a.role}${a.phone ? ' | ' + a.phone : ''}</div>
                    </div>
                </div>
            `).join('') : '<div style="color:#999; font-size:13px; padding:8px;">연결된 관리자가 없습니다</div>';

            const studentHtml = students.map(s => `
                <tr onclick="SystemApp.showStudentProfile(${s.id})" style="cursor:pointer;">
                    <td>
                        <div style="font-weight:600; font-size:13px;">${s.name}</div>
                        ${s.phone_last4 ? '<div style="font-size:11px; color:#999;">***-' + s.phone_last4 + '</div>' : ''}
                    </td>
                    <td style="text-align:center; font-size:12px;">${s.grade || '-'}</td>
                    <td style="text-align:center;">
                        <span style="font-weight:700; color:#F5A623;">${s.total_coins}</span>
                    </td>
                    <td style="text-align:center; font-size:11px;">${s.is_primary == 1 ? '<span style="color:#4CAF50;">본반</span>' : '<span style="color:#FF9800;">타반</span>'}</td>
                </tr>
            `).join('');

            const content = `
                <div style="text-align:center; margin-bottom:12px;">
                    <img src="/images/${c.logo_image}" style="width:64px; height:64px; border-radius:16px; object-fit:cover;">
                    <div style="font-size:16px; font-weight:700; margin-top:6px;">${c.display_name} 반</div>
                    <div style="font-size:12px; color:#999;">${c.coach_name || '코치 미배정'} | 학생 ${students.length}명</div>
                </div>
                <div style="margin-bottom:12px;">
                    <div style="font-weight:700; font-size:13px; margin-bottom:6px;">연결된 관리자 (${admins.length}명)</div>
                    <div style="display:flex; flex-direction:column; gap:4px;">
                        ${adminHtml}
                    </div>
                </div>
                <div>
                    <div style="font-weight:700; font-size:13px; margin-bottom:6px;">학생 목록 (${students.length}명)</div>
                    <div style="overflow-x:auto;">
                    <table class="data-table" style="font-size:12px;">
                        <thead><tr><th>학생</th><th>나이</th><th>코인</th><th>구분</th></tr></thead>
                        <tbody>${studentHtml}</tbody>
                    </table>
                    </div>
                </div>
            `;
            App.openModal(c.display_name + ' 반 상세', content);
        }

        // ============================================
        // 대행 로그인 (접속)
        // ============================================
        async function impersonateAdmin(adminId, role) {
            if (role === 'coach') {
                App.showLoading();
                const result = await App.post('/api/system.php?action=impersonate_coach', { coach_id: adminId });
                App.hideLoading();
                if (result.success) {
                    Toast.success(result.message);
                    setTimeout(() => { window.location.href = '/coach/'; }, 500);
                }
            } else {
                // admin_teacher, parent
                App.showLoading();
                const result = await App.post('/api/system.php?action=impersonate_admin', { admin_id: adminId });
                App.hideLoading();
                if (result.success) {
                    Toast.success(result.message);
                    setTimeout(() => { window.location.href = '/admin/'; }, 500);
                }
            }
        }

        async function impersonateStudent(studentId) {
            App.showLoading();
            const result = await App.post('/api/system.php?action=impersonate_student', { student_id: studentId });
            App.hideLoading();
            if (result.success) {
                Toast.success(result.message);
                setTimeout(() => { window.location.href = '/'; }, 500);
            }
        }

        function drillClass(classId) {
            document.getElementById('assign-class-filter').value = classId;
            assignSort = { col: '', dir: 'desc' }; // 반 변경시 정렬 초기화
            loadAssignments();
        }

        function calendarDayClick(dateStr) {
            document.getElementById('assign-date').value = dateStr;
            assignView = 'daily';
            syncViewButtons();
            loadAssignments();
        }

        async function generateSetupLink() {
            try {
                const r = await App.post('/api/system.php?action=generate_setup_link');
                if (!r.success) { Toast.error(r.error || '링크 생성 실패'); return; }
                const el = document.getElementById('setup-link-result');
                el.style.display = 'block';
                el.innerHTML = `
                    <div style="padding:12px;background:#ECFDF5;border:1.5px solid #A7F3D0;border-radius:10px;">
                        <div style="font-size:12px;color:#22C55E;font-weight:600;margin-bottom:6px;">1시간 유효 링크 생성됨</div>
                        <input type="text" value="${r.link}" readonly style="width:100%;padding:8px 10px;border:1px solid #E5E7EB;border-radius:6px;font-size:12px;background:#fff;margin-bottom:8px;" onclick="this.select()">
                        <div style="display:flex;gap:8px;">
                            <button class="btn btn-sm btn-primary" onclick="navigator.clipboard.writeText('${r.link}');Toast.success('클립보드에 복사됨');">링크 복사</button>
                            <span style="font-size:11px;color:#9CA3AF;align-self:center;">만료: ${r.expires_at}</span>
                        </div>
                    </div>
                `;
            } catch (e) {
                Toast.error('링크 생성 실패: ' + e.message);
            }
        }

        document.addEventListener('DOMContentLoaded', init);

        return { editStudent, editAdmin, editClass, goPage, createInvite, drillClass, calendarDayClick, toggleSort, quickDate, toggleCheck, adjustNum, showStudentProfile, showClassDetail, impersonateAdmin, impersonateStudent, dashDateMove, dashDateChange, dashDateToday, generateSetupLink };
    })();
    </script>
    <script src="/js/admin-dock.js?v=20260215g" data-adock-active="system"></script>
</body>
</html>
