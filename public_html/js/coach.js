/**
 * 소리튠 주니어 영어학교 - 코치 대시보드 로직
 */
const CoachApp = (() => {
    let currentClassId = null;
    let classes = [];
    let checklistDate = new Date();
    let qrRefreshInterval = null;
    let showFullList = false;
    let currentSessionId = null;
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
        if (result.logged_in && result.admin.admin_role === 'coach') {
            classes = result.classes || [];
            showDashboard();
            return;
        }

        // 2차: 핑거프린트 자동 로그인
        if (fingerprint) {
            const autoResult = await App.post('/api/coach.php?action=auto_login', {
                fingerprint: fingerprint
            });
            if (autoResult.logged_in) {
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
        document.getElementById('login-pw').addEventListener('keyup', e => { if (e.key === 'Enter') doLogin(); });

        // 비밀번호 변경 모달
        const pwModal = document.getElementById('pw-change-modal');
        document.getElementById('btn-change-pw').addEventListener('click', () => {
            pwModal.style.display = 'flex';
            document.getElementById('pw-current').value = '';
            document.getElementById('pw-new').value = '';
            document.getElementById('pw-confirm').value = '';
            document.getElementById('pw-current').focus();
        });
        document.getElementById('pw-modal-close').addEventListener('click', () => {
            pwModal.style.display = 'none';
        });
        pwModal.addEventListener('click', (e) => {
            if (e.target === pwModal) pwModal.style.display = 'none';
        });
        document.getElementById('btn-pw-submit').addEventListener('click', async () => {
            const currentPw = document.getElementById('pw-current').value.trim();
            const newPw = document.getElementById('pw-new').value.trim();
            const confirmPw = document.getElementById('pw-confirm').value.trim();
            if (!currentPw || !newPw || !confirmPw) {
                Toast.error('모든 항목을 입력해 주세요');
                return;
            }
            if (newPw !== confirmPw) {
                Toast.error('새 비밀번호가 일치하지 않습니다');
                return;
            }
            if (newPw.length < 4) {
                Toast.error('새 비밀번호는 4자 이상이어야 합니다');
                return;
            }
            const result = await App.post('/api/coach.php?action=change_password', {
                current_password: currentPw,
                new_password: newPw,
                confirm_password: confirmPw,
            });
            if (result.success) {
                Toast.success(result.message || '비밀번호가 변경되었습니다');
                pwModal.style.display = 'none';
            }
        });

        // 로그아웃
        document.getElementById('btn-coach-logout').addEventListener('click', () => {
            App.confirm('로그아웃 하시겠습니까?', async () => {
                await App.post('/api/coach.php?action=logout', {
                    fingerprint: fingerprint || '',
                });
                if (qrRefreshInterval) clearInterval(qrRefreshInterval);
                currentSessionId = null;
                window.location.href = '/';
            }, { formal: true });
        });

        // 탭
        App.initTabs(document.getElementById('app'));
        document.getElementById('app').addEventListener('tabChange', (e) => {
            const tab = e.detail.tab;
            if (tab === 'overview') loadOverview();
            else if (tab === 'checklist') loadChecklist();
            else if (tab === 'qr') loadQR();
            else if (tab === 'profile') loadProfileSelector();
            else if (tab === 'homework') loadHomeworkReport();
            else if (tab === 'ace') loadAcePending();
            else if (tab === 'messages') loadMsgThreads();
            else if (tab === 'announce') loadAnnList();
        });

        // 반 선택
        document.getElementById('class-select').addEventListener('change', (e) => {
            currentClassId = parseInt(e.target.value);
            const activeTab = document.querySelector('#coach-tabs .tab-btn.active');
            const tab = activeTab?.dataset?.tab || 'overview';
            if (tab === 'homework') loadHomeworkReport();
            else if (tab === 'ace') loadAcePending();
            else if (tab === 'messages') loadMsgThreads();
            else if (tab === 'announce') loadAnnList();
            else loadOverview();
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
        const loginId = document.getElementById('login-id').value.trim();
        const password = document.getElementById('login-pw').value.trim();

        if (!loginId || !password) {
            Toast.warning('아이디와 비밀번호를 입력해 주세요');
            return;
        }

        App.showLoading();
        const deviceInfo = typeof DeviceFingerprint !== 'undefined' ? DeviceFingerprint.getDeviceInfo() : null;
        const result = await App.post('/api/coach.php?action=login', {
            login_id: loginId,
            password: password,
            fingerprint: fingerprint || '',
            device_info: deviceInfo,
        });
        App.hideLoading();

        if (result.success) {
            classes = result.classes || [];
            Toast.success(`${result.admin.name} 코치님 환영합니다!`);
            showDashboard();
        }
    }

    function showLogin() {
        document.getElementById('view-login').classList.remove('hidden');
        document.getElementById('view-dashboard').classList.add('hidden');
        document.getElementById('login-id').value = '';
        document.getElementById('login-pw').value = '';
        if (qrRefreshInterval) clearInterval(qrRefreshInterval);
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

        // 안 읽은 메시지 배지 폴링 시작
        startUnreadPolling();
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
            const maxR = Math.max(...trend.map(t => t.rate), 1);
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
                    ${isToday ? '<div style="font-size:10px; color:#2196F3; font-weight:600;">TODAY</div>' : ''}
                </div>
                <button class="checklist-date-btn" id="ov-date-next">\u25B6</button>
                ${!isToday ? '<button class="btn btn-sm" id="ov-date-today" style="background:#2196F3; color:#fff; padding:4px 10px; font-size:11px; border-radius:20px;">오늘</button>' : ''}
            </div>

            <!-- 메인 과제율 카드 -->
            <div class="card" style="padding:0; overflow:hidden; margin-bottom:16px; border-radius:20px; box-shadow:0 4px 20px rgba(0,0,0,.1);">
                <div style="padding:20px; background:linear-gradient(135deg, #E3F2FD, #BBDEFB);">
                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px;">
                        <div>
                            <div style="font-weight:800; font-size:20px; color:#1565C0;">${result.class.display_name}</div>
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
                <div style="display:grid; grid-template-columns:repeat(5,1fr); border-top:1px solid #E3F2FD;">
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
                    <div class="list-item" style="cursor:pointer;" onclick="CoachApp.selectProfileStudent(${s.id})">
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
                        <button class="btn btn-sm" id="ranking-today" style="background:#FF7E17; color:#fff; padding:4px 10px; font-size:11px;">오늘</button>
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
        const btn = document.getElementById('btn-save-checklist');
        if (btn && btn.disabled) return;
        if (btn) btn.disabled = true;

        try {
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
        } finally {
            if (btn) btn.disabled = false;
        }
    }

    // ============================================
    // 탭3: QR 출석
    // ============================================
    async function loadQR() {
        if (!currentClassId) return;
        if (qrRefreshInterval) { clearInterval(qrRefreshInterval); qrRefreshInterval = null; }

        const container = document.getElementById('qr-content');

        // 활성 세션 확인
        const result = await App.get(`/qr/api.php?action=status&class_id=${currentClassId}`);
        if (!result.success) return;

        if (result.active) {
            showActiveQR(result);
        } else {
            container.innerHTML = `
                <button class="qr-create-btn" id="btn-create-qr">
                    <div class="qr-create-icon">📸</div>
                    <div class="qr-create-text">QR 출석 시작</div>
                    <div style="font-size:13px; color:#9E9E9E;">터치하여 QR 코드를 생성합니다</div>
                </button>
            `;
            document.getElementById('btn-create-qr').addEventListener('click', createQR);
        }
    }

    async function createQR() {
        App.showLoading();
        const result = await App.post('/api/coach.php?action=create_qr', {
            class_id: currentClassId,
            session_type: 'basic',
        });
        App.hideLoading();

        if (result.success) {
            Toast.success('QR 세션이 생성되었습니다');
            showActiveQR({
                session: {
                    id: result.session_id,
                    session_code: result.session_code,
                    expires_at: result.expires_at,
                },
                qr_image: result.qr_image,
                scan_url: result.scan_url,
            });
        }
    }

    function showActiveQR(data) {
        const container = document.getElementById('qr-content');
        const expiresAt = new Date(data.session.expires_at);
        currentSessionId = data.session.id;
        showFullList = false;

        container.innerHTML = `
            <div class="qr-active-session">
                <div style="font-size:18px; font-weight:700; margin-bottom:8px;">QR 출석 진행중</div>
                <div class="qr-image-container">
                    <img src="${data.qr_image}" alt="QR Code">
                </div>
                <div class="qr-timer" id="qr-timer"></div>
                <button id="btn-copy-link" data-url="${data.scan_url}"
                    style="display:flex; align-items:center; justify-content:center; gap:6px; width:100%; padding:12px; margin-top:4px; background:#E3F2FD; color:#1565C0; border:none; border-radius:10px; font-size:15px; font-weight:700; cursor:pointer; font-family:inherit;">
                    📋 링크 복사하기
                </button>
                <button class="btn btn-danger" id="btn-close-qr" style="margin-top:16px;">세션 종료</button>
            </div>

            <div style="margin-top:20px; text-align:left;">
                <div style="font-size:16px; font-weight:700; margin-bottom:12px;">출석 현황</div>
                <div id="attendance-list"></div>
            </div>
        `;

        // 타이머
        function updateTimer() {
            const now = new Date();
            const diff = expiresAt - now;
            const el = document.getElementById('qr-timer');
            if (!el) return;
            if (diff <= 0) {
                el.textContent = '만료됨';
                if (qrRefreshInterval) clearInterval(qrRefreshInterval);
                return;
            }
            const min = Math.floor(diff / 60000);
            const sec = Math.floor((diff % 60000) / 1000);
            el.textContent = `${String(min).padStart(2, '0')}:${String(sec).padStart(2, '0')}`;
        }
        updateTimer();
        const timerInterval = setInterval(updateTimer, 1000);

        // 링크 복사
        document.getElementById('btn-copy-link').addEventListener('click', (e) => {
            const url = e.currentTarget.dataset.url;
            navigator.clipboard.writeText(url).then(() => {
                Toast.success('링크가 복사되었습니다');
            });
        });

        // 출석 현황 로드 + 자동 갱신
        loadQRFullStatus();
        qrRefreshInterval = setInterval(loadQRFullStatus, 5000);

        // 세션 종료
        document.getElementById('btn-close-qr').addEventListener('click', () => {
            App.confirm('QR 세션을 종료하시겠습니까?', async () => {
                await App.post('/api/coach.php?action=close_qr', { session_id: currentSessionId });
                clearInterval(timerInterval);
                if (qrRefreshInterval) { clearInterval(qrRefreshInterval); qrRefreshInterval = null; }
                currentSessionId = null;
                Toast.info('세션이 종료되었습니다');
                loadQR();
            }, { formal: true });
        });
    }

    async function loadQRFullStatus() {
        if (!currentSessionId || !currentClassId) return;

        const result = await App.get(`/api/coach.php?action=qr_full_status&session_id=${currentSessionId}&class_id=${currentClassId}`);
        if (!result.success) return;

        const list = document.getElementById('attendance-list');
        if (!list) return;

        const currentClassName = classes.find(c => c.id == currentClassId)?.display_name || '';

        // 출석자만 표시
        const attended = result.students.filter(s => s.attended);

        // 대리출석 시도 경고
        let blockedHtml = '';
        if (result.blocked_attempts && result.blocked_attempts.length > 0) {
            blockedHtml = result.blocked_attempts.map(b => {
                const detail = JSON.parse(b.detail || '{}');
                const time = new Date(b.created_at).toLocaleTimeString('ko-KR', { hour:'2-digit', minute:'2-digit' });
                return `
                    <div style="background:#FFF3E0; border-left:3px solid #FF9800; border-radius:8px; padding:10px 12px; margin-bottom:6px;">
                        <div style="display:flex; align-items:center; gap:6px;">
                            <span style="font-size:14px;">⚠️</span>
                            <span style="font-weight:600; font-size:13px; color:#E65100;">대리출석 시도 감지</span>
                            <span style="font-size:11px; color:#999; flex:1; text-align:right;">${time}</span>
                        </div>
                        <div style="font-size:12px; color:#666; margin-top:4px; padding-left:26px;">
                            <b>${detail.attempted_student || b.student_name}</b>님이
                            <b>${detail.existing_student_name || '다른 학생'}</b>님의 기기에서 출석 시도
                        </div>
                    </div>
                `;
            }).join('');
        }

        if (attended.length === 0 && (!result.other_class || result.other_class.length === 0)) {
            list.innerHTML = blockedHtml || '<div style="text-align:center; color:#9E9E9E; padding:20px 0; font-size:14px;">아직 출석자가 없습니다</div>';
            return;
        }

        let html = attended.map(s => {
            const time = s.scanned_at ? new Date(s.scanned_at).toLocaleTimeString('ko-KR', { hour:'2-digit', minute:'2-digit' }) : '';
            return `
                <div style="background:#E8F5E9; border-radius:10px; padding:10px 12px; margin-bottom:6px;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span style="font-size:16px;">🟢</span>
                        <span style="font-weight:600; font-size:14px; color:#333;">${s.name}</span>
                        <span style="background:#C8E6C9; color:#2E7D32; font-size:10px; padding:2px 6px; border-radius:4px; font-weight:600;">${currentClassName}</span>
                        <span style="font-size:11px; color:#999; flex:1; text-align:right;">${time}</span>
                    </div>
                    <div style="margin-top:6px; padding-left:32px;">
                        ${s.posture_given
                            ? `<span style="background:#E8E8E8; color:#999; border:none; border-radius:8px; padding:6px 14px; font-size:12px; font-weight:700; display:inline-block;">지급완료</span>`
                            : `<button onclick="CoachApp.givePostureCard(${s.id})"
                                style="background:#9C27B0; color:#fff; border:none; border-radius:8px; padding:6px 14px; font-size:12px; font-weight:700; cursor:pointer; font-family:inherit; box-shadow:0 1px 3px rgba(0,0,0,.15);">
                                바른자세왕 카드 지급하기
                            </button>`}
                    </div>
                </div>
            `;
        }).join('');

        // 타반
        if (result.other_class && result.other_class.length > 0) {
            html += result.other_class.map(a => {
                const time = a.scanned_at ? new Date(a.scanned_at).toLocaleTimeString('ko-KR', { hour:'2-digit', minute:'2-digit' }) : '';
                return `
                    <div style="background:#E3F2FD; border-radius:10px; padding:10px 12px; margin-bottom:6px;">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <span style="font-size:16px;">🔵</span>
                            <span style="font-weight:600; font-size:14px; color:#333;">${a.student_name}</span>
                            <span style="background:#BBDEFB; color:#1565C0; font-size:10px; padding:2px 6px; border-radius:4px; font-weight:600;">${a.home_class_name || '타반'}</span>
                            <span style="font-size:11px; color:#999; flex:1; text-align:right;">${time}</span>
                        </div>
                        <div style="margin-top:6px; padding-left:32px;">
                            ${a.posture_given
                                ? `<span style="background:#E8E8E8; color:#999; border:none; border-radius:8px; padding:6px 14px; font-size:12px; font-weight:700; display:inline-block;">지급완료</span>`
                                : `<button onclick="CoachApp.givePostureCard(${a.student_id})"
                                    style="background:#9C27B0; color:#fff; border:none; border-radius:8px; padding:6px 14px; font-size:12px; font-weight:700; cursor:pointer; font-family:inherit; box-shadow:0 1px 3px rgba(0,0,0,.15);">
                                    바른자세왕 카드 지급하기
                                </button>`}
                        </div>
                    </div>
                `;
            }).join('');
        }

        list.innerHTML = blockedHtml + html;
    }

    async function manualAttendance(sessionId, studentId, type) {
        const result = await App.post('/api/coach.php?action=qr_manual_attendance', {
            session_id: sessionId,
            student_id: studentId,
            type: type,
            reason: type === 'add' ? '코치 수동 출석 추가' : '코치 수동 출석 제거'
        });
        if (result.success) {
            Toast.success(result.message);
            loadQRFullStatus();
        }
    }

    async function givePostureCard(studentId) {
        const result = await App.post('/api/coach.php?action=give_posture_card', {
            student_id: studentId,
            session_id: currentSessionId
        });
        if (result.success) {
            const msg = result.remaining !== undefined
                ? `${result.message} (이번 주 남은 횟수: ${result.remaining}/${result.limit})`
                : result.message;
            Toast.success(msg);
            loadQRFullStatus();
        }
    }

    // ============================================
    // 탭4: 학생 프로필
    // ============================================
    async function loadProfileSelector() {
        if (!currentClassId) return;

        const result = await App.get(`/api/coach.php?action=dashboard&class_id=${currentClassId}`);
        if (!result.success) return;

        const select = document.getElementById('profile-student-select');
        const sorted = [...result.students].sort((a, b) => a.name.localeCompare(b.name, 'ko'));
        select.innerHTML = '<option value="">학생을 선택하세요</option>' +
            sorted.map(s => `<option value="${s.id}">${s.name}</option>`).join('');
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

    function openAceFromProfile(studentId) {
        // ACE 평가 탭으로 전환 후 학생 상세 열기
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        document.querySelector('[data-tab="ace"]').classList.add('active');
        document.getElementById('tab-ace').classList.add('active');
        openAceEval(studentId);
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
                        <div class="profile-reward-item profile-reward-clickable" style="border-top:3px solid ${r.color}" onclick="CoachApp.showCardDates(${studentId}, '${r.code}')">
                            <div class="profile-reward-count" style="color:${r.color}">${r.quantity}</div>
                            <div class="profile-reward-name">${r.name_ko}</div>
                        </div>
                    `).join('')}
                </div>
                <button class="ace-profile-btn" onclick="CoachApp.openAceFromProfile(${studentId})">
                    🎤 ACE/BRAVO 도전 상황
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                </button>
            </div>

            <div class="reward-edit-form">
                <div style="font-size:16px; font-weight:700; margin-bottom:12px;">카드 수정</div>
                ${rewards.map(r => `
                    <div class="reward-edit-row" data-code="${r.code}">
                        <div class="reward-edit-label" style="color:${r.color}">${r.name_ko}</div>
                        <div class="reward-edit-controls">
                            <button class="reward-edit-btn reward-edit-btn-minus" onclick="CoachApp.adjustReward(${studentId}, '${r.code}', -1)">-</button>
                            <div class="reward-edit-value">${r.quantity}</div>
                            <button class="reward-edit-btn reward-edit-btn-plus" onclick="CoachApp.adjustReward(${studentId}, '${r.code}', 1)">+</button>
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
                                <span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;background:#E3F2FD;font-size:13px;font-weight:700;color:#1565C0;">${(log.changed_by_name||'S').charAt(0)}</span>
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

    async function showCardDates(studentId, code) {
        App.showLoading();
        const result = await App.get(`/api/coach.php?action=card_detail&student_id=${studentId}&code=${code}`);
        App.hideLoading();
        if (!result.success) return;

        // 현재 프로필의 rewards에서 카드 정보 찾기
        const rewardItems = document.querySelectorAll('.reward-edit-row');
        let cardName = code, cardColor = '#666';
        rewardItems.forEach(el => {
            if (el.dataset.code === code) {
                cardName = el.querySelector('.reward-edit-label')?.textContent || code;
                cardColor = el.querySelector('.reward-edit-label')?.style.color || '#666';
            }
        });
        const qtyEl = document.querySelector(`.profile-reward-item[onclick*="'${code}'"] .profile-reward-count`);
        const qty = qtyEl?.textContent || '0';

        // 날짜별 그룹핑 (양수만)
        const dateMap = {};
        result.history.forEach(h => {
            if (h.change_amount <= 0) return;
            const dateStr = h.created_at.substring(0, 10);
            dateMap[dateStr] = (dateMap[dateStr] || 0) + Number(h.change_amount);
        });
        const dates = Object.entries(dateMap).sort((a, b) => b[0].localeCompare(a[0]));

        let html = `
            <div style="text-align:center; margin-bottom:16px;">
                <div style="font-size:28px; font-weight:900; color:${cardColor}">${qty}<span style="font-size:14px; font-weight:600; color:#90A4AE">장</span></div>
            </div>
        `;

        if (dates.length === 0) {
            html += `<div style="text-align:center; color:#BDBDBD; padding:20px 0;">기록 없음</div>`;
        } else {
            html += `
                <div style="font-size:13px; font-weight:700; color:#546E7A; margin-bottom:10px;">📅 획득 날짜</div>
                <div style="display:flex; flex-wrap:wrap; gap:8px;">
                    ${dates.map(([date, count]) => {
                        const suffix = count > 1 ? ` *${count}개` : '';
                        return `<span class="date-tag">${date}${suffix}</span>`;
                    }).join('')}
                </div>
            `;
        }

        App.openModal(cardName, html);
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
    // 소리과제 현황 (단일 반)
    // ============================================
    let hwData = null;
    let hwPeriod = 'weekly';
    let hwChart = null;
    let hwSelectedIdx = -1; // -1 = auto (isCurrent)

    function esc(str) {
        const d = document.createElement('div');
        d.textContent = str ?? '';
        return d.innerHTML;
    }

    async function loadHomeworkReport() {
        const container = document.getElementById('hw-content');
        if (!currentClassId) {
            container.innerHTML = '<div style="text-align:center; padding:40px; color:#999;">반을 선택해 주세요</div>';
            return;
        }
        container.innerHTML = '<div style="text-align:center; padding:40px; color:#999;">로딩 중...</div>';

        try {
            hwData = await App.get('/api/coach.php?action=homework_report&class_id=' + currentClassId);
            if (!hwData || !hwData.success) {
                container.innerHTML = '<div style="text-align:center; padding:40px; color:#e53935;">데이터를 불러올 수 없습니다: ' + (hwData?.error || '') + '</div>';
                return;
            }
            hwPeriod = 'weekly';
            renderHwUI();
            loadHwAlerts();
        } catch (e) {
            console.error('homework_report error:', e);
            container.innerHTML = '<div style="text-align:center; padding:40px; color:#e53935;">오류: ' + (e.message || '') + '</div>';
        }
    }

    function renderHwUI() {
        const container = document.getElementById('hw-content');
        const info = hwData.classInfo;
        container.innerHTML = `
            <div class="hw-info" id="hw-info"></div>
            <div class="hw-period-wrap">
                <button class="hw-period-btn active" data-period="weekly">주간</button>
                <button class="hw-period-btn" data-period="monthly">월간</button>
                <button class="hw-period-btn" data-period="daily">일별</button>
            </div>
            <div class="hw-chart-wrap">
                <div class="hw-chart-inner">
                    <canvas id="hw-chart"></canvas>
                </div>
            </div>
            <div id="hw-alerts"></div>
        `;

        container.querySelectorAll('.hw-period-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                hwPeriod = btn.dataset.period;
                hwSelectedIdx = -1; // 기간 변경 시 현재로 리셋
                container.querySelectorAll('.hw-period-btn').forEach(b => b.classList.toggle('active', b === btn));
                renderHwChart();
            });
        });

        renderHwChart();
    }

    function renderHwChart() {
        if (!hwData) return;
        const pd = hwData[hwPeriod];
        if (!pd) return;
        const labels = pd.labels;
        const data = pd.data;
        const info = hwData.classInfo;
        const color = info.color || '#FF7E17';
        const isDaily = hwPeriod === 'daily';

        // 선택 인덱스 결정
        if (hwSelectedIdx < 0 || hwSelectedIdx >= data.length) {
            hwSelectedIdx = data.findIndex(d => d.isCurrent);
            if (hwSelectedIdx < 0) hwSelectedIdx = data.length - 1;
        }

        // 정보 박스
        const infoEl = document.getElementById('hw-info');
        const idx = hwSelectedIdx;
        const hasPrev = idx > 0;
        const hasNext = idx < data.length - 1;
        const navBtnStyle = 'background:none; border:1.5px solid #E0E0E0; border-radius:8px; width:28px; height:28px; font-size:14px; cursor:pointer; display:flex; align-items:center; justify-content:center; padding:0; color:#757575; font-weight:700;';

        if (idx >= 0 && data[idx]) {
            const d = data[idx];
            const hasSoFar = d.rate_so_far !== undefined;
            const rateColor = d.rate >= 80 ? '#4CAF50' : d.rate >= 50 ? '#FF9800' : '#F44336';
            const soFarColor = hasSoFar ? (d.rate_so_far >= 80 ? '#4CAF50' : d.rate_so_far >= 50 ? '#FF9800' : '#F44336') : '';
            const isCurrent = d.isCurrent;
            infoEl.innerHTML = `
                <div style="display:flex; align-items:center; gap:8px; margin-bottom:8px;">
                    <div style="font-size:15px; font-weight:700; color:#333;">${esc(info.name)}반</div>
                    <div style="display:flex; align-items:center; gap:4px;">
                        <button onclick="CoachApp.hwNav(-1)" style="${navBtnStyle} ${!hasPrev ? 'opacity:0.3; cursor:default;' : ''}" ${!hasPrev ? 'disabled' : ''}>◀</button>
                        <div style="font-size:13px; font-weight:600; color:#555; min-width:70px; text-align:center;">${esc(labels[idx])}</div>
                        <button onclick="CoachApp.hwNav(1)" style="${navBtnStyle} ${!hasNext ? 'opacity:0.3; cursor:default;' : ''}" ${!hasNext ? 'disabled' : ''}>▶</button>
                    </div>
                    ${!isCurrent ? '<button onclick="CoachApp.hwNav(0)" style="background:none; border:none; font-size:11px; color:#2196F3; cursor:pointer; font-weight:600; padding:2px 6px;">현재</button>' : ''}
                    <button onclick="CoachApp.showHwHelp()" style="background:none; border:1.5px solid #BDBDBD; border-radius:50%; width:22px; height:22px; font-size:12px; color:#999; cursor:pointer; display:flex; align-items:center; justify-content:center; padding:0; line-height:1; font-weight:700;">?</button>
                </div>
                ${hasSoFar ? `
                    <div style="font-size:13px; color:#999; margin-bottom:6px;">과제 완료율</div>
                    <div style="display:flex; gap:16px; align-items:flex-start;">
                        <div>
                            <div style="display:flex; align-items:baseline; gap:4px;">
                                <span style="font-size:28px; font-weight:900; color:${soFarColor};">${d.rate_so_far}%</span>
                            </div>
                            <div style="font-size:11px; color:#999; margin-top:2px;">오늘까지 (${d.submitted}/${d.possible_so_far}건)</div>
                        </div>
                        <div style="border-left:1px solid #E0E0E0; padding-left:16px;">
                            <div style="display:flex; align-items:baseline; gap:4px;">
                                <span style="font-size:28px; font-weight:900; color:${rateColor};">${d.rate}%</span>
                            </div>
                            <div style="font-size:11px; color:#999; margin-top:2px;">전체 기간 (${d.submitted}/${d.possible}건)</div>
                        </div>
                    </div>
                    <div style="font-size:12px; color:#757575; margin-top:6px;">학생 ${info.students}명 · 진행 ${d.elapsed_days}/${d.total_days}일</div>
                ` : `
                    <div style="font-size:13px; color:#999; margin-bottom:6px;">과제 완료율</div>
                    <div style="display:flex; align-items:baseline; gap:6px;">
                        <span style="font-size:28px; font-weight:900; color:${rateColor};">${d.rate}%</span>
                        <span style="font-size:12px; color:#BDBDBD;">(${d.submitted}/${d.possible}건)</span>
                    </div>
                    <div style="font-size:12px; color:#757575; margin-top:4px;">학생 ${info.students}명 · 필요 <strong>${d.required}일</strong></div>
                `}
            `;
        } else {
            infoEl.innerHTML = `
                <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                    <div style="font-size:15px; font-weight:700; color:#333;">${esc(info.name)}반</div>
                    <button onclick="CoachApp.showHwHelp()" style="background:none; border:1.5px solid #BDBDBD; border-radius:50%; width:22px; height:22px; font-size:12px; color:#999; cursor:pointer; display:flex; align-items:center; justify-content:center; padding:0; line-height:1; font-weight:700;">?</button>
                </div>
                <div style="font-size:13px; color:#999;">학생 ${info.students}명</div>
            `;
        }

        // 차트
        const ctx = document.getElementById('hw-chart');
        if (!ctx) return;
        if (hwChart) { hwChart.destroy(); hwChart = null; }

        hwChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels.map((l, i) => data[i]?.isCurrent ? l + ' *' : l),
                datasets: [{
                    label: info.name + '반 과제 완료율',
                    data: data.map(d => d.rate),
                    borderColor: color,
                    backgroundColor: color + '20',
                    borderWidth: 2.5,
                    pointRadius: isDaily ? 2 : 5,
                    pointBackgroundColor: color,
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointHoverRadius: 7,
                    tension: 0.3,
                    fill: true,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 600, easing: 'easeOutQuart' },
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.85)',
                        titleFont: { size: 13, weight: '600' },
                        bodyFont: { size: 12 },
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(ctx) {
                                const d = data[ctx.dataIndex];
                                return d ? `과제 완료율: ${d.rate}% (${d.submitted}/${d.possible}건)` : ctx.parsed.y + '%';
                            },
                            afterLabel: function(ctx) {
                                const d = data[ctx.dataIndex];
                                return d ? `필요 ${d.required}일 | 학생 ${info.students}명` : '';
                            },
                            afterTitle: function(ctxArr) {
                                const d = data[ctxArr[0]?.dataIndex];
                                return d?.isCurrent ? '(진행 중)' : '';
                            }
                        },
                    },
                },
                scales: {
                    y: {
                        min: 0, max: 100,
                        ticks: { callback: v => v + '%', stepSize: 20, font: { size: 11 } },
                        grid: { color: '#f0f0f0' },
                    },
                    x: {
                        ticks: { font: { size: isDaily ? 9 : 10 }, maxRotation: 45 },
                        grid: { display: false },
                    },
                },
            },
        });
    }

    function hwNav(dir) {
        if (!hwData) return;
        const pd = hwData[hwPeriod];
        if (!pd) return;
        if (dir === 0) {
            // "현재" 버튼 → isCurrent로 복귀
            hwSelectedIdx = -1;
        } else {
            const newIdx = hwSelectedIdx + dir;
            if (newIdx < 0 || newIdx >= pd.data.length) return;
            hwSelectedIdx = newIdx;
        }
        renderHwChart();
    }

    function showHwHelp() {
        App.openModal('과제 완료율 안내', `
            <div style="font-size:14px; line-height:1.8; color:#333;">
                <div style="font-weight:700; color:#FF7E17; margin-bottom:8px;">과제 완료율이란?</div>
                <div style="margin-bottom:14px;">반 학생들의 <strong>소리과제 제출률</strong>을 기간별로 보여줍니다.</div>
                <div style="background:#F5F5F5; border-radius:10px; padding:12px; margin-bottom:10px;">
                    <div style="font-weight:600; margin-bottom:6px;">오늘까지 기준</div>
                    <div style="font-size:13px; color:#555;">제출 건수 ÷ (학생 수 × <strong>경과일 수</strong>) × 100</div>
                    <div style="font-size:12px; color:#999; margin-top:2px;">현재까지 지나간 날만 분모로 계산합니다.</div>
                </div>
                <div style="background:#F5F5F5; border-radius:10px; padding:12px; margin-bottom:14px;">
                    <div style="font-weight:600; margin-bottom:6px;">전체 기간 기준</div>
                    <div style="font-size:13px; color:#555;">제출 건수 ÷ (학생 수 × <strong>필요일 수</strong>) × 100</div>
                    <div style="font-size:12px; color:#999; margin-top:2px;">남은 날을 포함한 전체 기간을 분모로 계산합니다.</div>
                </div>
                <div style="font-size:13px; color:#555;">
                    <div style="margin-bottom:6px;"><strong>제출 건수</strong>: 학생들이 실제로 소리과제를 제출한 횟수</div>
                    <div style="margin-bottom:6px;"><strong>경과일 수</strong>: 해당 기간의 시작일부터 오늘까지 지난 날수</div>
                    <div style="margin-bottom:6px;"><strong>필요일 수</strong>: 주간 캘린더에 설정된 전체 과제 요구 일수</div>
                    <div><strong>◀ ▶</strong>: 이전/다음 기간으로 이동하여 비교할 수 있습니다</div>
                </div>
                <div style="font-size:12px; color:#BDBDBD; margin-top:10px;">* 이미 종료된 기간은 두 값이 동일하므로 하나만 표시됩니다.</div>
            </div>
        `);
    }

    async function loadHwAlerts() {
        const container = document.getElementById('hw-alerts');
        if (!container) return;

        try {
            const result = await App.get('/api/coach.php?action=homework_alerts&class_id=' + currentClassId);
            if (!result.success) return;

            const alerts = result.alerts || [];
            if (alerts.length === 0) {
                container.innerHTML = '<div class="hw-no-alert">모든 학생이 최근 3일 이내 소리과제를 제출했습니다!</div>';
                return;
            }

            const levelColor = { red: '#F44336', orange: '#FF9800', yellow: '#FFC107' };

            let html = '<div class="hw-alert-section" style="background:#fff; border-radius:14px; box-shadow:0 2px 8px rgba(0,0,0,0.06); overflow:hidden;">';
            html += '<div class="hw-alert-header">&#9888;&#65039; 특별 관심 학생 <span style="font-size:13px; font-weight:600; background:#FF7E17; color:#fff; padding:2px 8px; border-radius:10px; margin-left:6px;">' + alerts.length + '명</span></div>';

            alerts.forEach(a => {
                const daysText = a.days_since === null ? '미제출' : a.days_since + '일';
                const lastText = a.last_hw_date || '기록 없음';
                const color = levelColor[a.level] || '#999';
                html += `<div class="hw-alert-card level-${a.level}">
                    <div style="flex:1;">
                        <div class="hw-alert-name">${esc(a.name)}</div>
                        <div class="hw-alert-class">${esc(a.class_name)}</div>
                    </div>
                    <div>
                        <div class="hw-alert-days" style="color:${color}">${daysText}</div>
                        <div class="hw-alert-last">마지막: ${esc(lastText)}</div>
                    </div>
                </div>`;
            });

            html += '</div>';
            container.innerHTML = html;
        } catch(e) {
            container.innerHTML = '';
        }
    }

    // ============================================
    // ACE 평가
    // ============================================
    let acePlayingAudio = null;
    let aceTemplates = null;
    let bravoTemplates = null;

    let aceFilter = 'all';
    const BRAVO_LEVEL_NAMES = {
        1: 'Jr 1 🟡', 2: 'Jr 2 🟡', 3: 'Jr 3 🟡',
        4: 'Jr 4 🟢', 5: 'Jr 5 🟢', 6: 'Jr 6 🟢',
        7: 'Jr 7 🔵', 8: 'Jr 8 🔵', 9: 'Jr 9 🔵',
    };

    async function loadAcePending() {
        const container = document.getElementById('ace-content');
        if (!currentClassId) {
            container.innerHTML = '<div style="text-align:center; padding:40px; color:#999;">반을 선택해주세요</div>';
            return;
        }

        container.innerHTML = '<div style="text-align:center; padding:40px;"><div class="loading-spinner"></div></div>';
        const [result, bravoResult] = await Promise.all([
            App.get('/api/ace.php?action=coach_pending&class_id=' + currentClassId),
            App.get('/api/bravo.php?action=coach_pending&class_id=' + currentClassId),
        ]);

        if (!result.success) {
            container.innerHTML = '<div style="text-align:center; padding:40px; color:#F44336;">데이터를 불러올 수 없습니다</div>';
            return;
        }

        const pending = result.pending || [];
        const beforeOnly = result.before_submitted || [];
        const bravoPending = bravoResult.success ? (bravoResult.pending || []) : [];
        const sm = result.summary || {};
        const total = sm.total_students || 0;

        let html = '';

        // ── 요약 카드 ──
        const stats = [
            { icon: '🎵', label: 'Before', value: sm.before_completed || 0, color: '#2196F3', retry: 0 },
            { icon: '🔤', label: 'ACE 1', value: sm.ace1_pass || 0, color: '#4CAF50', retry: sm.ace1_retry || 0 },
            { icon: '📝', label: 'ACE 2', value: sm.ace2_pass || 0, color: '#FF9800', retry: sm.ace2_retry || 0 },
            { icon: '💬', label: 'ACE 3', value: sm.ace3_pass || 0, color: '#9C27B0', retry: sm.ace3_retry || 0 },
        ];

        html += `<div class="ace-summary">
            <div class="ace-summary-header">
                <div class="ace-summary-title">ACE 현황</div>
                <div class="ace-summary-sub">전체 ${total}명</div>
            </div>
            <div class="ace-summary-grid">
                ${stats.map(s => `
                    <div class="ace-summary-cell">
                        <div class="ace-summary-icon">${s.icon}</div>
                        <div class="ace-summary-value" style="color:${s.value > 0 ? s.color : '#E0E0E0'}">${s.value}<span class="ace-summary-total">/${total}</span></div>
                        <div class="ace-summary-label">${s.label}</div>
                        ${s.retry ? `<div class="ace-summary-retry">🔄 ${s.retry}명</div>` : ''}
                    </div>
                `).join('')}
            </div>
        </div>`;

        // ── BRAVO 현황 ──
        const bs = bravoResult.success ? (bravoResult.summary || {}) : {};
        const bravoTotal = bs.total_students || 0;
        const bravoStats = [
            { icon: '🟡', label: 'Jr 1', value: bs.jr1_pass || 0, color: '#F59E0B' },
            { icon: '🟡', label: 'Jr 2', value: bs.jr2_pass || 0, color: '#FB923C' },
            { icon: '🟡', label: 'Jr 3', value: bs.jr3_pass || 0, color: '#EA580C' },
            { icon: '🟢', label: 'Jr 4', value: bs.jr4_pass || 0, color: '#10B981' },
            { icon: '🟢', label: 'Jr 5', value: bs.jr5_pass || 0, color: '#059669' },
            { icon: '🟢', label: 'Jr 6', value: bs.jr6_pass || 0, color: '#047857' },
        ];

        html += `<div class="ace-summary bravo-summary">
            <div class="ace-summary-header bravo-summary-header">
                <div class="ace-summary-title">BRAVO 현황</div>
                <div class="ace-summary-sub">ACE 완료 ${bravoTotal}명</div>
            </div>
            <div class="ace-summary-grid bravo-summary-grid">
                ${bravoStats.map(s => `
                    <div class="ace-summary-cell">
                        <div class="ace-summary-icon">${s.icon}</div>
                        <div class="ace-summary-value" style="color:${s.value > 0 ? s.color : '#E0E0E0'}">${s.value}<span class="ace-summary-total">/${bravoTotal}</span></div>
                        <div class="ace-summary-label">${s.label}</div>
                    </div>
                `).join('')}
            </div>
        </div>`;

        // ── 평가 대기 리스트 ──
        const allPending = [
            ...pending.map(p => ({ ...p, type: 'eval' })),
            ...beforeOnly.map(p => ({ ...p, type: 'before' })),
            ...bravoPending.map(p => ({ ...p, type: 'bravo' })),
        ];

        html += `<div class="ace-pending-section">
            <div class="ace-section-title">테스트 평가 대기 중 (${allPending.length}건)</div>
            <div class="ace-filter-chips">
                <button class="ace-filter-chip ${aceFilter === 'all' ? 'active' : ''}" onclick="CoachApp.filterAce('all')">전체</button>
                <button class="ace-filter-chip ${aceFilter === '1' ? 'active' : ''}" onclick="CoachApp.filterAce('1')">ACE 1</button>
                <button class="ace-filter-chip ${aceFilter === '2' ? 'active' : ''}" onclick="CoachApp.filterAce('2')">ACE 2</button>
                <button class="ace-filter-chip ${aceFilter === '3' ? 'active' : ''}" onclick="CoachApp.filterAce('3')">ACE 3</button>
                <button class="ace-filter-chip ${aceFilter === 'bravo' ? 'active' : ''}" onclick="CoachApp.filterAce('bravo')">Bravo</button>
            </div>
            <div class="ace-pending-list" id="ace-pending-list">`;

        const levelNames = { 1: 'ACE1 · 1음절', 2: 'ACE2 · 긴단어', 3: 'ACE3 · 문장' };

        if (allPending.length === 0) {
            html += `<div style="text-align:center; padding:32px; color:#999; font-size:14px;">평가 대기 항목이 없습니다</div>`;
        }

        allPending.forEach(p => {
            if (p.type === 'bravo') {
                const bLvl = parseInt(p.bravo_level);
                const hidden = aceFilter !== 'all' && aceFilter !== 'bravo' ? 'style="display:none;"' : '';
                const autoLabel = p.auto_result === 'pass' ? '자동 PASS' : '자동 FAIL';
                const autoColor = p.auto_result === 'pass' ? '#4CAF50' : '#F44336';
                html += `
                    <div class="ace-pending-item" data-level="bravo" onclick="CoachApp.openBravoEval(${p.student_id})" ${hidden}>
                        <div class="ace-pending-avatar" style="background:${p.class_color || '#7C5CFC'}">${esc(p.student_name).charAt(0)}</div>
                        <div class="ace-pending-info">
                            <div class="ace-pending-name">${esc(p.student_name)}</div>
                            <div class="ace-pending-meta">Bravo ${BRAVO_LEVEL_NAMES[bLvl] || 'Jr ' + bLvl} · <span style="color:${autoColor}">${autoLabel}</span> · ${p.submitted_at ? p.submitted_at.slice(5,16).replace('T',' ') : ''}</div>
                        </div>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                    </div>`;
                return;
            }

            const lvl = parseInt(p.ace_level);
            const filterAttr = p.type === 'before' ? '1' : String(lvl);
            const hidden = aceFilter !== 'all' && aceFilter !== filterAttr ? 'style="display:none;"' : '';

            if (p.type === 'before') {
                html += `
                    <div class="ace-pending-item ace-pending-before" data-level="1" onclick="CoachApp.openAceEval(${p.student_id})" ${hidden}>
                        <div class="ace-pending-avatar" style="background:${p.class_color || '#2196F3'}">${esc(p.student_name).charAt(0)}</div>
                        <div class="ace-pending-info">
                            <div class="ace-pending-name">${esc(p.student_name)}</div>
                            <div class="ace-pending-meta">Before 녹음 완료 · ${p.submitted_at ? p.submitted_at.slice(5,16).replace('T',' ') : ''}</div>
                        </div>
                        <span style="font-size:11px; color:#fff; padding:3px 8px; background:#4CAF50; border-radius:6px;">완료 ✅</span>
                    </div>`;
            } else {
                html += `
                    <div class="ace-pending-item" data-level="${lvl}" onclick="CoachApp.openAceEval(${p.student_id})" ${hidden}>
                        <div class="ace-pending-avatar" style="background:${p.class_color || '#2196F3'}">${esc(p.student_name).charAt(0)}</div>
                        <div class="ace-pending-info">
                            <div class="ace-pending-name">${esc(p.student_name)}</div>
                            <div class="ace-pending-meta">${levelNames[lvl] || 'ACE' + lvl} · ${p.submitted_at ? p.submitted_at.slice(5,16).replace('T',' ') : ''}</div>
                        </div>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                    </div>`;
            }
        });

        html += `</div></div>`;

        container.innerHTML = html;
    }

    function filterAce(level) {
        aceFilter = level;
        document.querySelectorAll('.ace-filter-chip').forEach(c => {
            const txt = c.textContent.trim();
            const match = level === 'all' ? txt === '전체' :
                          level === 'bravo' ? txt === 'Bravo' :
                          txt === 'ACE ' + level;
            c.classList.toggle('active', match);
        });
        document.querySelectorAll('#ace-pending-list .ace-pending-item').forEach(el => {
            const elLevel = el.dataset.level;
            el.style.display = (level === 'all' || elLevel === level) ? '' : 'none';
        });
    }

    async function openAceEval(studentId) {
        const container = document.getElementById('ace-content');
        container.innerHTML = '<div style="text-align:center; padding:40px;"><div class="loading-spinner"></div></div>';

        const [result, tmplResult] = await Promise.all([
            App.get('/api/ace.php?action=coach_student_detail&student_id=' + studentId),
            aceTemplates ? Promise.resolve({ success: true, templates: aceTemplates }) : App.get('/api/ace.php?action=comment_templates'),
        ]);

        if (!result.success) {
            container.innerHTML = '<div style="text-align:center; padding:40px; color:#F44336;">' + esc(result.error || '오류') + '</div>';
            return;
        }

        if (tmplResult.success && tmplResult.templates) {
            aceTemplates = tmplResult.templates;
        }

        const { student, submissions, recordings, evaluations } = result;

        // 레벨별 그룹화
        const levels = [1, 2, 3];
        let html = `
            <div class="ace-eval-header">
                <button class="ace-back-btn" onclick="CoachApp.loadAcePending()">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                </button>
                <div class="ace-eval-student">
                    <div class="ace-eval-avatar" style="background:${student.class_color || '#2196F3'}">${esc(student.name).charAt(0)}</div>
                    <div>
                        <div style="font-weight:700; font-size:16px;">${esc(student.name)}</div>
                        <div style="font-size:12px; color:#999;">${esc(student.class_name || '')}</div>
                    </div>
                </div>
            </div>
        `;

        levels.forEach(level => {
            const levelNames = { 1: 'ACE1 · 1음절 단어', 2: 'ACE2 · 긴 단어', 3: 'ACE3 · 문장' };
            // 최신 submission을 찾기 위해 뒤에서부터 검색 (API가 created_at DESC로 정렬)
            const beforeSub = submissions.find(s => parseInt(s.ace_level) === level && s.role === 'before' && s.status !== 'recording');
            const afterSub = submissions.find(s => parseInt(s.ace_level) === level && s.role === 'after' && s.status !== 'recording');
            // 최신 평가 (가장 마지막)
            const levelEvals = evaluations.filter(e => parseInt(e.ace_level) === level);
            const existingEval = levelEvals.length > 0 ? levelEvals[0] : null; // DESC 정렬이므로 첫 번째가 최신

            if (!beforeSub && !afterSub) return;

            const beforeRecs = recordings.filter(r => parseInt(r.submission_id) === parseInt(beforeSub?.id));
            const afterRecs = recordings.filter(r => parseInt(r.submission_id) === parseInt(afterSub?.id));

            const hasBoth = beforeSub && afterSub;
            // 재도전: 기존 평가가 retry이고 새로운 after가 submitted면 평가 가능
            const canEval = hasBoth && afterSub.status === 'submitted' &&
                (!existingEval || existingEval.result === 'retry');

            html += `<div class="ace-eval-level" data-level="${level}">`;
            html += `<div class="ace-eval-level-header">
                <span class="ace-eval-level-name">${levelNames[level]}</span>`;
            if (canEval && existingEval && existingEval.result === 'retry') {
                html += '<span class="ace-result-badge" style="background:#FFF3E0; color:#E65100;">재제출 · 평가 대기</span>';
            } else if (existingEval) {
                const badge = existingEval.result === 'pass'
                    ? '<span class="ace-result-badge pass">PASS ✅</span>'
                    : '<span class="ace-result-badge retry">재도전 🔄</span>';
                html += badge;
            }
            html += `</div>`;

            // 녹음 비교 영역
            if (beforeRecs.length > 0 || afterRecs.length > 0) {
                // 항목별 Before/After 매칭
                const allItems = new Map();
                beforeRecs.forEach(r => {
                    allItems.set(r.item_index, { ...(allItems.get(r.item_index) || {}), before: r, text: r.item_text, ipa: r.item_ipa, type: r.item_type });
                });
                afterRecs.forEach(r => {
                    allItems.set(r.item_index, { ...(allItems.get(r.item_index) || {}), after: r, text: r.item_text, ipa: r.item_ipa, type: r.item_type });
                });

                html += '<div class="ace-eval-items">';
                for (const [idx, item] of [...allItems.entries()].sort((a, b) => a[0] - b[0])) {
                    html += `<div class="ace-eval-item">
                        <div class="ace-eval-item-text">${esc(item.text || '')}`;
                    if (item.ipa) html += ` <span class="ace-eval-ipa">${esc(item.ipa)}</span>`;
                    html += `</div>
                        <div class="ace-eval-players">`;
                    if (item.before) {
                        html += `<button class="ace-play-btn before" onclick="CoachApp.playAceAudio(${item.before.id}, this)">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21"/></svg> Before
                        </button>`;
                    } else {
                        html += `<span class="ace-play-empty">Before 없음</span>`;
                    }
                    if (item.after) {
                        html += `<button class="ace-play-btn after" onclick="CoachApp.playAceAudio(${item.after.id}, this)">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21"/></svg> After
                        </button>`;
                    } else {
                        html += `<span class="ace-play-empty">After 없음</span>`;
                    }
                    html += `</div></div>`;
                }
                html += '</div>';
            }

            // 평가 폼 (미평가 + before/after 모두 있을 때)
            if (canEval) {
                html += `<div class="ace-eval-form" id="ace-form-${level}">
                    <div class="ace-eval-form-title">평가</div>
                    <div class="ace-eval-result-btns">
                        <button class="ace-result-btn pass" data-result="pass" onclick="CoachApp.selectAceResult(${level}, 'pass')">
                            ✅ PASS
                        </button>
                        <button class="ace-result-btn retry" data-result="retry" onclick="CoachApp.selectAceResult(${level}, 'retry')">
                            🔄 재도전
                        </button>
                    </div>
                    <div class="ace-comment-section hidden" id="ace-comment-${level}">
                        <div class="ace-comment-type-btns">
                            <button class="ace-comment-type-btn" data-type="excellent" onclick="CoachApp.selectCommentType(${level}, 'excellent')">🌟 우수</button>
                            <button class="ace-comment-type-btn" data-type="growing" onclick="CoachApp.selectCommentType(${level}, 'growing')">🌱 성장</button>
                            <button class="ace-comment-type-btn" data-type="support" onclick="CoachApp.selectCommentType(${level}, 'support')">💪 보완</button>
                        </div>
                        <div class="ace-template-select hidden" id="ace-tmpl-${level}"></div>
                        <textarea class="ace-comment-text" id="ace-text-${level}" placeholder="코멘트를 입력하거나 위에서 템플릿을 선택하세요..." rows="3"></textarea>
                        <button class="btn btn-primary ace-submit-eval" style="background:#2196F3; width:100%;" onclick="CoachApp.submitAceEval(${studentId}, ${level})">
                            평가 저장
                        </button>
                    </div>
                </div>`;
            }

            // 기존 평가 결과 + 리포트 링크
            if (existingEval && !canEval) {
                html += `<div class="ace-eval-result-info">`;
                if (existingEval.comment_text) {
                    const typeLabels = { excellent: '🌟 우수', growing: '🌱 성장', support: '💪 보완' };
                    html += `<div class="ace-eval-comment">
                        <span class="ace-comment-label">${typeLabels[existingEval.comment_type] || ''}</span>
                        ${esc(existingEval.comment_text)}
                    </div>`;
                }
                if (existingEval.report_token) {
                    html += `<button class="btn btn-primary ace-send-btn" style="background:#673AB7;" onclick="CoachApp.copyReportLink('${existingEval.report_token}')">
                        📋 리포트 링크 복사
                    </button>`;
                }
                html += `</div>`;
            }

            html += `</div>`;
        });

        container.innerHTML = html;
    }

    async function playAceAudio(recordingId, btnEl) {
        if (acePlayingAudio) {
            acePlayingAudio.pause();
            acePlayingAudio = null;
            document.querySelectorAll('.ace-play-btn.playing').forEach(b => b.classList.remove('playing'));
        }

        btnEl.classList.add('playing');
        try {
            const resp = await fetch('/api/ace.php?action=audio&id=' + recordingId);
            if (!resp.ok) {
                let msg = '재생 실패';
                try { const j = await resp.json(); msg = j.error || msg; } catch(e) {}
                Toast.error(msg);
                btnEl.classList.remove('playing');
                return;
            }
            const blob = await resp.blob();
            const url = URL.createObjectURL(blob);
            const audio = new Audio(url);
            audio.onended = () => { btnEl.classList.remove('playing'); acePlayingAudio = null; URL.revokeObjectURL(url); };
            audio.onerror = () => { btnEl.classList.remove('playing'); Toast.error('재생 실패'); URL.revokeObjectURL(url); };
            audio.play();
            acePlayingAudio = audio;
        } catch (e) {
            btnEl.classList.remove('playing');
            Toast.error('재생 실패');
        }
    }

    function selectAceResult(level, result) {
        const form = document.getElementById('ace-form-' + level);
        if (!form) return;
        form.querySelectorAll('.ace-result-btn').forEach(b => {
            b.classList.toggle('selected', b.dataset.result === result);
        });
        form.dataset.selectedResult = result;
        document.getElementById('ace-comment-' + level)?.classList.remove('hidden');
    }

    function selectCommentType(level, type) {
        const section = document.getElementById('ace-comment-' + level);
        if (!section) return;
        section.querySelectorAll('.ace-comment-type-btn').forEach(b => {
            b.classList.toggle('selected', b.dataset.type === type);
        });
        section.dataset.selectedType = type;

        // 템플릿 표시
        const tmplContainer = document.getElementById('ace-tmpl-' + level);
        const templates = (aceTemplates || []).filter(t => t.comment_type === type);
        if (templates.length > 0) {
            tmplContainer.classList.remove('hidden');
            tmplContainer.innerHTML = templates.map(t => `
                <div class="ace-template-item" onclick="document.getElementById('ace-text-${level}').value = this.textContent.trim()">
                    ${esc(t.template_text)}
                </div>
            `).join('');
        }
    }

    async function submitAceEval(studentId, level) {
        const form = document.getElementById('ace-form-' + level);
        if (!form) return;
        const result = form.dataset.selectedResult;
        if (!result) { Toast.warning('PASS 또는 재도전을 선택해주세요'); return; }

        const commentSection = document.getElementById('ace-comment-' + level);
        const commentType = commentSection?.dataset?.selectedType || null;
        const commentText = document.getElementById('ace-text-' + level)?.value?.trim() || null;

        App.showLoading();
        const res = await App.post('/api/ace.php?action=evaluate', {
            student_id: studentId,
            ace_level: level,
            result: result,
            comment_type: commentType,
            comment_text: commentText,
        });
        App.hideLoading();

        if (res.success) {
            Toast.success(res.message || '평가가 저장되었습니다');
            openAceEval(studentId); // 새로고침
        } else {
            Toast.error(res.error || '평가 저장 실패');
        }
    }

    async function copyReportLink(token) {
        const url = `${location.origin}/ace-report/?token=${token}`;
        try {
            await navigator.clipboard.writeText(url);
            Toast.success('리포트 링크가 복사되었습니다!');
        } catch (e) {
            prompt('리포트 링크:', url);
        }
    }

    // ============================================
    // Bravo 평가
    // ============================================
    async function openBravoEval(studentId) {
        const container = document.getElementById('ace-content');
        container.innerHTML = '<div style="text-align:center; padding:40px;"><div class="loading-spinner"></div></div>';

        const [result, tmplResult] = await Promise.all([
            App.get('/api/bravo.php?action=coach_student_detail&student_id=' + studentId),
            bravoTemplates ? Promise.resolve({ success: true, templates: bravoTemplates }) : App.get('/api/bravo.php?action=comment_templates'),
        ]);

        if (!result.success) {
            container.innerHTML = '<div style="text-align:center; padding:40px; color:#F44336;">' + esc(result.error || '오류') + '</div>';
            return;
        }

        if (tmplResult.success && tmplResult.templates) {
            bravoTemplates = tmplResult.templates;
        }

        const { student, submissions, recordings, answers } = result;

        let html = `
            <div class="ace-eval-header">
                <button class="ace-back-btn" onclick="CoachApp.loadAcePending()">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                </button>
                <div class="ace-eval-student">
                    <div class="ace-eval-avatar" style="background:${student.class_color || '#7C5CFC'}">${esc(student.name).charAt(0)}</div>
                    <div>
                        <div style="font-weight:700; font-size:16px;">${esc(student.name)}</div>
                        <div style="font-size:12px; color:#999;">${esc(student.class_name || '')} · Bravo</div>
                    </div>
                </div>
            </div>
        `;

        // 레벨별 그룹화 (최신 제출만)
        const latestByLevel = {};
        submissions.forEach(sub => {
            const lvl = parseInt(sub.bravo_level);
            if (!latestByLevel[lvl] || parseInt(sub.id) > parseInt(latestByLevel[lvl].id)) {
                latestByLevel[lvl] = sub;
            }
        });

        const levels = Object.keys(latestByLevel).map(Number).sort((a, b) => a - b);
        if (levels.length === 0) {
            html += '<div style="text-align:center; padding:32px; color:#999;">Bravo 제출 기록이 없습니다</div>';
        }

        levels.forEach(level => {
            const sub = latestByLevel[level];
            const subId = sub.id;
            const subRecs = recordings[subId] || [];
            const subAnswers = answers[subId] || [];
            const levelName = BRAVO_LEVEL_NAMES[level] || 'Jr ' + level;
            const isSubmitted = sub.status === 'submitted';
            const isConfirmed = sub.status === 'confirmed';

            html += `<div class="ace-eval-level" data-level="${level}">`;
            html += `<div class="ace-eval-level-header">
                <span class="ace-eval-level-name">Bravo ${levelName}</span>`;
            if (isConfirmed) {
                const badge = sub.coach_result === 'pass'
                    ? '<span class="ace-result-badge pass">PASS ✅</span>'
                    : '<span class="ace-result-badge retry">재도전 🔄</span>';
                html += badge;
            } else if (isSubmitted) {
                html += '<span class="ace-result-badge" style="background:#FFF3E0; color:#E65100;">확인 대기</span>';
            }
            html += `</div>`;

            // ── 퀴즈 결과 ──
            const quizAnswers = subAnswers.filter(a => a.section_type === 'quiz');
            if (quizAnswers.length > 0) {
                html += `<div class="bravo-coach-section">
                    <div class="bravo-coach-section-title">📝 단어 퀴즈 (${sub.quiz_correct}/${sub.quiz_total})</div>
                    <div class="bravo-coach-quiz-list">`;
                quizAnswers.forEach(a => {
                    const data = a.item_data || {};
                    const correct = parseInt(a.is_correct);
                    const icon = correct ? '✅' : '❌';
                    const userAnswer = a.answer_data?.selected || '';
                    html += `<div class="bravo-coach-quiz-item ${correct ? 'correct' : 'wrong'}">
                        <span class="bravo-quiz-icon">${icon}</span>
                        <span class="bravo-quiz-word">${esc(data.w || '')}</span>
                        <span class="bravo-quiz-ipa">${esc(data.ipa || '')}</span>
                        <span class="bravo-quiz-answer">${esc(userAnswer)}</span>
                        ${!correct ? `<span class="bravo-quiz-correct-answer">${esc(data.a || '')}</span>` : ''}
                    </div>`;
                });
                html += `</div></div>`;
            }

            // ── 블록 결과 ──
            const blockAnswers = subAnswers.filter(a => a.section_type === 'block');
            if (blockAnswers.length > 0) {
                html += `<div class="bravo-coach-section">
                    <div class="bravo-coach-section-title">🧱 블록 만들기 (${sub.block_correct}/${sub.block_total})</div>
                    <div class="bravo-coach-quiz-list">`;
                blockAnswers.forEach(a => {
                    const data = a.item_data || {};
                    const correct = parseInt(a.is_correct);
                    const icon = correct ? '✅' : '❌';
                    const userAnswer = (a.answer_data?.selected || []).join(', ');
                    html += `<div class="bravo-coach-quiz-item ${correct ? 'correct' : 'wrong'}">
                        <span class="bravo-quiz-icon">${icon}</span>
                        <span class="bravo-quiz-word">${esc(data.kr || '')}</span>
                        <span class="bravo-quiz-answer">${esc(userAnswer)}</span>
                        ${!correct ? `<span class="bravo-quiz-correct-answer">${esc((data.a || []).join(', '))}</span>` : ''}
                    </div>`;
                });
                html += `</div></div>`;
            }

            // ── 자동 채점 결과 ──
            const quizC = parseInt(sub.quiz_correct) || 0;
            const quizT = parseInt(sub.quiz_total) || 0;
            const blockC = parseInt(sub.block_correct) || 0;
            const blockT = parseInt(sub.block_total) || 0;
            const totalC = quizC + blockC;
            const totalT = quizT + blockT;
            const rate = totalT > 0 ? Math.round(totalC / totalT * 100) : 0;
            const autoPass = sub.auto_result === 'pass';
            html += `<div class="bravo-coach-auto-result" style="background:${autoPass ? '#E8F5E9' : '#FFEBEE'}; padding:12px; border-radius:10px; margin:8px 0;">
                <div style="font-weight:700; color:${autoPass ? '#2E7D32' : '#C62828'};">자동 채점: ${rate}% ${autoPass ? '(PASS)' : '(FAIL)'}</div>
                <div style="font-size:12px; color:#666; margin-top:4px;">퀴즈 ${quizC}/${quizT} + 블록 ${blockC}/${blockT} = ${totalC}/${totalT}</div>
            </div>`;

            // ── 녹음 재생 ──
            const sentenceRecs = subRecs.filter(r => r.section_type === 'sentence');
            const phonicsRecs = subRecs.filter(r => r.section_type === 'phonics');

            if (sentenceRecs.length > 0) {
                html += `<div class="bravo-coach-section">
                    <div class="bravo-coach-section-title">🎤 문장 읽기 (${sentenceRecs.length}개)</div>
                    <div class="ace-eval-items">`;
                sentenceRecs.forEach(r => {
                    const data = r.item_data || {};
                    html += `<div class="ace-eval-item">
                        <div class="ace-eval-item-text">${esc(data.s || '')}</div>
                        <div class="ace-eval-players">
                            <button class="ace-play-btn after" onclick="CoachApp.playBravoAudio(${r.id}, this)">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21"/></svg> 재생
                            </button>
                        </div>
                    </div>`;
                });
                html += `</div></div>`;
            }

            if (phonicsRecs.length > 0) {
                html += `<div class="bravo-coach-section">
                    <div class="bravo-coach-section-title">🔤 파닉스 읽기 (${phonicsRecs.length}개)</div>
                    <div class="ace-eval-items">`;
                phonicsRecs.forEach(r => {
                    const data = r.item_data || {};
                    html += `<div class="ace-eval-item">
                        <div class="ace-eval-item-text">${esc(data.letters || '')} → ${esc(data.word || '')}</div>
                        <div class="ace-eval-players">
                            <button class="ace-play-btn after" onclick="CoachApp.playBravoAudio(${r.id}, this)">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21"/></svg> 재생
                            </button>
                        </div>
                    </div>`;
                });
                html += `</div></div>`;
            }

            // ── 확인 폼 (submitted 상태일 때) ──
            if (isSubmitted) {
                html += `<div class="ace-eval-form" id="bravo-form-${subId}">
                    <div class="ace-eval-form-title">확인</div>
                    <div class="ace-eval-result-btns">
                        <button class="ace-result-btn pass" data-result="pass" onclick="CoachApp.selectBravoResult(${subId}, 'pass')">
                            ✅ PASS
                        </button>
                        <button class="ace-result-btn retry" data-result="retry" onclick="CoachApp.selectBravoResult(${subId}, 'retry')">
                            🔄 재도전
                        </button>
                    </div>
                    <div class="ace-comment-section hidden" id="bravo-comment-${subId}">
                        <div class="ace-comment-type-btns">
                            <button class="ace-comment-type-btn" data-type="excellent" onclick="CoachApp.selectBravoCommentType(${subId}, 'excellent')">🌟 우수</button>
                            <button class="ace-comment-type-btn" data-type="growing" onclick="CoachApp.selectBravoCommentType(${subId}, 'growing')">🌱 성장</button>
                            <button class="ace-comment-type-btn" data-type="support" onclick="CoachApp.selectBravoCommentType(${subId}, 'support')">💪 보완</button>
                        </div>
                        <div class="ace-template-select hidden" id="bravo-tmpl-${subId}"></div>
                        <textarea class="ace-comment-text" id="bravo-text-${subId}" placeholder="코멘트를 입력하거나 위에서 템플릿을 선택하세요..." rows="3"></textarea>
                        <button class="btn btn-primary ace-submit-eval" style="background:#FF9800; width:100%;" onclick="CoachApp.confirmBravo(${subId})">
                            확인 저장
                        </button>
                    </div>
                </div>`;
            }

            // ── 확인 완료 상태에서 리포트 링크 표시 ──
            if (isConfirmed) {
                html += `<div class="ace-eval-result-info">`;
                if (sub.comment_text) {
                    const typeLabels = { excellent: '🌟 우수', growing: '🌱 성장', support: '💪 보완' };
                    html += `<div class="ace-eval-comment">
                        <span class="ace-comment-label">${typeLabels[sub.comment_type] || ''}</span>
                        ${esc(sub.comment_text)}
                    </div>`;
                }
                if (sub.report_token) {
                    html += `<button class="btn btn-primary ace-send-btn" style="background:#FF9800;" onclick="CoachApp.copyBravoReportLink('${sub.report_token}')">
                        📋 리포트 링크 복사
                    </button>`;
                }
                html += `</div>`;
            }

            html += `</div>`;
        });

        container.innerHTML = html;
    }

    async function playBravoAudio(recordingId, btnEl) {
        if (acePlayingAudio) {
            acePlayingAudio.pause();
            acePlayingAudio = null;
            document.querySelectorAll('.ace-play-btn.playing').forEach(b => b.classList.remove('playing'));
        }

        btnEl.classList.add('playing');
        try {
            const resp = await fetch('/api/bravo.php?action=audio&id=' + recordingId);
            if (!resp.ok) {
                let msg = '재생 실패';
                try { const j = await resp.json(); msg = j.error || msg; } catch(e) {}
                Toast.error(msg);
                btnEl.classList.remove('playing');
                return;
            }
            const blob = await resp.blob();
            const url = URL.createObjectURL(blob);
            const audio = new Audio(url);
            audio.onended = () => { btnEl.classList.remove('playing'); acePlayingAudio = null; URL.revokeObjectURL(url); };
            audio.onerror = () => { btnEl.classList.remove('playing'); Toast.error('재생 실패'); URL.revokeObjectURL(url); };
            audio.play();
            acePlayingAudio = audio;
        } catch (e) {
            btnEl.classList.remove('playing');
            Toast.error('재생 실패');
        }
    }

    function selectBravoResult(subId, result) {
        const form = document.getElementById('bravo-form-' + subId);
        if (!form) return;
        form.querySelectorAll('.ace-result-btn').forEach(b => {
            b.classList.toggle('selected', b.dataset.result === result);
        });
        form.dataset.selectedResult = result;
        document.getElementById('bravo-comment-' + subId)?.classList.remove('hidden');
    }

    function selectBravoCommentType(subId, type) {
        const section = document.getElementById('bravo-comment-' + subId);
        if (!section) return;
        section.querySelectorAll('.ace-comment-type-btn').forEach(b => {
            b.classList.toggle('selected', b.dataset.type === type);
        });
        section.dataset.selectedType = type;

        const tmplContainer = document.getElementById('bravo-tmpl-' + subId);
        const templates = (bravoTemplates || []).filter(t => t.comment_type === type);
        if (templates.length > 0) {
            tmplContainer.classList.remove('hidden');
            tmplContainer.innerHTML = templates.map(t => `
                <div class="ace-template-item" onclick="document.getElementById('bravo-text-${subId}').value = this.textContent.trim()">
                    ${esc(t.template_text)}
                </div>
            `).join('');
        }
    }

    async function confirmBravo(submissionId) {
        const form = document.getElementById('bravo-form-' + submissionId);
        if (!form) return;
        const result = form.dataset.selectedResult;
        if (!result) { Toast.warning('PASS 또는 재도전을 선택해주세요'); return; }

        const commentSection = document.getElementById('bravo-comment-' + submissionId);
        const commentType = commentSection?.dataset?.selectedType || null;
        const commentText = document.getElementById('bravo-text-' + submissionId)?.value?.trim() || null;

        const label = result === 'pass' ? 'PASS' : '재도전';
        App.confirm(`${label} 처리하시겠습니까?`, async () => {
            App.showLoading();
            const res = await App.post('/api/bravo.php?action=coach_confirm', {
                submission_id: submissionId,
                result: result,
                comment_type: commentType,
                comment_text: commentText,
            });
            App.hideLoading();

            if (res.success) {
                Toast.success(res.message || '처리 완료');
                loadAcePending();
            } else {
                Toast.error(res.error || '처리 실패');
            }
        }, { formal: true });
    }

    function copyBravoReportLink(token) {
        const url = `${location.origin}/bravo-report/?token=${token}`;
        try {
            navigator.clipboard.writeText(url);
            Toast.success('리포트 링크가 복사되었습니다!');
        } catch (e) {
            prompt('리포트 링크:', url);
        }
    }

    // ============================================
    // 탭7: 메시지 (코치 ↔ 학부모 1:1)
    // ============================================
    let msgCurrentThreadId = null;
    let msgPollingTimer = null;

    async function loadMsgThreads() {
        const container = document.getElementById('msg-content');
        container.innerHTML = '<div style="text-align:center; padding:40px;"><div class="loading-spinner" style="display:inline-block;"></div></div>';

        const result = await App.get('/api/coach.php?action=msg_threads');
        if (!result.success) {
            container.innerHTML = '<div style="text-align:center; padding:40px; color:#F44336;">데이터를 불러올 수 없습니다</div>';
            return;
        }

        if (!result.threads || result.threads.length === 0) {
            container.innerHTML = '<div class="empty-state"><div class="empty-state-text">아직 학부모 메시지가 없습니다</div></div>';
            updateUnreadBadge();
            return;
        }

        container.innerHTML = `
            <div style="display:flex; flex-direction:column; gap:8px;">
                ${result.threads.map(t => {
                    const unread = parseInt(t.unread_count) || 0;
                    const lastMsg = t.last_message || '';
                    const preview = lastMsg.length > 30 ? lastMsg.substring(0, 30) + '...' : lastMsg;
                    const timeStr = t.last_message_at ? formatMsgTime(t.last_message_at) : '';
                    return `
                        <div class="card" style="cursor:pointer; padding:14px; transition:all .15s;" onclick="CoachApp.openMsgThread(${t.thread_id})">
                            <div style="display:flex; align-items:center; gap:12px;">
                                <div style="width:42px; height:42px; border-radius:50%; background:#E3F2FD; color:#1565C0; display:flex; align-items:center; justify-content:center; font-size:16px; font-weight:700; flex-shrink:0;">
                                    ${t.student_name.charAt(0)}
                                </div>
                                <div style="flex:1; min-width:0;">
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <span style="font-weight:700; font-size:14px;">${t.student_name}</span>
                                        <span style="font-size:11px; color:#999;">${t.class_name}</span>
                                    </div>
                                    <div style="font-size:12px; color:#757575; margin-top:2px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${preview || '대화를 시작해 보세요'}</div>
                                </div>
                                <div style="text-align:right; flex-shrink:0;">
                                    <div style="font-size:10px; color:#999;">${timeStr}</div>
                                    ${unread > 0 ? `<div style="background:#F44336; color:#fff; border-radius:12px; padding:2px 8px; font-size:11px; font-weight:700; margin-top:4px;">${unread}건</div>` : ''}
                                </div>
                            </div>
                        </div>
                    `;
                }).join('')}
            </div>
        `;

        // 레드닷도 함께 갱신
        updateUnreadBadge();
    }

    async function openMsgThread(threadId) {
        msgCurrentThreadId = threadId;
        const container = document.getElementById('msg-content');
        container.innerHTML = '<div style="text-align:center; padding:40px;"><div class="loading-spinner" style="display:inline-block;"></div></div>';

        const result = await App.get(`/api/coach.php?action=msg_thread_detail&thread_id=${threadId}`);
        if (!result.success) return;

        const { messages, thread } = result;
        const threadInfo = thread || {};

        container.innerHTML = `
            <div style="margin-bottom:12px;">
                <button class="btn btn-secondary btn-sm" onclick="CoachApp.loadMsgThreads()">\u2190 목록</button>
                <span style="font-weight:700; margin-left:8px;">${threadInfo.student_name || ''}</span>
                <span style="font-size:12px; color:#999; margin-left:4px;">${threadInfo.class_name || ''}</span>
            </div>
            <div id="msg-chat-area" style="max-height:60vh; overflow-y:auto; padding:8px; background:#F5F5F5; border-radius:12px; margin-bottom:12px;">
                ${messages.length === 0 ? '<div style="text-align:center; padding:40px; color:#999;">메시지가 없습니다. 먼저 대화를 시작해 보세요!</div>' :
                    messages.map(m => renderChatBubble(m, 'coach')).join('')}
            </div>
            <div style="display:flex; gap:8px; align-items:flex-end;">
                <label style="cursor:pointer; flex-shrink:0; padding:10px; background:#E3F2FD; border-radius:10px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#1565C0" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                    <input type="file" id="msg-image-input" accept="image/jpeg,image/png,image/webp" style="display:none;">
                </label>
                <div style="flex:1; position:relative;">
                    <textarea id="msg-text-input" placeholder="메시지를 입력하세요" rows="1" style="width:100%; padding:10px 14px; border:1.5px solid #E0E0E0; border-radius:12px; font-size:14px; resize:none; font-family:inherit; box-sizing:border-box;"></textarea>
                    <div id="msg-image-preview" style="display:none; margin-top:4px;"></div>
                </div>
                <button class="btn" id="msg-send-btn" style="background:#2196F3; color:#fff; padding:10px 16px; border-radius:12px; font-weight:700; flex-shrink:0;">전송</button>
            </div>
        `;

        // 스크롤 하단으로
        const chatArea = document.getElementById('msg-chat-area');
        chatArea.scrollTop = chatArea.scrollHeight;

        // 읽음 처리 후 배지 갱신
        updateUnreadBadge();

        // 이미지 미리보기
        document.getElementById('msg-image-input').addEventListener('change', (e) => {
            const file = e.target.files[0];
            const preview = document.getElementById('msg-image-preview');
            if (file) {
                const url = URL.createObjectURL(file);
                preview.style.display = 'block';
                preview.innerHTML = '<div style="display:inline-block; position:relative;"><img src="' + url + '" style="max-height:60px; border-radius:8px;"><button onclick="document.getElementById(\'msg-image-input\').value=\'\'; document.getElementById(\'msg-image-preview\').style.display=\'none\';" style="position:absolute; top:-6px; right:-6px; background:#F44336; color:#fff; border:none; border-radius:50%; width:20px; height:20px; font-size:12px; cursor:pointer; line-height:20px;">&times;</button></div>';
            } else {
                preview.style.display = 'none';
                preview.innerHTML = '';
            }
        });

        // 전송 이벤트
        document.getElementById('msg-send-btn').addEventListener('click', () => sendCoachMsg(threadId));
        document.getElementById('msg-text-input').addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendCoachMsg(threadId); }
        });

        // 폴링 시작
        startMsgPolling(threadId);
    }

    async function sendCoachMsg(threadId) {
        const textEl = document.getElementById('msg-text-input');
        const imageEl = document.getElementById('msg-image-input');
        const body = textEl.value.trim();
        const file = imageEl.files[0];

        if (!body && !file) return;

        const fd = new FormData();
        fd.append('thread_id', threadId);
        fd.append('body', body);
        if (file) fd.append('image', file);

        const result = await App.post('/api/coach.php?action=msg_send', fd);
        if (result.success && result.message) {
            const chatArea = document.getElementById('msg-chat-area');
            // 빈 상태 메시지 제거
            const empty = chatArea.querySelector('.empty-state, [style*="text-align:center"]');
            if (empty && chatArea.children.length === 1) chatArea.innerHTML = '';
            chatArea.insertAdjacentHTML('beforeend', renderChatBubble(result.message, 'coach'));
            chatArea.scrollTop = chatArea.scrollHeight;
            textEl.value = '';
            imageEl.value = '';
            document.getElementById('msg-image-preview').style.display = 'none';
            document.getElementById('msg-image-preview').innerHTML = '';
        }
    }

    function renderChatBubble(msg, myType) {
        const isMine = msg.sender_type === myType;
        const align = isMine ? 'flex-end' : 'flex-start';
        const bgColor = isMine ? '#2196F3' : '#fff';
        const textColor = isMine ? '#fff' : '#333';
        const borderStyle = isMine ? '' : 'border:1px solid #E0E0E0;';
        const time = msg.created_at ? msg.created_at.slice(11, 16) : '';
        const imgHtml = msg.image_path ? `<img src="/api/coach.php?action=msg_image&path=${encodeURIComponent(msg.image_path)}" style="max-width:200px; border-radius:8px; margin-bottom:4px; cursor:pointer; display:block;" onclick="window.open(this.src)">` : '';
        const bodyHtml = msg.body ? `<div style="word-break:break-word;">${escapeHtml(msg.body)}</div>` : '';
        return `
            <div style="display:flex; flex-direction:column; align-items:${align}; margin-bottom:8px;">
                ${!isMine ? `<div style="font-size:11px; color:#999; margin-bottom:2px;">${escapeHtml(msg.sender_name)}</div>` : ''}
                <div style="max-width:75%; padding:10px 14px; border-radius:14px; background:${bgColor}; color:${textColor}; font-size:14px; ${borderStyle}">
                    ${imgHtml}${bodyHtml}
                </div>
                <div style="font-size:10px; color:#BDBDBD; margin-top:2px;">${time}</div>
            </div>
        `;
    }

    function startMsgPolling(threadId) {
        stopMsgPolling();
        msgPollingTimer = setInterval(async () => {
            if (document.hidden || msgCurrentThreadId !== threadId) return;
            const chatArea = document.getElementById('msg-chat-area');
            if (!chatArea) return;
            const lastMsgEl = chatArea.querySelector('[data-msg-id]');
            // 간단한 방법: 전체 새로고침 대신 새 메시지만 확인
            const result = await App.get(`/api/coach.php?action=msg_thread_detail&thread_id=${threadId}&limit=5`, null);
            if (result.success && result.messages) {
                const existingIds = new Set([...chatArea.querySelectorAll('[data-msg-id]')].map(el => el.dataset.msgId));
                const newMsgs = result.messages.filter(m => !existingIds.has(String(m.id)));
                if (newMsgs.length > 0) {
                    newMsgs.forEach(m => {
                        chatArea.insertAdjacentHTML('beforeend', renderChatBubble(m, 'coach').replace('<div style="display:flex', `<div data-msg-id="${m.id}" style="display:flex`));
                    });
                    chatArea.scrollTop = chatArea.scrollHeight;
                }
            }
        }, 15000); // 15초
    }

    function stopMsgPolling() {
        if (msgPollingTimer) { clearInterval(msgPollingTimer); msgPollingTimer = null; }
    }

    // ============================================
    // 안 읽은 메시지 배지 (메시지 탭 버튼)
    // ============================================
    let unreadPollingTimer = null;

    function startUnreadPolling() {
        updateUnreadBadge();
        unreadPollingTimer = setInterval(() => {
            if (!document.hidden) updateUnreadBadge();
        }, 30000); // 30초
    }

    async function updateUnreadBadge() {
        try {
            const result = await App.get('/api/coach.php?action=msg_unread_total');
            if (!result.success) return;
            const total = result.unread_messages || 0;
            const dot = document.getElementById('msg-tab-dot');
            if (dot) {
                dot.style.display = total > 0 ? 'block' : 'none';
            }
        } catch(e) {}
    }

    function formatMsgTime(dateStr) {
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

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ============================================
    // 탭8: 공지사항 (코치 → 학부모)
    // ============================================

    async function loadAnnList() {
        if (!currentClassId) {
            document.getElementById('ann-content').innerHTML = '<div class="empty-state"><div class="empty-state-text">반을 선택해 주세요</div></div>';
            return;
        }

        const container = document.getElementById('ann-content');
        container.innerHTML = '<div style="text-align:center; padding:40px;"><div class="loading-spinner" style="display:inline-block;"></div></div>';

        const result = await App.get('/api/coach.php?action=ann_list&class_id=' + currentClassId);
        if (!result.success) {
            container.innerHTML = '<div style="text-align:center; padding:40px; color:#F44336;">데이터를 불러올 수 없습니다</div>';
            return;
        }

        const parentCount = result.parent_count || 0;
        const anns = result.announcements || [];

        container.innerHTML = `
            <div style="margin-bottom:16px;">
                <button class="btn" onclick="CoachApp.openAnnForm()" style="background:#2196F3; color:#fff; font-weight:700; padding:10px 20px; border-radius:12px;">
                    + 새 공지 작성
                </button>
            </div>
            ${anns.length === 0 ? '<div class="empty-state"><div class="empty-state-text">등록된 공지가 없습니다</div></div>' :
                `<div style="display:flex; flex-direction:column; gap:10px;">
                    ${anns.map(a => {
                        const readCount = parseInt(a.read_count) || 0;
                        const pinBadge = parseInt(a.is_pinned) ? '<span style="background:#FF9800; color:#fff; padding:1px 6px; border-radius:6px; font-size:10px; font-weight:700; margin-left:6px;">고정</span>' : '';
                        return `
                            <div class="card" style="padding:14px;">
                                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:6px;">
                                    <div style="font-weight:700; font-size:15px;">${escapeHtml(a.title)}${pinBadge}</div>
                                    <button onclick="CoachApp.deleteAnn(${a.id})" style="background:none; border:none; color:#F44336; font-size:12px; cursor:pointer; padding:4px 8px;">삭제</button>
                                </div>
                                <div style="font-size:13px; color:#555; margin-bottom:8px; white-space:pre-wrap;">${escapeHtml(a.body)}</div>
                                ${a.image_path ? `<img src="/api/coach.php?action=ann_image&path=${encodeURIComponent(a.image_path)}" style="max-width:100%; border-radius:8px; margin-bottom:8px; cursor:pointer;" onclick="window.open(this.src)">` : ''}
                                <div style="display:flex; align-items:center; justify-content:space-between; font-size:11px; color:#999;">
                                    <span>${a.created_at ? a.created_at.slice(0, 16) : ''}</span>
                                    <span style="cursor:pointer; color:#2196F3;" onclick="CoachApp.viewAnnReads(${a.id})">${readCount}/${parentCount}명 읽음</span>
                                </div>
                            </div>
                        `;
                    }).join('')}
                </div>`
            }
        `;
    }

    function openAnnForm() {
        if (!currentClassId) { Toast.warning('반을 먼저 선택해 주세요'); return; }
        const container = document.getElementById('ann-content');
        container.innerHTML = `
            <div style="margin-bottom:12px;">
                <button class="btn btn-secondary btn-sm" onclick="CoachApp.loadAnnList()">\u2190 목록</button>
            </div>
            <div class="card" style="padding:20px;">
                <div style="font-size:18px; font-weight:700; margin-bottom:16px;">새 공지 작성</div>
                <div class="form-group" style="margin-bottom:12px;">
                    <label class="form-label">제목</label>
                    <input type="text" id="ann-title" class="form-input" placeholder="공지 제목" maxlength="200">
                </div>
                <div class="form-group" style="margin-bottom:12px;">
                    <label class="form-label">내용</label>
                    <textarea id="ann-body" class="form-input" placeholder="공지 내용을 입력하세요" rows="5" style="resize:vertical;"></textarea>
                </div>
                <div class="form-group" style="margin-bottom:12px;">
                    <label class="form-label">이미지 (선택)</label>
                    <input type="file" id="ann-image" accept="image/jpeg,image/png,image/webp" class="form-input">
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:flex; align-items:center; gap:8px; font-size:14px; cursor:pointer;">
                        <input type="checkbox" id="ann-pinned"> 상단 고정
                    </label>
                </div>
                <button class="btn btn-primary btn-block" onclick="CoachApp.submitAnn()" style="background:#2196F3;">공지 등록</button>
            </div>
        `;
    }

    async function submitAnn() {
        const title = document.getElementById('ann-title').value.trim();
        const body = document.getElementById('ann-body').value.trim();
        const imageFile = document.getElementById('ann-image').files[0];
        const isPinned = document.getElementById('ann-pinned').checked ? 1 : 0;

        if (!title) { Toast.warning('제목을 입력해 주세요'); return; }
        if (!body) { Toast.warning('내용을 입력해 주세요'); return; }

        const fd = new FormData();
        fd.append('class_id', currentClassId);
        fd.append('title', title);
        fd.append('body', body);
        fd.append('is_pinned', isPinned);
        if (imageFile) fd.append('image', imageFile);

        App.showLoading();
        const result = await App.post('/api/coach.php?action=ann_create', fd);
        App.hideLoading();

        if (result.success) {
            Toast.success('공지가 등록되었습니다');
            loadAnnList();
        }
    }

    async function deleteAnn(annId) {
        App.confirm('이 공지를 삭제하시겠습니까?', async () => {
            const result = await App.post('/api/coach.php?action=ann_delete', { announcement_id: annId });
            if (result.success) {
                Toast.success('공지가 삭제되었습니다');
                loadAnnList();
            }
        }, { formal: true });
    }

    async function viewAnnReads(annId) {
        const result = await App.get('/api/coach.php?action=ann_read_status&announcement_id=' + annId);
        if (!result.success) return;

        const readList = (result.read || []).map(r => `<div style="padding:6px 0; border-bottom:1px solid #f0f0f0; font-size:13px;">📱 ${r.parent_phone.slice(-4)} <span style="color:#999; font-size:11px; margin-left:8px;">${r.read_at ? r.read_at.slice(0,16) : ''}</span></div>`).join('');
        const unreadList = (result.unread || []).map(u => `<div style="padding:6px 0; border-bottom:1px solid #f0f0f0; font-size:13px; color:#999;">${u.student_name} 부모님 (미확인)</div>`).join('');

        App.openModal(`읽음 현황 (${result.read_count}/${result.total}명)`, `
            ${readList ? `<div style="margin-bottom:12px;"><div style="font-weight:600; color:#4CAF50; margin-bottom:4px;">확인한 학부모</div>${readList}</div>` : ''}
            ${unreadList ? `<div><div style="font-weight:600; color:#F44336; margin-bottom:4px;">미확인 학부모</div>${unreadList}</div>` : ''}
        `);
    }

    // ============================================
    // 시작
    // ============================================
    document.addEventListener('DOMContentLoaded', init);

    return {
        init, changeAttendance: manualAttendance, adjustReward, showCardDates, selectProfileStudent, openAceFromProfile,
        manualAttendance, givePostureCard,
        // ACE exports
        loadAcePending, openAceEval, playAceAudio, filterAce, selectAceResult,
        selectCommentType, submitAceEval, copyReportLink,
        // Bravo exports
        openBravoEval, playBravoAudio, confirmBravo,
        selectBravoResult, selectBravoCommentType, copyBravoReportLink,
        // Homework exports
        showHwHelp, hwNav,
        // Message exports
        loadMsgThreads, openMsgThread, sendCoachMsg,
        // Announcement exports
        loadAnnList, openAnnForm, submitAnn, deleteAnn, viewAnnReads,
    };
})();
