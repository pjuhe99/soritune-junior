/**
 * 소리튠 주니어 - ACE 학생 녹음 페이지
 */
const AceStudentApp = (() => {
    let submissions = [];    // 현재 세션의 submission 목록
    let items = [];          // 녹음할 항목 목록
    let recordings = {};     // item_id -> { blob, url, uploaded }
    let currentItemIdx = 0;  // 현재 녹음 중인 항목 인덱스
    let recordingTimer = null;
    let recordingSeconds = 0;
    let statusData = null;
    let playingAudio = null;
    let activeSection = null; // 'ace' or 'bravo'

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    // ============================================
    // 초기화
    // ============================================
    async function init() {
        // 세션 확인
        const session = await App.api('/api/student.php?action=check_session', { showError: false });
        if (!session.logged_in) {
            document.getElementById('view-main').innerHTML = `
                <div style="text-align:center; padding:60px 20px;">
                    <div style="font-size:48px; margin-bottom:16px;">🔒</div>
                    <div style="font-size:18px; font-weight:700; color:#333; margin-bottom:8px;">로그인이 필요합니다</div>
                    <div style="font-size:14px; color:#999; margin-bottom:24px;">ACE 도전을 하려면 먼저 로그인해주세요</div>
                    <a href="/login.php" class="ace-btn ace-btn-primary" style="display:inline-block; text-decoration:none;">로그인하기</a>
                </div>
            `;
            return;
        }

        await loadStatus();
    }

    // ============================================
    // 상태 로드 → 대시보드 표시
    // ============================================
    async function loadStatus() {
        App.showLoading();
        const result = await App.get('/api/ace.php?action=student_status');
        App.hideLoading();

        if (!result.success) {
            Toast.error('데이터를 불러올 수 없습니다');
            return;
        }

        statusData = result;
        checkTestDateBlock();
        renderDashboard();
    }

    function checkTestDateBlock() {
        const overlay = document.getElementById('test-disabled-overlay');
        const info = document.getElementById('test-date-info');
        if (!overlay || !statusData?.test_dates) return;

        // 현재 학생의 ACE/Bravo 테스트 타입 결정
        // Before 녹음 (level === null)은 기간 제한 없이 허용
        const aceLevel = statusData.current_level;
        if (aceLevel === null) {
            overlay.style.display = 'none';
            return;
        }
        const aceCompleted = aceLevel >= 4;
        const bravoLevel = aceCompleted ? (statusData.bravo_current_level || 1) : 0;

        let testType = null;
        if (!aceCompleted && aceLevel >= 1 && aceLevel <= 3) {
            testType = 'ace_' + aceLevel;
        } else if (aceCompleted && bravoLevel >= 1 && bravoLevel <= 6) {
            testType = 'bravo_' + bravoLevel;
        }

        if (!testType || !statusData.test_dates[testType]) {
            overlay.style.display = 'none';
            return;
        }

        const td = statusData.test_dates[testType];
        const startDate = td.start_date;
        const endDate = td.end_date;

        // 둘 중 하나라도 없으면 → 차단
        if (!startDate || !endDate) {
            overlay.style.display = 'flex';
            return;
        }

        const today = new Date().toISOString().slice(0, 10);
        const blocked = today < startDate || today > endDate;

        if (!blocked) {
            overlay.style.display = 'none';
            return;
        }

        // 날짜 정보 표시
        const fmt = (d) => { const p = d.split('-'); return parseInt(p[1]) + '월 ' + parseInt(p[2]) + '일'; };
        const dateText = '테스트 가능 기간: ' + fmt(startDate) + ' ~ ' + fmt(endDate);

        if (info && dateText) {
            info.textContent = dateText;
            info.style.display = 'block';
        }
        overlay.style.display = 'flex';
    }

    function renderDashboard() {
        const container = document.getElementById('view-main');
        const level = statusData.current_level;
        const evals = statusData.evaluations || [];
        const awaitingEval = statusData.awaiting_evaluation;
        const beforeCompleted = statusData.before_completed;

        const aceCompleted = level >= 4;
        const bravoLevel = aceCompleted ? (statusData.bravo_current_level || 1) : 0;
        const bravoStatusMap = statusData.bravo_level_status || {};
        const bravoAwaiting = aceCompleted && Object.values(bravoStatusMap).some(s => s.status === 'submitted');

        // 디폴트 섹션
        if (!activeSection) {
            activeSection = aceCompleted ? 'bravo' : 'ace';
        }

        // 토글 뱃지
        const passedLevels = new Set(evals.filter(e => e.result === 'pass').map(e => parseInt(e.ace_level)));
        const aceBadge = aceCompleted ? ' ✅' : ` ${passedLevels.size}/3`;
        const bravoPassedCount = aceCompleted
            ? Object.values(bravoStatusMap).filter(s => s.coach_result === 'pass').length : 0;
        const bravoBadge = !aceCompleted ? ' 🔒' : ` ${bravoPassedCount}/6`;

        // ── 공통 히어로 ──
        let html = `
            <div class="ace-dashboard">
                <div class="ace-hero">
                    <div class="ace-hero-icon">🎤</div>
                    <h2 class="ace-hero-title">ACE/BRAVO Challenge</h2>
                    <p class="ace-hero-desc">영어 소리 성장 인증 시험</p>
                </div>
                <div class="ace-section-toggle tabs" id="ace-section-tabs">
                    <button class="tab-btn${activeSection === 'ace' ? ' active' : ''}" data-tab="ace">ACE${aceBadge}</button>
                    <button class="tab-btn${activeSection === 'bravo' ? ' active' : ''}" data-tab="bravo">BRAVO${bravoBadge}</button>
                </div>
        `;

        // ── ACE 탭 ──
        html += `<div id="tab-ace" class="tab-content${activeSection === 'ace' ? ' active' : ''}">`;

        // ACE 액션 영역
        if (awaitingEval) {
            html += `
                <div class="ace-action">
                    <div class="ace-waiting">
                        <div style="font-size:48px; margin-bottom:12px;">⏳</div>
                        <div style="font-size:18px; font-weight:800; color:#FF9800;">평가를 기다리고 있어요!</div>
                        <div style="font-size:14px; color:#999; margin-top:8px;">코치 선생님이 소리를 듣고 있어요.<br>평가가 끝나면 다시 도전할 수 있습니다.</div>
                    </div>
                </div>`;
        } else if (level === null) {
            html += `
                <div class="ace-action">
                    <button class="ace-btn ace-btn-primary ace-btn-lg" id="btn-start-ace">
                        🎤 Before 녹음 시작하기
                    </button>
                    <p class="ace-action-hint">ACE1 단어 5개를 녹음합니다</p>
                </div>`;
        } else if (level < 4) {
            const levelNames = { 1: 'ACE1', 2: 'ACE2', 3: 'ACE3' };
            const nextItems = level < 3
                ? `${levelNames[level]} 단어 + ${levelNames[level+1]} 단어 (보너스)`
                : `${levelNames[level]} 문장`;
            html += `
                <div class="ace-action">
                    <button class="ace-btn ace-btn-primary ace-btn-lg" id="btn-start-ace">
                        🎤 ${levelNames[level]} 도전하기
                    </button>
                    <p class="ace-action-hint">${nextItems}를 녹음합니다</p>
                </div>`;
        }

        // ACE 레벨 카드
        const levelInfo = [
            { level: 1, name: 'ACE 1', desc: '1음절 단어 5개', icon: '🔤' },
            { level: 2, name: 'ACE 2', desc: '긴 단어 5개', icon: '📝' },
            { level: 3, name: 'ACE 3', desc: '문장 3개', icon: '💬' },
        ];

        html += `<div class="ace-levels">`;

        // Before 녹음 카드
        if (beforeCompleted) {
            html += `
                <div class="ace-level-card passed">
                    <div class="ace-level-icon">🎵</div>
                    <div class="ace-level-info">
                        <div class="ace-level-name">Before 녹음</div>
                        <div class="ace-level-desc">입학 소리 기록</div>
                    </div>
                    <div class="ace-level-badge passed">완료 ✅</div>
                </div>`;
        } else {
            html += `
                <div class="ace-level-card current">
                    <div class="ace-level-icon">🎵</div>
                    <div class="ace-level-info">
                        <div class="ace-level-name">Before 녹음</div>
                        <div class="ace-level-desc">입학 소리 기록</div>
                    </div>
                    <div class="ace-level-badge current">도전 가능</div>
                </div>`;
        }

        levelInfo.forEach(li => {
            const passed = passedLevels.has(li.level);
            const isCurrent = level === li.level && beforeCompleted;
            const isLocked = !beforeCompleted || (level === null ? true : li.level > level);
            const isComplete = level >= 4;

            let statusBadge, statusClass;
            if (passed) {
                statusBadge = 'PASS ✅'; statusClass = 'passed';
            } else if (isCurrent) {
                statusBadge = '도전 가능'; statusClass = 'current';
            } else if (isLocked && !isComplete) {
                statusBadge = '🔒'; statusClass = 'locked';
            } else {
                statusBadge = '대기'; statusClass = 'waiting';
            }

            html += `
                <div class="ace-level-card ${statusClass}">
                    <div class="ace-level-icon">${li.icon}</div>
                    <div class="ace-level-info">
                        <div class="ace-level-name">${li.name}</div>
                        <div class="ace-level-desc">${li.desc}</div>
                    </div>
                    <div class="ace-level-badge ${statusClass}">${statusBadge}</div>
                </div>`;
        });

        html += `</div></div>`; // close ace-levels + tab-ace

        // ── BRAVO 탭 ──
        html += `<div id="tab-bravo" class="tab-content${activeSection === 'bravo' ? ' active' : ''}">`;

        // Bravo 액션 영역
        if (bravoAwaiting) {
            html += `
                <div class="ace-action">
                    <div class="ace-waiting">
                        <div style="font-size:48px; margin-bottom:12px;">⏳</div>
                        <div style="font-size:18px; font-weight:800; color:#FF9800;">Bravo 평가를 기다리고 있어요!</div>
                        <div style="font-size:14px; color:#999; margin-top:8px;">코치 선생님이 확인하고 있어요.<br>평가가 끝나면 다음 레벨에 도전할 수 있습니다.</div>
                    </div>
                </div>`;
        } else if (aceCompleted && bravoLevel <= 6) {
            const bravoLs = bravoStatusMap[bravoLevel];
            const bravoSubmitted = bravoLs && bravoLs.status === 'submitted';
            if (!bravoSubmitted) {
                html += `
                    <div class="ace-action">
                        <button class="ace-btn ace-btn-primary ace-btn-lg" onclick="BravoApp.startFromAce(${bravoLevel})">
                            🏆 Bravo Jr ${bravoLevel} 도전하기
                        </button>
                        <p class="ace-action-hint">Bravo Jr ${bravoLevel} 테스트를 시작합니다</p>
                    </div>`;
            }
        } else if (!aceCompleted) {
            html += `
                <div class="ace-action">
                    <div class="ace-waiting">
                        <div style="font-size:48px; margin-bottom:12px;">🔒</div>
                        <div style="font-size:18px; font-weight:800; color:#999;">ACE 3를 통과하면 열려요!</div>
                        <div style="font-size:14px; color:#999; margin-top:8px;">ACE 도전을 먼저 완료해 주세요.</div>
                    </div>
                </div>`;
        }

        // Bravo 레벨 카드
        const bravoLevels = [
            { lv: 1, name: 'Bravo Jr 1', desc: 'Level aa · 파닉스 마스터', color: '#F59E0B' },
            { lv: 2, name: 'Bravo Jr 2', desc: 'Level a · 소리블록 기초', color: '#FB923C' },
            { lv: 3, name: 'Bravo Jr 3', desc: 'Level b · 소리블록 확장', color: '#EA580C' },
            { lv: 4, name: 'Bravo Jr 4', desc: 'Level C · 기초 문장 패턴', color: '#10B981' },
            { lv: 5, name: 'Bravo Jr 5', desc: 'Level D · 복합 문장 패턴', color: '#059669' },
            { lv: 6, name: 'Bravo Jr 6', desc: 'Level E · 스토리 & 표현', color: '#047857' },
        ];

        html += `<div class="ace-levels">`;

        for (const bl of bravoLevels) {
            const ls = bravoStatusMap[bl.lv];
            const isPassed = aceCompleted && bl.lv < bravoLevel;
            const isSubmitted = aceCompleted && ls && ls.status === 'submitted';
            const isAvailable = aceCompleted && bl.lv === bravoLevel && !isSubmitted;

            let badge, cls, clickAttr = '';
            if (isPassed) {
                badge = 'PASS ✅'; cls = 'passed';
            } else if (isSubmitted) {
                badge = '확인 대기 ⏳'; cls = 'waiting';
            } else if (isAvailable) {
                badge = '도전 가능'; cls = 'current';
                clickAttr = `onclick="BravoApp.startFromAce(${bl.lv})" style="cursor:pointer;"`;
            } else {
                badge = '🔒'; cls = 'locked';
            }

            html += `
                <div class="ace-level-card ${cls}" ${clickAttr}>
                    <div class="ace-level-icon" style="background:${bl.color};color:#fff;border-radius:10px;width:36px;height:36px;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:800;">${bl.lv}</div>
                    <div class="ace-level-info">
                        <div class="ace-level-name">${bl.name}</div>
                        <div class="ace-level-desc">${bl.desc}</div>
                    </div>
                    <div class="ace-level-badge ${cls}">${badge}</div>
                </div>`;
        }

        html += `</div></div>`; // close ace-levels + tab-bravo

        html += `
            <div style="text-align:center; margin-top:20px;">
                <a href="/" class="ace-link">← 메인으로 돌아가기</a>
            </div>
        </div>`;

        container.innerHTML = html;

        // 토글 이벤트 바인딩
        const tabContainer = document.getElementById('ace-section-tabs');
        if (tabContainer) {
            tabContainer.querySelectorAll('.tab-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    activeSection = btn.dataset.tab;
                    tabContainer.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    document.getElementById('tab-ace').classList.toggle('active', activeSection === 'ace');
                    document.getElementById('tab-bravo').classList.toggle('active', activeSection === 'bravo');
                });
            });
        }

        // ACE 시작 버튼
        const btnStart = document.getElementById('btn-start-ace');
        if (btnStart) btnStart.addEventListener('click', startSession);
    }

    // ============================================
    // 녹음 세션 시작
    // ============================================
    async function startSession() {
        // 마이크 권한 먼저 요청
        try {
            await AceRecorder.requestMic();
        } catch (e) {
            Toast.error('마이크 접근 권한이 필요합니다. 브라우저 설정에서 마이크를 허용해주세요.');
            return;
        }

        App.showLoading();
        const result = await App.post('/api/ace.php?action=start_session', {});
        App.hideLoading();

        if (!result.success) {
            Toast.error(result.error || '세션 시작에 실패했습니다');
            return;
        }

        submissions = result.submissions;
        items = result.items;
        recordings = {};
        currentItemIdx = 0;

        renderRecordingView();
    }

    // ============================================
    // 녹음 화면
    // ============================================
    function renderRecordingView() {
        const container = document.getElementById('view-main');
        const total = items.length;

        // 현재 항목 그룹화: ACE 레벨별 구분
        const groups = [];
        let lastLevel = 0;
        items.forEach((item, idx) => {
            if (item.ace_level !== lastLevel) {
                groups.push({ level: item.ace_level, startIdx: idx, items: [] });
                lastLevel = item.ace_level;
            }
            groups[groups.length - 1].items.push({ ...item, globalIdx: idx });
        });

        // 현재 항목
        const item = items[currentItemIdx];
        const isWord = item.item_type === 'word';
        const rec = recordings[item.id];
        const isRecorded = rec && rec.uploaded;

        // 진행률
        const recordedCount = Object.values(recordings).filter(r => r.uploaded).length;

        // 현재 그룹 정보
        let currentGroup = null;
        let localIdx = 0;
        for (const g of groups) {
            const found = g.items.find(gi => gi.globalIdx === currentItemIdx);
            if (found) {
                currentGroup = g;
                localIdx = g.items.indexOf(found);
                break;
            }
        }

        const subForLevel = submissions.find(s => parseInt(s.ace_level) === parseInt(item.ace_level));
        const roleLabel = subForLevel && subForLevel.role === 'before' ? 'Before 녹음' : 'After 녹음';
        const roleBadgeClass = subForLevel && subForLevel.role === 'before' ? 'before' : 'after';

        html = `
            <div class="ace-recording-view">
                <!-- 상단 진행바 -->
                <div class="ace-progress-bar">
                    <div class="ace-progress-fill" style="width:${(recordedCount / total) * 100}%"></div>
                </div>
                <div class="ace-progress-text">${recordedCount} / ${total} 완료</div>

                <!-- 레벨 뱃지 -->
                <div class="ace-level-badge-bar">
                    <span class="ace-role-badge ${roleBadgeClass}">${roleLabel}</span>
                    <span class="ace-level-label">ACE ${item.ace_level}</span>
                    <span class="ace-item-counter">${localIdx + 1} / ${currentGroup ? currentGroup.items.length : total}</span>
                </div>

                <!-- 단어/문장 표시 -->
                <div class="ace-word-card ${isWord ? 'word' : 'sentence'}">
                    <div class="ace-word-text">${esc(item.item_text)}</div>
                    ${item.item_ipa ? `<div class="ace-word-ipa">${esc(item.item_ipa)}</div>` : ''}
                </div>

                <!-- 녹음 영역 -->
                <div class="ace-record-area">
                    ${isRecorded ? `
                        <div class="ace-recorded-badge">✅ 녹음 완료</div>
                        <button class="ace-btn-icon ace-btn-play" id="btn-play">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                            다시 듣기
                        </button>
                        <button class="ace-btn ace-btn-outline ace-btn-sm" id="btn-rerecord">다시 녹음</button>
                    ` : `
                        <button class="ace-record-btn" id="btn-record">
                            <div class="ace-record-btn-inner">
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="currentColor"><path d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3z"/><path d="M17 11c0 2.76-2.24 5-5 5s-5-2.24-5-5H5c0 3.53 2.61 6.43 6 6.92V21h2v-3.08c3.39-.49 6-3.39 6-6.92h-2z"/></svg>
                            </div>
                            <span>탭하여 녹음</span>
                        </button>
                    `}
                    <div class="ace-timer hidden" id="ace-timer">
                        <div class="ace-timer-dot"></div>
                        <span id="ace-timer-text">0:00</span>
                    </div>
                </div>

                <!-- 하단 네비게이션 -->
                <div class="ace-nav-bar">
                    <button class="ace-btn ace-btn-outline" id="btn-prev" ${currentItemIdx === 0 ? 'disabled' : ''}>
                        ◀ 이전
                    </button>
                    ${recordedCount >= total ? `
                        <button class="ace-btn ace-btn-primary ace-btn-lg" id="btn-submit-all">
                            제출하기 🚀
                        </button>
                    ` : `
                        <button class="ace-btn ace-btn-primary" id="btn-next" ${currentItemIdx >= total - 1 || !isRecorded ? 'disabled' : ''}>
                            다음 ▶
                        </button>
                    `}
                </div>
            </div>
        `;

        container.innerHTML = html;
        bindRecordingEvents();
    }

    function bindRecordingEvents() {
        const btnRecord = document.getElementById('btn-record');
        const btnPlay = document.getElementById('btn-play');
        const btnRerecord = document.getElementById('btn-rerecord');
        const btnPrev = document.getElementById('btn-prev');
        const btnNext = document.getElementById('btn-next');
        const btnSubmit = document.getElementById('btn-submit-all');

        if (btnRecord) btnRecord.addEventListener('click', toggleRecording);
        if (btnPlay) btnPlay.addEventListener('click', playRecording);
        if (btnRerecord) btnRerecord.addEventListener('click', reRecord);
        if (btnPrev) btnPrev.addEventListener('click', () => navigate(-1));
        if (btnNext) btnNext.addEventListener('click', () => {
            const item = items[currentItemIdx];
            const rec = recordings[item.id];
            if (!rec || !rec.uploaded) {
                Toast.warning('녹음을 완료해줘!');
                return;
            }
            navigate(1);
        });
        if (btnSubmit) btnSubmit.addEventListener('click', submitAll);
    }

    // ============================================
    // 다시 녹음: 기존 녹음 삭제 후 미녹음 상태로 전환
    // ============================================
    function reRecord() {
        const item = items[currentItemIdx];
        delete recordings[item.id];
        renderRecordingView();
    }

    // ============================================
    // 녹음 시작/정지
    // ============================================
    async function toggleRecording() {
        if (AceRecorder.isRecording()) {
            // 정지
            try {
                const blob = await AceRecorder.stop();
                clearInterval(recordingTimer);
                document.getElementById('ace-timer')?.classList.add('hidden');

                // 녹음 데이터 최소 크기 검증
                if (blob.size < 1000) {
                    Toast.error('녹음이 제대로 되지 않았습니다. 다시 시도해주세요.');
                    renderRecordingView();
                    return;
                }

                const item = items[currentItemIdx];
                const url = URL.createObjectURL(blob);
                recordings[item.id] = { blob, url, uploaded: false };

                // 업로드
                await uploadRecording(item.id, blob);
            } catch (e) {
                Toast.error('녹음 저장에 실패했습니다');
            }
        } else {
            // 시작
            try {
                await AceRecorder.start();
                recordingSeconds = 0;
                const timerEl = document.getElementById('ace-timer');
                const timerText = document.getElementById('ace-timer-text');
                if (timerEl) timerEl.classList.remove('hidden');

                // 녹음 중 UI 변경
                const btnRecord = document.getElementById('btn-record');
                if (btnRecord) {
                    btnRecord.classList.add('recording');
                    btnRecord.querySelector('span').textContent = '탭하여 정지';
                }

                recordingTimer = setInterval(() => {
                    recordingSeconds++;
                    const m = Math.floor(recordingSeconds / 60);
                    const s = recordingSeconds % 60;
                    if (timerText) timerText.textContent = `${m}:${String(s).padStart(2, '0')}`;

                    // 최대 30초
                    if (recordingSeconds >= 30) {
                        toggleRecording();
                    }
                }, 1000);

            } catch (e) {
                Toast.error('녹음을 시작할 수 없습니다');
            }
        }
    }

    async function uploadRecording(itemId, blob) {
        const item = items[currentItemIdx];
        const sub = submissions.find(s => parseInt(s.ace_level) === parseInt(item.ace_level));
        if (!sub) { Toast.error('submission을 찾을 수 없습니다'); return; }

        const formData = new FormData();
        formData.append('submission_id', sub.id);
        formData.append('item_id', itemId);
        formData.append('audio', blob, 'recording.' + (AceRecorder.getMimeType().includes('webm') ? 'webm' : 'ogg'));

        try {
            const resp = await fetch('/api/ace.php?action=upload_audio', {
                method: 'POST',
                body: formData,
            });
            const result = await resp.json();

            if (result.success) {
                recordings[itemId].uploaded = true;
                recordings[itemId].recordingId = result.recording_id;
                renderRecordingView();
            } else {
                Toast.error(result.error || '업로드 실패');
            }
        } catch (e) {
            Toast.error('네트워크 오류');
        }
    }

    async function playRecording() {
        const item = items[currentItemIdx];
        const rec = recordings[item.id];
        if (!rec) return;

        if (playingAudio) {
            playingAudio.pause();
            playingAudio = null;
        }

        const btnPlay = document.getElementById('btn-play');
        if (btnPlay) btnPlay.classList.add('playing');

        // 재생할 blob 결정: 로컬 blob → 서버에서 fetch
        let audioBlob = rec.blob;
        if (!audioBlob || audioBlob.size < 100) {
            // blob이 없거나 너무 작으면 서버에서 가져오기
            if (rec.recordingId) {
                try {
                    const resp = await fetch('/api/ace.php?action=audio&id=' + rec.recordingId);
                    if (resp.ok) audioBlob = await resp.blob();
                } catch (e) { /* 서버 폴백 실패 */ }
            }
        }

        if (!audioBlob || audioBlob.size < 100) {
            if (btnPlay) btnPlay.classList.remove('playing');
            Toast.error('재생에 실패했습니다');
            return;
        }

        const url = URL.createObjectURL(audioBlob);
        playingAudio = new Audio(url);
        playingAudio.onended = () => {
            if (btnPlay) btnPlay.classList.remove('playing');
            URL.revokeObjectURL(url);
            playingAudio = null;
        };
        playingAudio.onerror = () => {
            if (btnPlay) btnPlay.classList.remove('playing');
            URL.revokeObjectURL(url);
            Toast.error('재생에 실패했습니다');
            playingAudio = null;
        };
        playingAudio.play().catch(() => {});
    }

    function navigate(dir) {
        if (AceRecorder.isRecording()) {
            Toast.warning('녹음을 먼저 정지해주세요');
            return;
        }
        const newIdx = currentItemIdx + dir;
        if (newIdx >= 0 && newIdx < items.length) {
            currentItemIdx = newIdx;
            renderRecordingView();
        }
    }

    // ============================================
    // 제출
    // ============================================
    async function submitAll() {
        const total = items.length;
        const recorded = Object.values(recordings).filter(r => r.uploaded).length;
        if (recorded < total) {
            Toast.warning(`아직 ${total - recorded}개의 녹음이 남았습니다`);
            return;
        }

        App.showLoading();
        const subIds = submissions.map(s => s.id);
        const result = await App.post('/api/ace.php?action=submit', { submission_ids: subIds });
        App.hideLoading();

        if (!result.success) {
            Toast.error(result.error || '제출에 실패했습니다');
            return;
        }

        // 축하 화면
        renderCelebration(result);
    }

    function renderCelebration(result) {
        AceRecorder.cleanup();
        const container = document.getElementById('view-main');
        const coinsAwarded = result.coins_awarded || 0;

        const coinHtml = coinsAwarded > 0
            ? `<div class="ace-coin-drop">
                    <div class="ace-coin-icon">🪙</div>
                    <div class="ace-coin-text">+${coinsAwarded} 코인!</div>
                </div>`
            : '';
        const subText = coinsAwarded > 0
            ? '코치 선생님이 소리를 들어볼 거야!'
            : '코치 선생님이 평가하면 코인을 받을 수 있어!';

        container.innerHTML = `
            <div class="ace-celebration">
                <div class="ace-confetti" id="confetti-container"></div>
                <div class="ace-celebration-content">
                    ${coinHtml}
                    <div class="ace-celebration-title">🎉 녹음 제출 완료!</div>
                    <div class="ace-celebration-sub">${subText}</div>
                    <button class="ace-btn ace-btn-primary ace-btn-lg" id="btn-back-dashboard">
                        확인
                    </button>
                </div>
            </div>
        `;

        // 컨페티 생성
        createConfetti();

        document.getElementById('btn-back-dashboard')?.addEventListener('click', () => {
            loadStatus();
        });
    }

    function createConfetti() {
        const container = document.getElementById('confetti-container');
        if (!container) return;
        const colors = ['#FF6B6B', '#4ECDC4', '#45B7D1', '#FFD93D', '#6BCB77', '#FF8E53'];
        for (let i = 0; i < 50; i++) {
            const confetti = document.createElement('div');
            confetti.className = 'confetti-piece';
            confetti.style.cssText = `
                left: ${Math.random() * 100}%;
                background: ${colors[Math.floor(Math.random() * colors.length)]};
                animation-delay: ${Math.random() * 2}s;
                animation-duration: ${2 + Math.random() * 2}s;
            `;
            container.appendChild(confetti);
        }
    }

    // ============================================
    // 시작
    // ============================================
    document.addEventListener('DOMContentLoaded', init);

    // Bravo에서 돌아올 때 대시보드 다시 로드
    async function reloadDashboard() {
        await loadStatus();
    }

    return { init, reloadDashboard };
})();
