/**
 * 소리튠 주니어 영어학교 - 관리쌤 대시보드 로직
 * coach.js 기반, QR 제거, 전화번호 로그인
 */
const TeacherApp = (() => {
    let currentClassId = null;
    let classes = [];
    let checklistDate = new Date();
    let fingerprint = null;

    // ============================================
    // 초기화
    // ============================================
    async function init() {
        bindEvents();

        // 핑거프린트 생성
        if (typeof DeviceFingerprint !== 'undefined') {
            try {
                fingerprint = await DeviceFingerprint.generate();
            } catch (e) {
                console.warn('Fingerprint generation failed:', e);
            }
        }

        // 1차: 세션 확인
        const result = await App.get('/api/coach.php?action=check_session');
        if (result.logged_in && result.admin.admin_role === 'admin_teacher') {
            classes = result.classes || [];
            showDashboard();
            return;
        }

        // 2차: 핑거프린트 자동 로그인
        if (fingerprint) {
            const autoResult = await App.post('/api/coach.php?action=auto_login', {
                fingerprint: fingerprint
            });
            if (autoResult.logged_in && autoResult.admin.role === 'admin_teacher') {
                classes = autoResult.classes || [];
                showDashboard();
                return;
            }
        }

        showLogin();
    }

    function bindEvents() {
        // 로그인
        document.getElementById('btn-login').addEventListener('click', doLogin);
        document.getElementById('login-phone').addEventListener('keyup', e => { if (e.key === 'Enter') doLogin(); });

        // 로그아웃
        document.getElementById('btn-teacher-logout').addEventListener('click', () => {
            App.confirm('로그아웃 하시겠습니까?', async () => {
                await App.post('/api/coach.php?action=logout', {
                    fingerprint: fingerprint || '',
                });
                window.location.href = '/';
            }, { formal: true });
        });

        // 탭
        App.initTabs(document.getElementById('app'));
        document.getElementById('app').addEventListener('tabChange', (e) => {
            const tab = e.detail.tab;
            if (tab === 'overview') loadOverview();
            else if (tab === 'checklist') loadChecklist();
            else if (tab === 'profile') loadProfileSelector();
        });

        // 반 선택
        document.getElementById('class-select').addEventListener('change', (e) => {
            currentClassId = parseInt(e.target.value);
            loadOverview();
        });

        // 체크리스트 날짜
        document.getElementById('date-prev').addEventListener('click', () => {
            checklistDate.setDate(checklistDate.getDate() - 1);
            loadChecklist();
        });
        document.getElementById('date-next').addEventListener('click', () => {
            checklistDate.setDate(checklistDate.getDate() + 1);
            loadChecklist();
        });

        // 체크리스트 저장
        document.getElementById('btn-save-checklist').addEventListener('click', saveChecklist);

        // 학생 프로필 선택
        document.getElementById('profile-student-select').addEventListener('change', (e) => {
            if (e.target.value) loadStudentProfile(parseInt(e.target.value));
        });
    }

    // ============================================
    // 로그인/로그아웃
    // ============================================
    async function doLogin() {
        const phoneLast4 = document.getElementById('login-phone').value.trim();

        if (!phoneLast4 || phoneLast4.length !== 4) {
            Toast.warning('전화번호 뒷 4자리를 입력해 주세요');
            return;
        }

        App.showLoading();
        const deviceInfo = typeof DeviceFingerprint !== 'undefined' ? DeviceFingerprint.getDeviceInfo() : null;
        const result = await App.post('/api/coach.php?action=teacher_login', {
            phone_last4: phoneLast4,
            fingerprint: fingerprint || '',
            device_info: deviceInfo,
        });
        App.hideLoading();

        if (result.success) {
            classes = result.classes || [];
            Toast.success(result.message);
            showDashboard();
        }
    }

    function showLogin() {
        document.getElementById('view-login').classList.remove('hidden');
        document.getElementById('view-dashboard').classList.add('hidden');
        document.getElementById('login-phone').value = '';
    }

    function showDashboard() {
        document.getElementById('view-login').classList.add('hidden');
        document.getElementById('view-dashboard').classList.remove('hidden');

        // 반 선택 설정
        const selector = document.getElementById('class-selector');
        const select = document.getElementById('class-select');

        if (classes.length > 1) {
            selector.classList.remove('hidden');
            select.innerHTML = classes.map(c =>
                `<option value="${c.id}">${c.display_name}</option>`
            ).join('');
        } else {
            selector.classList.add('hidden');
        }

        currentClassId = classes.length > 0 ? classes[0].id : null;
        if (currentClassId) loadOverview();
    }

    // ============================================
    // 탭1: 반 현황
    // ============================================
    let overviewDate = null;

    async function loadOverview(date) {
        if (!currentClassId) return;

        overviewDate = date || new Date().toISOString().split('T')[0];
        const container = document.getElementById('overview-list');
        container.innerHTML = '<div style="text-align:center; padding:40px;"><div class="loading-spinner" style="display:inline-block;"></div></div>';

        const result = await App.get('/api/coach.php?action=dashboard&class_id=' + currentClassId, { date: overviewDate });
        if (!result.success) {
            container.innerHTML = '<div style="text-align:center; padding:40px; color:#F44336;">데이터를 불러올 수 없습니다</div>';
            return;
        }

        if (result.students.length === 0) {
            container.innerHTML = '<div class="empty-state"><div class="empty-state-text">등록된 학생이 없습니다</div></div>';
            return;
        }

        const completionRate = result.completion_rate || 0;
        const completedCount = result.completed_count || 0;
        const totalCount = result.total_count || 0;
        const items = result.items || { zoom: 0, posture: 0, homework: 0, mission: 0, leader: 0 };
        const trend = result.trend || [];
        const rateColor = completionRate >= 80 ? '#4CAF50' : completionRate >= 50 ? '#FF9800' : completionRate > 0 ? '#F44336' : '#BDBDBD';

        // 날짜 표시
        const dayNames = ['일','월','화','수','목','금','토'];
        const dd = new Date(overviewDate + 'T00:00:00');
        const dateLabel = `${dd.getFullYear()}.${dd.getMonth()+1}.${dd.getDate()} (${dayNames[dd.getDay()]})`;
        const isToday = overviewDate === new Date().toISOString().split('T')[0];

        // Dense ranking
        let rank = 0, prevCoins = -1;
        const ranked = result.students.map(s => {
            if (s.total_coins != prevCoins) { rank++; prevCoins = s.total_coins; }
            return { ...s, rank };
        });

        // 미니 추세 SVG (최근 7일)
        let trendSvg = '';
        if (trend.length > 1) {
            const w = 140, h = 40, pad = 4;
            const pts = trend.map((t, i) => {
                const x = pad + (i / (trend.length - 1)) * (w - pad * 2);
                const y = h - pad - (t.rate / 100) * (h - pad * 2);
                return `${x},${y}`;
            });
            trendSvg = `<svg width="${w}" height="${h}" style="display:block;">
                <polyline points="${pts.join(' ')}" fill="none" stroke="${rateColor}" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                ${trend.map((t, i) => {
                    const x = pad + (i / (trend.length - 1)) * (w - pad * 2);
                    const y = h - pad - (t.rate / 100) * (h - pad * 2);
                    return `<circle cx="${x}" cy="${y}" r="3" fill="${i === trend.length - 1 ? rateColor : '#fff'}" stroke="${rateColor}" stroke-width="1.5"/>`;
                }).join('')}
            </svg>`;
        }

        // 항목별 아이콘과 색상
        const itemData = [
            { key: 'zoom', label: '줌출석', val: items.zoom, color: '#F44336', icon: '\uD83D\uDCF9' },
            { key: 'posture', label: '자세왕', val: items.posture, color: '#9C27B0', icon: '\uD83E\uDDD8' },
            { key: 'homework', label: '소리과제', val: items.homework, color: '#FF7E17', icon: '\uD83C\uDFA7' },
            { key: 'mission', label: '밴드미션', val: items.mission, color: '#2196F3', icon: '\uD83C\uDFAF' },
            { key: 'leader', label: '리더왕', val: items.leader, color: '#4CAF50', icon: '\uD83D\uDC51' },
        ];

        container.innerHTML = `
            <!-- 날짜 네비게이션 -->
            <div style="display:flex; align-items:center; justify-content:center; gap:10px; margin-bottom:16px;">
                <button class="checklist-date-btn" id="ov-date-prev">\u25C0</button>
                <div style="text-align:center; min-width:160px;">
                    <div style="font-weight:800; font-size:16px; color:#333;">${dateLabel}</div>
                    ${isToday ? '<div style="font-size:10px; color:#FF9800; font-weight:600;">TODAY</div>' : ''}
                </div>
                <button class="checklist-date-btn" id="ov-date-next">\u25B6</button>
                ${!isToday ? '<button class="btn btn-sm" id="ov-date-today" style="background:#FF9800; color:#fff; padding:4px 10px; font-size:11px; border-radius:20px;">오늘</button>' : ''}
            </div>

            <!-- 메인 과제율 카드 -->
            <div class="card" style="padding:0; overflow:hidden; margin-bottom:16px; border-radius:20px; box-shadow:0 4px 20px rgba(0,0,0,.1);">
                <div style="padding:20px; background:linear-gradient(135deg, #FFF3E0, #FFE0B2);">
                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px;">
                        <div>
                            <div style="font-weight:800; font-size:20px; color:#E65100;">${result.class.display_name}</div>
                            <div style="font-size:12px; color:#90A4AE; margin-top:4px;">학생 ${result.count}명</div>
                        </div>
                        <div style="text-align:center; position:relative;">
                            <div style="width:80px; height:80px; border-radius:50%; background:conic-gradient(${rateColor} ${completionRate * 3.6}deg, rgba(255,255,255,0.3) 0deg); display:flex; align-items:center; justify-content:center;">
                                <div style="width:64px; height:64px; border-radius:50%; background:#fff; display:flex; flex-direction:column; align-items:center; justify-content:center;">
                                    <div style="font-size:22px; font-weight:800; color:${rateColor}; line-height:1;">${completionRate}%</div>
                                    <div style="font-size:9px; color:#999;">과제율</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div style="display:flex; align-items:center; justify-content:space-between;">
                        <div style="font-size:13px; color:#546E7A;">
                            <span style="font-weight:700; color:${rateColor};">${completedCount}</span> / ${totalCount}명 참여
                        </div>
                        <div style="text-align:right;">
                            ${trend.length > 1 ? '<div style="font-size:9px; color:#90A4AE; margin-bottom:2px;">7일 추세</div>' + trendSvg : ''}
                        </div>
                    </div>
                </div>

                <!-- 항목별 현황 -->
                <div style="display:grid; grid-template-columns:repeat(5,1fr); border-top:1px solid #FFE0B2;">
                    ${itemData.map(it => `
                        <div style="text-align:center; padding:12px 4px; border-right:1px solid #F5F5F5;">
                            <div style="font-size:18px; margin-bottom:4px;">${it.icon}</div>
                            <div style="font-size:18px; font-weight:800; color:${it.val > 0 ? it.color : '#E0E0E0'};">${it.val}</div>
                            <div style="font-size:9px; color:#999; margin-top:2px;">${it.label}</div>
                        </div>
                    `).join('')}
                </div>
            </div>

            <!-- 전체 반 랭킹 버튼 -->
            <button class="btn btn-block" id="btn-all-class-ranking" style="background:linear-gradient(135deg,#FF7E17,#FF9800); color:#fff; font-weight:700; margin-bottom:16px; padding:14px; border-radius:14px; font-size:14px; box-shadow:0 4px 12px rgba(255,126,23,.3);">
                \uD83C\uDFC6 전체 반 과제율 랭킹 보러가기
            </button>
            <div id="all-class-ranking-container" style="display:none; margin-bottom:16px;"></div>

            <!-- 학생 순위 -->
            <div class="card" style="padding:0; overflow:hidden; border-radius:16px;">
                <div style="padding:12px 16px; background:#F5F5F5; font-weight:700; color:#333; display:flex; align-items:center; gap:6px;">
                    \uD83C\uDFC5 학생 순위 (코인순)
                </div>
                ${ranked.map(s => `
                    <div class="list-item" style="cursor:pointer;" onclick="TeacherApp.selectProfileStudent(${s.id})">
                        <div style="width:28px; text-align:center; font-weight:700; color:${s.rank<=3 ? ['','#FFD700','#C0C0C0','#CD7F32'][s.rank] : '#9E9E9E'};">
                            ${s.rank <= 3 ? App.getRankTrophy(s.rank) : s.rank}
                        </div>
                        <div class="list-item-content">
                            <div class="list-item-title">${s.name}</div>
                            ${s.grade ? `<div class="list-item-subtitle">${s.grade}</div>` : ''}
                        </div>
                        ${App.coinBadge(s.total_coins)}
                    </div>
                `).join('')}
            </div>
        `;

        // 날짜 네비게이션 이벤트
        document.getElementById('ov-date-prev').addEventListener('click', () => {
            const d = new Date(overviewDate);
            d.setDate(d.getDate() - 1);
            loadOverview(d.toISOString().split('T')[0]);
        });
        document.getElementById('ov-date-next').addEventListener('click', () => {
            const d = new Date(overviewDate);
            d.setDate(d.getDate() + 1);
            loadOverview(d.toISOString().split('T')[0]);
        });
        const todayBtn = document.getElementById('ov-date-today');
        if (todayBtn) todayBtn.addEventListener('click', () => loadOverview());

        // 전체 반 과제율 랭킹 버튼
        document.getElementById('btn-all-class-ranking').addEventListener('click', () => {
            const rc = document.getElementById('all-class-ranking-container');
            if (rc.style.display === 'none') {
                rc.style.display = 'block';
                document.getElementById('btn-all-class-ranking').innerHTML = '\uD83C\uDFC6 전체 반 과제율 랭킹 접기';
                loadAllClassRanking(overviewDate);
            } else {
                rc.style.display = 'none';
                document.getElementById('btn-all-class-ranking').innerHTML = '\uD83C\uDFC6 전체 반 과제율 랭킹 보러가기';
            }
        });
    }

    // ============================================
    // 전체 반 과제율 랭킹
    // ============================================
    let rankingDate = null;

    async function loadAllClassRanking(date) {
        rankingDate = date || new Date().toISOString().split('T')[0];
        const container = document.getElementById('all-class-ranking-container');
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
            <div class="card" style="padding:0; overflow:hidden;">
                <div style="padding:14px 16px; background:linear-gradient(135deg,#FFF3E0,#FFE0B2);">
                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;">
                        <div style="font-weight:800; font-size:16px; color:#E65100;">전체 반 과제율 랭킹</div>
                    </div>
                    <div style="display:flex; align-items:center; gap:6px;">
                        <button class="btn btn-sm" id="ranking-prev" style="background:#fff; color:#333; border:1px solid #ddd; padding:4px 8px;">◀</button>
                        <span style="font-weight:700; font-size:14px; color:#333; min-width:100px; text-align:center;" id="ranking-date-label">${dateLabel}</span>
                        <button class="btn btn-sm" id="ranking-next" style="background:#fff; color:#333; border:1px solid #ddd; padding:4px 8px;">▶</button>
                        <button class="btn btn-sm" id="ranking-today" style="background:#FF9800; color:#fff; padding:4px 10px; font-size:11px;">오늘</button>
                    </div>
                </div>
                <div id="ranking-list">
                    ${renderRankingList(result.classes)}
                </div>
            </div>
        `;

        // 날짜 네비게이션
        document.getElementById('ranking-prev').addEventListener('click', () => {
            const dd = new Date(rankingDate);
            dd.setDate(dd.getDate() - 1);
            loadAllClassRanking(dd.toISOString().split('T')[0]);
        });
        document.getElementById('ranking-next').addEventListener('click', () => {
            const dd = new Date(rankingDate);
            dd.setDate(dd.getDate() + 1);
            loadAllClassRanking(dd.toISOString().split('T')[0]);
        });
        document.getElementById('ranking-today').addEventListener('click', () => {
            loadAllClassRanking(new Date().toISOString().split('T')[0]);
        });
    }

    function renderRankingList(classes) {
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
    // 탭2: 체크리스트
    // ============================================
    async function loadChecklist() {
        if (!currentClassId) return;

        const dateStr = App.formatDate(checklistDate);
        document.getElementById('checklist-date').textContent = App.formatDateKo(checklistDate);

        // 체크리스트 데이터 + 주간 카드 한도를 병렬 로드
        const [result, limitResult] = await Promise.all([
            App.get(`/api/coach.php?action=checklist_load&class_id=${currentClassId}&date=${dateStr}`),
            App.get(`/api/coach.php?action=weekly_card_remaining&class_id=${currentClassId}`),
        ]);
        if (!result.success) return;

        // 주간 한도 맵: { studentId: { passion: {used, limit, remaining}, posture: {...} } }
        const limitsMap = {};
        if (limitResult && limitResult.success) {
            (limitResult.students || []).forEach(s => {
                limitsMap[s.student_id] = s.limits;
            });
        }

        // 필드 → 카드코드 매핑
        const fieldToCard = { zoom_attendance: 'passion', posture_king: 'posture' };

        const container = document.getElementById('checklist-content');
        const fields = ['zoom_attendance', 'posture_king', 'sound_homework', 'band_mission', 'leader_king', 'reboot_card'];
        const numFields = ['zoom_attendance', 'posture_king'];
        const labelsFull = ['줌출석', '바른자세', '소리과제', '밴드미션', '밴드리더', '리부트'];
        const labelsShort = ['출석', '자세', '과제', '미션', '리더', '리붓'];
        const isMobile = window.innerWidth < 768;
        const colors = ['cl-zoom', 'cl-posture', 'cl-sound', 'cl-band', 'cl-leader', 'cl-reboot'];

        function renderField(f, value, studentId) {
            if (numFields.includes(f)) {
                const cardCode = fieldToCard[f];
                const limitInfo = limitsMap[studentId] && limitsMap[studentId][cardCode];
                let badge = '';
                if (limitInfo) {
                    const r = limitInfo.remaining;
                    const color = r === 0 ? '#E53935' : r <= 2 ? '#FF9800' : '#4CAF50';
                    badge = `<div style="font-size:10px;color:${color};font-weight:600;margin-top:1px;line-height:1;">${r}/${limitInfo.limit}</div>`;
                }
                return `<div class="checklist-check">
                    <input type="number" class="checklist-num" data-field="${f}" min="0" max="99" value="${parseInt(value) || 0}">
                    ${badge}
                </div>`;
            }
            return `<div class="checklist-check">
                <label class="checkbox-wrapper">
                    <input type="checkbox" data-field="${f}" ${value ? 'checked' : ''}>
                    <span class="checkbox-custom"></span>
                </label>
            </div>`;
        }

        function renderBulk(f) {
            if (numFields.includes(f)) {
                return `<div class="checklist-check">
                    <button class="checklist-bulk-num" data-bulk-num="${f}" title="전체 +1">+1</button>
                </div>`;
            }
            return `<div class="checklist-check">
                <label class="checkbox-wrapper">
                    <input type="checkbox" data-bulk="${f}">
                    <span class="checkbox-custom"></span>
                </label>
            </div>`;
        }

        container.innerHTML = `
            <div class="checklist-table">
                <div class="checklist-header">
                    <div class="col-name">학생</div>
                    ${labelsFull.map((l, i) => `<div class="${colors[i]}" title="${l}">${isMobile ? labelsShort[i] : l}</div>`).join('')}
                </div>
                <div class="checklist-bulk">
                    <div class="checklist-bulk-label">전체</div>
                    ${fields.map(f => renderBulk(f)).join('')}
                </div>
                ${result.students.map(s => `
                    <div class="checklist-row" data-student-id="${s.id}" data-student-name="${s.name}">
                        <div class="checklist-student-name">${s.name}</div>
                        ${fields.map(f => renderField(f, s[f], s.id)).join('')}
                    </div>
                `).join('')}
            </div>
        `;

        // 일괄 체크 이벤트 (checkbox 필드)
        container.querySelectorAll('[data-bulk]').forEach(bulk => {
            bulk.addEventListener('change', () => {
                const field = bulk.dataset.bulk;
                const checked = bulk.checked;
                container.querySelectorAll(`[data-field="${field}"]`).forEach(cb => {
                    cb.checked = checked;
                });
            });
        });

        // 일괄 +1 이벤트 (숫자 필드)
        container.querySelectorAll('[data-bulk-num]').forEach(btn => {
            btn.addEventListener('click', () => {
                const field = btn.dataset.bulkNum;
                container.querySelectorAll(`input[type="number"][data-field="${field}"]`).forEach(input => {
                    input.value = Math.min(99, (parseInt(input.value) || 0) + 1);
                });
            });
        });
    }

    async function saveChecklist() {
        if (!currentClassId) return;

        const rows = document.querySelectorAll('.checklist-row');
        const items = [];

        rows.forEach(row => {
            const studentId = parseInt(row.dataset.studentId);
            const item = { student_id: studentId, name: row.dataset.studentName || '' };
            row.querySelectorAll('[data-field]').forEach(el => {
                if (el.type === 'number') {
                    item[el.dataset.field] = parseInt(el.value) || 0;
                } else {
                    item[el.dataset.field] = el.checked ? 1 : 0;
                }
            });
            items.push(item);
        });

        App.showLoading();
        const result = await App.api('/api/coach.php?action=checklist_save', {
            method: 'POST',
            data: { class_id: currentClassId, date: App.formatDate(checklistDate), items },
            showError: false,
        });
        App.hideLoading();

        if (result.success) {
            Toast.success('체크리스트가 저장되었습니다');
            loadChecklist(); // 주간 한도 배지 갱신
        } else if (result.limit_errors) {
            const msgs = result.limit_errors.map(e => {
                const name = e.name || `학생#${e.student_id}`;
                return `${name}: ${e.card} 요청 ${e.requested}장, 남은 ${e.remaining}장`;
            });
            Toast.error('주간 카드 한도 초과\n' + msgs.join('\n'));
        } else if (result.error) {
            Toast.error(result.error);
        }
    }

    // ============================================
    // 탭3: 학생 프로필
    // ============================================
    async function loadProfileSelector() {
        if (!currentClassId) return;

        const result = await App.get(`/api/coach.php?action=dashboard&class_id=${currentClassId}`);
        if (!result.success) return;

        const select = document.getElementById('profile-student-select');
        select.innerHTML = '<option value="">학생을 선택하세요</option>' +
            result.students.map(s => `<option value="${s.id}">${s.name}</option>`).join('');
    }

    function selectProfileStudent(studentId) {
        // 프로필 탭으로 전환
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        document.querySelector('[data-tab="profile"]').classList.add('active');
        document.getElementById('tab-profile').classList.add('active');

        document.getElementById('profile-student-select').value = studentId;
        loadStudentProfile(studentId);
    }

    async function loadStudentProfile(studentId) {
        App.showLoading();
        const result = await App.get(`/api/coach.php?action=student_profile&student_id=${studentId}`);
        App.hideLoading();

        if (!result.success) return;

        const { student, rewards, total_coins, audit_log } = result;
        const container = document.getElementById('profile-content');

        container.innerHTML = `
            <div class="student-profile-card">
                <div class="profile-header">
                    <div class="avatar avatar-lg">${student.name.charAt(0)}</div>
                    <div>
                        <div class="profile-name">${student.name}</div>
                        <div class="profile-details">
                            ${student.class_name || ''} ${student.grade ? `/ ${student.grade}` : ''}
                        </div>
                    </div>
                    <div>${App.coinBadge(total_coins)}</div>
                </div>
                <div class="profile-rewards">
                    ${rewards.map(r => `
                        <div class="profile-reward-item" style="border-top:3px solid ${r.color}">
                            <div class="profile-reward-count" style="color:${r.color}">${r.quantity}</div>
                            <div class="profile-reward-name">${r.name_ko}</div>
                        </div>
                    `).join('')}
                </div>
            </div>

            <div class="reward-edit-form">
                <div style="font-size:16px; font-weight:700; margin-bottom:12px;">카드 수정</div>
                ${rewards.map(r => `
                    <div class="reward-edit-row" data-code="${r.code}">
                        <div class="reward-edit-label" style="color:${r.color}">${r.name_ko}</div>
                        <div class="reward-edit-controls">
                            <button class="reward-edit-btn reward-edit-btn-minus" onclick="TeacherApp.adjustReward(${studentId}, '${r.code}', -1)">-</button>
                            <div class="reward-edit-value">${r.quantity}</div>
                            <button class="reward-edit-btn reward-edit-btn-plus" onclick="TeacherApp.adjustReward(${studentId}, '${r.code}', 1)">+</button>
                        </div>
                    </div>
                `).join('')}
                <div class="form-group reward-edit-reason" style="margin-top:12px;">
                    <label class="form-label">수정 사유 (필수)</label>
                    <input type="text" id="reward-reason" class="form-input" placeholder="수정 사유를 입력해 주세요">
                </div>
            </div>

            ${audit_log.length > 0 ? `
                <div class="card" style="margin-top:16px;">
                    <div style="font-size:16px; font-weight:700; margin-bottom:12px;">최근 수정 이력 <span style="font-size:13px; color:#9E9E9E; font-weight:500;">(${audit_log.length}건)</span></div>
                    ${audit_log.map(log => {
                        const d = new Date(log.created_at);
                        const weekdays = ['일','월','화','수','목','금','토'];
                        const fullDate = `${d.getFullYear()}년 ${d.getMonth()+1}월 ${d.getDate()}일 (${weekdays[d.getDay()]}) ${String(d.getHours()).padStart(2,'0')}:${String(d.getMinutes()).padStart(2,'0')}`;
                        const fieldLabels = {
                            steady: '꾸준왕', leader: '리더왕', mission: '미션왕',
                            posture: '바른자세왕', passion: '열정왕',
                        };
                        const fieldName = fieldLabels[log.field_name] || log.field_name;
                        const diff = (parseInt(log.new_value)||0) - (parseInt(log.old_value)||0);
                        const diffStr = diff > 0 ? `+${diff}` : `${diff}`;
                        const diffColor = diff > 0 ? '#43A047' : '#E53935';

                        return `
                        <div class="audit-item" style="cursor:pointer; border-radius:10px; transition: background 0.15s;"
                            onmouseenter="this.style.background='#F5F5F5'"
                            onmouseleave="this.style.background=''"
                            title="${fullDate} | ${log.changed_by_name || '시스템'}님이 ${fieldName} 카드를 ${log.old_value}→${log.new_value}로 변경 (${diffStr}장)${log.reason ? ' | 사유: ' + log.reason : ''}">
                            <div class="audit-meta">
                                <span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;background:#FFF3E0;font-size:13px;font-weight:700;color:#E65100;">${(log.changed_by_name||'S').charAt(0)}</span>
                                <span class="audit-who">${log.changed_by_name || '시스템'}</span>
                                <span class="audit-when">${App.formatDate(log.created_at, 'MM/DD HH:mm')}</span>
                            </div>
                            <div class="audit-detail" style="display:flex; align-items:center; gap:6px;">
                                <span style="font-weight:700;">${fieldName}</span>
                                <span style="color:#BDBDBD;">${log.old_value}</span>
                                <span style="color:#BDBDBD;">→</span>
                                <span style="font-weight:700;">${log.new_value}</span>
                                <span style="font-weight:800; color:${diffColor}; font-size:13px; margin-left:4px;">(${diffStr})</span>
                            </div>
                            ${log.reason ? `<div class="audit-changes" style="margin-top:4px; padding:4px 8px; background:#FFF8E1; border-radius:6px; display:inline-block;">💬 ${log.reason}</div>` : ''}
                        </div>`;
                    }).join('')}
                </div>
            ` : ''}
        `;
    }

    async function adjustReward(studentId, rewardCode, amount) {
        const reason = document.getElementById('reward-reason')?.value?.trim();
        if (!reason) {
            Toast.warning('수정 사유를 입력해 주세요');
            document.getElementById('reward-reason')?.focus();
            return;
        }

        const result = await App.api('/api/coach.php?action=edit_reward', {
            method: 'POST',
            data: { student_id: studentId, reward_code: rewardCode, change_amount: amount, reason },
            showError: false,
        });

        if (result.success) {
            Toast.success(result.message);
            // 화면 값 업데이트
            const row = document.querySelector(`[data-code="${rewardCode}"]`);
            if (row) {
                row.querySelector('.reward-edit-value').textContent = result.new_quantity;
            }
        } else if (result.remaining !== undefined) {
            Toast.error(`주간 한도 초과! 이번 주 남은 수량: ${result.remaining}장`);
        } else if (result.error) {
            Toast.error(result.error);
        }
    }

    // ============================================
    // 시작
    // ============================================
    document.addEventListener('DOMContentLoaded', init);

    return { init, adjustReward, selectProfileStudent };
})();
