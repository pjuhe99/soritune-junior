/**
 * 소리튠 주니어 - Bravo 테스트 학생 UI
 */
const BravoApp = (() => {
    // 상태
    let statusData = null;
    let submissionId = null;
    let currentLevel = 0;
    let items = [];       // API에서 받은 문항
    let meta = null;      // 레벨 메타
    let part = 0;         // 0=quiz, 1=sentence, 2=block/phonics
    let qi = 0;           // 현재 문항 인덱스
    let answers = {};     // {itemId: answer}
    let recordings = {};  // {itemId: blobUrl}
    let autoTimer = null;
    let recStartTime = 0;
    let embeddedMode = false; // ACE 페이지에서 로드됨

    const container = () => document.getElementById('view-main');

    // ========================================
    // 초기화
    // ========================================
    async function init() {
        const sessionResult = await App.get('/api/student.php?action=check_session');
        if (!sessionResult.logged_in) {
            window.location.href = '/';
            return;
        }
        await loadStatus();
    }

    async function loadStatus() {
        const result = await App.get('/api/bravo.php?action=student_status');
        if (!result.success) {
            container().innerHTML = '<div style="text-align:center;padding:40px;color:#999;">데이터를 불러올 수 없습니다.</div>';
            return;
        }
        statusData = result;

        if (!result.ace_completed) {
            renderLocked();
            return;
        }

        // bravo_current_level이 없으면 1로 초기화
        const bravoLevel = result.bravo_current_level || 1;
        renderDashboard(bravoLevel);
    }

    // ========================================
    // ACE 미완료 잠금 화면
    // ========================================
    function renderLocked() {
        container().innerHTML = `
            <div style="max-width:480px;margin:0 auto;padding:60px 20px;text-align:center;">
                <div style="font-size:56px;margin-bottom:16px;">🔒</div>
                <h2 style="font-size:22px;font-weight:800;color:#1E293B;margin:0 0 8px;">Bravo 도전</h2>
                <p style="color:#64748B;font-size:14px;line-height:1.6;">
                    ACE 3를 통과하면<br>Bravo 도전이 열려요!
                </p>
                <a href="/ace/" style="display:inline-block;margin-top:20px;padding:12px 28px;border-radius:14px;background:#FF5722;color:#fff;font-weight:700;text-decoration:none;">
                    ACE 도전하기
                </a>
            </div>`;
    }

    // ========================================
    // 대시보드 (레벨 선택)
    // ========================================
    function renderDashboard(bravoLevel) {
        const bands = statusData.bands || {};
        const levelsMeta = statusData.levels_meta || {};
        const levelStatus = statusData.level_status || {};

        let html = `
            <div class="bravo-header" style="position:relative;">
                <a href="/ace/" class="bravo-back-btn" title="돌아가기">←</a>
                <div class="bravo-header-sub">소리튠 주니어</div>
                <div class="bravo-header-title">
                    <span style="font-size:22px;">🏆</span>
                    <h1>Bravo 도전</h1>
                </div>
            </div>
            <div class="bravo-dashboard">
                <div class="bravo-level-cards">`;

        // 레벨 1~6 카드 (현재 Yellow + Green만)
        const bandGroups = [
            { key: 'yellow', label: '🟡 Yellow반', levels: [1,2,3] },
            { key: 'green',  label: '🟢 Green반',  levels: [4,5,6] },
        ];

        for (const bg of bandGroups) {
            html += `<div style="font-size:13px;font-weight:700;color:#64748B;margin:12px 0 4px;padding-left:4px;">${bg.label}</div>`;

            for (const lv of bg.levels) {
                const m = levelsMeta[lv];
                if (!m) continue;

                const ls = levelStatus[lv];
                const isPassed = ls && ls.coach_result === 'pass';
                const isSubmitted = ls && ls.status === 'submitted';
                const isAvailable = lv <= bravoLevel && !isSubmitted;
                const isLocked = lv > bravoLevel;

                let chipHtml = '';
                let cardClass = '';
                if (isPassed) {
                    chipHtml = '<span class="bravo-level-card-chip pass">PASS ✅</span>';
                    cardClass = 'passed';
                } else if (isSubmitted) {
                    chipHtml = '<span class="bravo-level-card-chip waiting">확인 대기</span>';
                    cardClass = '';
                } else if (isAvailable) {
                    chipHtml = '<span class="bravo-level-card-chip available">도전 가능</span>';
                    cardClass = 'current';
                } else {
                    chipHtml = '<span class="bravo-level-card-chip locked">🔒</span>';
                    cardClass = 'locked';
                }

                const clickAttr = (isAvailable && !isPassed) ? `onclick="BravoApp.startLevel(${lv})"` : '';

                html += `
                    <div class="bravo-level-card ${cardClass}" ${clickAttr} style="--level-color:${m.color};--level-light:${m.color}15;">
                        <div class="bravo-level-card-icon" style="background:${m.color};">${lv}</div>
                        <div class="bravo-level-card-info">
                            <div class="bravo-level-card-name">${m.bravo}</div>
                            <div class="bravo-level-card-desc">${m.level} · ${m.title}</div>
                        </div>
                        ${chipHtml}
                    </div>`;
            }
        }

        html += `</div></div>`;
        container().innerHTML = html;
    }

    // ========================================
    // 테스트 시작
    // ========================================
    async function startLevel(level) {
        App.showLoading();
        currentLevel = level;
        part = 0;
        qi = 0;
        answers = {};
        recordings = {};

        // 세션 시작
        const sessionResult = await App.post('/api/bravo.php?action=start_session', { level });
        if (!sessionResult.success) {
            App.hideLoading();
            Toast.error(sessionResult.error || '세션 시작 실패');
            return;
        }
        submissionId = sessionResult.submission_id;

        // 문항 로드
        const itemsResult = await App.get('/api/bravo.php?action=get_items', { level });
        if (!itemsResult.success) {
            App.hideLoading();
            Toast.error('문항 로드 실패');
            return;
        }
        items = itemsResult.items;
        meta = itemsResult.meta;

        // 마이크 권한 요청
        try {
            await AceRecorder.requestMic();
        } catch (e) {
            console.warn('Mic permission:', e);
        }

        App.hideLoading();
        renderTest();
    }

    // ========================================
    // 파트 분류
    // ========================================
    function getPartList() {
        const quiz = items.filter(i => i.section_type === 'quiz');
        const sentence = items.filter(i => i.section_type === 'sentence');
        const isPhonics = meta && meta.isPhonics;
        const third = isPhonics
            ? items.filter(i => i.section_type === 'phonics')
            : items.filter(i => i.section_type === 'block');

        return [
            { key: 'quiz', title: '단어 퀴즈', icon: '📝', items: quiz },
            { key: 'sentence', title: '문장 읽기', icon: '🎤', items: sentence },
            isPhonics
                ? { key: 'phonics', title: '파닉스 읽기', icon: '🔤', items: third }
                : { key: 'block', title: '블록 만들기', icon: '🧱', items: third },
        ];
    }

    function getPartCompletion() {
        const parts = getPartList();
        return parts.map(p => {
            if (p.key === 'quiz') {
                return p.items.every(i => answers[i.id] !== undefined);
            } else if (p.key === 'sentence' || p.key === 'phonics') {
                return p.items.every(i => recordings[i.id]);
            } else if (p.key === 'block') {
                return p.items.every(i => {
                    const ans = answers[i.id];
                    if (!ans) return false;
                    const d = i.item_data;
                    return Object.keys(ans).length >= d.blanks && d.a.every((a, j) => (ans[j] || ans[String(j)]) === a);
                });
            }
            return false;
        });
    }

    // ========================================
    // 테스트 렌더링
    // ========================================
    function renderTest() {
        const parts = getPartList();
        const completion = getPartCompletion();
        const cp = parts[part];
        const item = cp.items[qi];
        const allDone = completion.every(c => c);
        const color = meta.color;

        let html = `
            <div style="padding-bottom:80px;">
                <!-- 테스트 헤더 -->
                <div class="bravo-test-header" style="background:linear-gradient(135deg,${color},${color}CC);">
                    <div class="bravo-test-header-top">
                        <div>
                            <div class="bravo-test-header-sub">${meta.bravo} · ${meta.level}</div>
                            <div class="bravo-test-header-title">${meta.title}</div>
                        </div>
                        <button onclick="BravoApp.exitTest()" style="padding:6px 12px;border-radius:8px;border:1px solid rgba(255,255,255,0.3);background:rgba(255,255,255,0.1);color:#fff;font-size:10px;cursor:pointer;font-weight:600;">나가기</button>
                    </div>
                    <div class="bravo-part-tabs">`;

        for (let pi = 0; pi < parts.length; pi++) {
            const p = parts[pi];
            html += `
                <button class="bravo-part-tab ${part === pi ? 'active' : ''}" onclick="BravoApp.switchPart(${pi})">
                    <div class="bravo-part-tab-icon">${p.icon}</div>
                    <div class="bravo-part-tab-name">${p.title}</div>
                    ${completion[pi] ? '<div class="bravo-part-tab-check">✓</div>' : ''}
                </button>`;
        }

        html += `</div></div>
            <div class="bravo-test-content">
                <div class="bravo-progress-bar">
                    <span class="bravo-progress-label" style="color:${color};">${cp.icon} ${cp.title}</span>
                    <span class="bravo-progress-count">${qi + 1} / ${cp.items.length}</span>
                </div>
                <div class="bravo-progress-track">
                    <div class="bravo-progress-fill" style="width:${((qi + 1) / cp.items.length) * 100}%;background:${color};"></div>
                </div>`;

        // 카드 렌더
        if (cp.key === 'quiz') {
            html += renderQuizCard(item, color);
        } else if (cp.key === 'sentence') {
            html += renderSentenceCard(item, color);
        } else if (cp.key === 'phonics') {
            html += renderPhonicsCard(item, color);
        } else if (cp.key === 'block') {
            html += renderBlockCard(item, color);
        }

        // 네비게이션
        html += `
                <div class="bravo-nav">
                    <button class="bravo-nav-prev" onclick="BravoApp.prevItem()" ${qi === 0 ? 'disabled' : ''}>← 이전</button>
                    <button class="bravo-nav-next" onclick="BravoApp.nextItem()" style="background:${color};" ${qi >= cp.items.length - 1 ? 'disabled' : ''}>다음 →</button>
                </div>
            </div>

            <!-- 제출 바 -->
            <div class="bravo-submit-bar">
                <button class="bravo-submit-btn" onclick="BravoApp.submitTest()" ${!allDone ? 'disabled' : ''}
                    style="${allDone ? `background:linear-gradient(135deg,${color},#FF6B35);color:#fff;` : ''}">
                    ${allDone ? `🎯 ${meta.bravo} 도전 제출!` : '3가지 모두 완료하면 제출!'}
                </button>
            </div>
        </div>`;

        container().innerHTML = html;
    }

    // ========================================
    // 퀴즈 카드
    // ========================================
    function renderQuizCard(item, color) {
        const d = item.item_data;
        const sel = answers[item.id];

        let choicesHtml = '';
        for (const ch of d.c) {
            let cls = '';
            if (sel !== undefined) {
                if (ch === d.a) cls = 'correct';
                else if (ch === sel && ch !== d.a) cls = 'wrong';
                else cls = 'dimmed';
            }
            const onclick = sel === undefined ? `onclick="BravoApp.selectQuiz(${item.id},'${ch.replace(/'/g, "\\'")}')"` : '';
            choicesHtml += `<button class="bravo-quiz-choice ${cls}" ${onclick}>${cls === 'correct' ? '✅ ' : cls === 'wrong' ? '❌ ' : ''}${ch}</button>`;
        }

        let resultHtml = '';
        if (sel !== undefined) {
            if (sel === d.a) {
                resultHtml = '<div class="bravo-quiz-result success">정답! 🎉</div>';
            } else {
                resultHtml = `<div class="bravo-quiz-answer">정답: <strong style="color:#059669;">${d.a}</strong></div>`;
            }
        }

        return `
            <div class="bravo-card">
                <div class="bravo-quiz-word">
                    <div class="bravo-quiz-word-text">${d.w}</div>
                    <div class="bravo-quiz-word-ipa">${d.ipa || ''}</div>
                </div>
                <div class="bravo-quiz-choices">${choicesHtml}</div>
                ${resultHtml}
            </div>`;
    }

    // ========================================
    // 문장 카드
    // ========================================
    function renderSentenceCard(item, color) {
        const d = item.item_data;
        const done = recordings[item.id];

        return `
            <div class="bravo-card ${done ? 'done' : ''}" style="text-align:center;${done ? '' : `border-color:${color}30;`}">
                ${done ? '<div class="bravo-card-check">✓</div>' : ''}
                <div class="bravo-sentence-text">"${d.s}"</div>
                <div class="bravo-sentence-kr">${d.kr}</div>
                ${d.p ? `<div class="bravo-sentence-pattern" style="background:${color}15;color:${color};">🧱 ${d.p}</div>` : ''}
                ${renderRecBtn(item.id, color)}
            </div>`;
    }

    // ========================================
    // 파닉스 카드
    // ========================================
    function renderPhonicsCard(item, color) {
        const d = item.item_data;
        const done = recordings[item.id];

        return `
            <div class="bravo-card ${done ? 'done' : ''}" style="text-align:center;padding:40px 22px;${done ? '' : `border-color:${color}30;`}">
                ${done ? '<div class="bravo-card-check">✓</div>' : ''}
                <div class="bravo-phonics-letters" style="color:${color};background:${color}15;">${d.letters}</div>
                <div class="bravo-phonics-arrow">↓ 합치면</div>
                <div class="bravo-phonics-word">${d.word}</div>
                ${renderRecBtn(item.id, color)}
            </div>`;
    }

    // ========================================
    // 블록 카드
    // ========================================
    function renderBlockCard(item, color) {
        const d = item.item_data;
        const nb = d.blanks || 1;
        const filled = answers[item.id] || {};
        const filledCount = Object.keys(filled).length;
        const currentBlank = Math.min(filledCount, nb - 1);
        const allFilled = filledCount >= nb;
        const allCorrect = allFilled && d.a.every((a, i) => filled[i] === a || filled[String(i)] === a);

        // 슬롯 매핑
        let blankIdx = 0;
        const slotBlanks = d.slots.map(sl => sl === '___' ? blankIdx++ : -1);

        let slotsHtml = '';
        for (let si = 0; si < d.slots.length; si++) {
            const bi = slotBlanks[si];
            if (bi === -1) {
                slotsHtml += `<div class="bravo-block-fixed">${d.slots[si]}</div>`;
            } else {
                const val = filled[bi] || filled[String(bi)];
                const correct = val === d.a[bi];
                const wrong = val && !correct;
                const isCurrent = bi === currentBlank && !allFilled;
                let style = `border:2.5px ${isCurrent ? 'solid' : 'dashed'} ${correct ? '#059669' : wrong ? '#EF4444' : isCurrent ? color : color + '60'};`;
                style += `background:${correct ? 'rgba(5,150,105,0.12)' : wrong ? 'rgba(239,68,68,0.1)' : isCurrent ? color + '15' : color + '08'};`;
                style += `color:${correct ? '#059669' : wrong ? '#EF4444' : val ? color : '#94A3B8'};`;
                if (isCurrent) style += 'transform:scale(1.05);box-shadow:0 0 0 3px ' + color + '25;';
                slotsHtml += `<div class="bravo-block-blank" style="${style}">${val || (isCurrent ? '?' : '·')}</div>`;
            }
        }

        let choicesHtml = '';
        if (!allCorrect) {
            const choices = d.c[Math.min(currentBlank, d.c.length - 1)] || [];
            for (const ch of choices) {
                const isFilledThis = filled[currentBlank] === ch || filled[String(currentBlank)] === ch;
                const correctThis = filled[currentBlank] !== undefined && ch === d.a[currentBlank];
                const wrongThis = isFilledThis && ch !== d.a[currentBlank];
                let cls = correctThis ? 'correct' : wrongThis ? 'wrong' : '';
                const onclick = filled[currentBlank] === undefined && filled[String(currentBlank)] === undefined
                    ? `onclick="BravoApp.selectBlock(${item.id},${currentBlank},'${ch.replace(/'/g, "\\'")}')"` : '';
                choicesHtml += `<button class="bravo-block-choice ${cls}" ${onclick}>${correctThis ? '✅ ' : wrongThis ? '❌ ' : ''}${ch}</button>`;
            }
        }

        let successHtml = '';
        if (allCorrect) {
            successHtml = `
                <div class="bravo-block-success">
                    <div class="bravo-block-success-text">"${d.r}"</div>
                    <div style="font-size:13px;color:#64748B;margin-top:4px;">완벽해! 🎉</div>
                </div>`;
        }

        return `
            <div class="bravo-card" style="${allCorrect ? 'border:2px solid #059669;' : ''}">
                <div class="bravo-block-hint">
                    <div class="bravo-block-hint-label">💡 이런 뜻의 문장을 만들어봐!</div>
                    <div class="bravo-block-hint-text">${d.kr}</div>
                </div>
                <div class="bravo-block-slots">${slotsHtml}</div>
                ${!allFilled ? `<div class="bravo-block-indicator" style="color:${color};">▲ ${currentBlank + 1}/${nb}번째 빈칸을 채워봐!</div>` : ''}
                ${choicesHtml ? `<div class="bravo-block-choices">${choicesHtml}</div>` : ''}
                ${successHtml}
            </div>`;
    }

    // ========================================
    // 녹음 버튼
    // ========================================
    function renderRecBtn(itemId, color) {
        const done = recordings[itemId];
        const isRecording = AceRecorder.isRecording();

        if (isRecording && recStartTime > 0) {
            return `
                <div class="bravo-rec-area">
                    <div class="bravo-rec-dot"></div>
                    <button class="bravo-rec-btn recording" onclick="BravoApp.stopRec(${itemId})">
                        <div class="bravo-rec-btn-stop"></div>
                    </button>
                    <span class="bravo-rec-hint">멈추려면 눌러!</span>
                </div>`;
        }

        if (done) {
            return `
                <div class="bravo-rec-area">
                    <button class="bravo-rec-play-btn" onclick="BravoApp.playRec(${itemId})" style="border-color:${color};color:${color};">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="${color}"><polygon points="5 3 19 12 5 21"/></svg>
                    </button>
                    <button class="bravo-rec-btn" style="border-color:${color};background:linear-gradient(135deg,${color}15,${color}30);" onclick="BravoApp.startRec(${itemId})">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="${color}"><path d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3z"/><path d="M17 11c0 2.76-2.24 5-5 5s-5-2.24-5-5H5c0 3.53 2.61 6.43 6 6.92V21h2v-3.08c3.39-.49 6-3.39 6-6.92h-2z"/></svg>
                    </button>
                    <span class="bravo-rec-hint">✅ 녹음 완료 · 다시 하려면 🎤</span>
                </div>`;
        }

        return `
            <div class="bravo-rec-area">
                <button class="bravo-rec-btn" style="border-color:${color};background:linear-gradient(135deg,${color}15,${color}30);" onclick="BravoApp.startRec(${itemId})">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="${color}"><path d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3z"/><path d="M17 11c0 2.76-2.24 5-5 5s-5-2.24-5-5H5c0 3.53 2.61 6.43 6 6.92V21h2v-3.08c3.39-.49 6-3.39 6-6.92h-2z"/></svg>
                </button>
                <span class="bravo-rec-hint">🎤 읽고 녹음해봐!</span>
            </div>`;
    }

    // ========================================
    // 인터랙션 핸들러
    // ========================================
    async function selectQuiz(itemId, answer) {
        const item = items.find(i => i.id == itemId);
        if (!item) return;

        answers[itemId] = answer;

        // 서버에 저장
        App.post('/api/bravo.php?action=save_answer', {
            submission_id: submissionId,
            item_id: itemId,
            answer: answer,
        });

        renderTest();
    }

    async function selectBlock(itemId, blankIndex, choice) {
        const item = items.find(i => i.id == itemId);
        if (!item) return;

        if (!answers[itemId]) answers[itemId] = {};
        answers[itemId][blankIndex] = choice;

        const d = item.item_data;
        const nb = d.blanks || 1;
        const filled = answers[itemId];
        const filledCount = Object.keys(filled).length;

        // 모든 빈칸이 채워졌으면 서버에 저장
        if (filledCount >= nb) {
            App.post('/api/bravo.php?action=save_answer', {
                submission_id: submissionId,
                item_id: itemId,
                answer: answers[itemId],
            });
        }

        renderTest();
    }

    let playingAudio = null;
    function playRec(itemId) {
        if (playingAudio) { playingAudio.pause(); playingAudio = null; }
        const url = recordings[itemId];
        if (!url) return;
        playingAudio = new Audio(url);
        playingAudio.play();
    }

    async function startRec(itemId) {
        try {
            await AceRecorder.start();
            recStartTime = Date.now();

            // 30초 자동 정지
            autoTimer = setTimeout(() => stopRec(itemId), 30000);

            renderTest();
        } catch (e) {
            Toast.error('마이크를 사용할 수 없어요');
        }
    }

    async function stopRec(itemId) {
        if (autoTimer) { clearTimeout(autoTimer); autoTimer = null; }

        try {
            const blob = await AceRecorder.stop();
            recStartTime = 0;

            if (!blob || blob.size < 1000) {
                Toast.warning('녹음이 너무 짧아! 다시 해봐.');
                renderTest();
                return;
            }

            // 업로드
            const formData = new FormData();
            formData.append('submission_id', submissionId);
            formData.append('item_id', itemId);
            const ext = (AceRecorder.getMimeType() || 'audio/webm').includes('mp4') ? 'mp4' : 'webm';
            formData.append('audio', blob, `recording.${ext}`);

            const result = await App.api('/api/bravo.php?action=upload_audio', {
                method: 'POST',
                data: formData,
            });

            if (result.success) {
                recordings[itemId] = URL.createObjectURL(blob);
            } else {
                Toast.error(result.error || '업로드 실패');
            }
        } catch (e) {
            console.error('Recording error:', e);
        }

        renderTest();
    }

    function switchPart(newPart) {
        part = newPart;
        qi = 0;
        renderTest();
    }

    function prevItem() {
        if (qi > 0) { qi--; renderTest(); }
    }

    function nextItem() {
        const parts = getPartList();
        if (qi < parts[part].items.length - 1) { qi++; renderTest(); }
    }

    function exitTest() {
        App.confirm('테스트를 그만둘까?', () => {
            AceRecorder.cleanup();
            if (embeddedMode) {
                AceStudentApp.reloadDashboard();
            } else {
                loadStatus();
            }
        });
    }

    // ========================================
    // 제출
    // ========================================
    async function submitTest() {
        const completion = getPartCompletion();
        if (!completion.every(c => c)) {
            Toast.warning('3가지 모두 완료해야 제출할 수 있어!');
            return;
        }

        App.showLoading();

        const result = await App.post('/api/bravo.php?action=submit', {
            submission_id: submissionId,
        });

        App.hideLoading();

        if (!result.success) {
            Toast.error(result.error || '제출 실패');
            return;
        }

        AceRecorder.cleanup();
        renderResult(result);
    }

    // ========================================
    // 결과 화면
    // ========================================
    function renderResult(result) {
        const color = meta.color;
        const qParts = result.quiz_score ? result.quiz_score.split('/') : ['0','0'];
        const bParts = result.block_score ? result.block_score.split('/') : ['0','0'];
        const qc = parseInt(qParts[0]), qt = parseInt(qParts[1]);
        const bc = parseInt(bParts[0]), bt = parseInt(bParts[1]);
        const qPct = qt > 0 ? Math.round(qc / qt * 100) : 0;
        const bPct = bt > 0 ? Math.round(bc / bt * 100) : 0;

        // 컨페티
        spawnConfetti();

        container().innerHTML = `
            <div class="bravo-header" style="position:relative;">
                <div class="bravo-header-sub">소리튠 주니어</div>
                <div class="bravo-header-title">
                    <span style="font-size:22px;">🏆</span>
                    <h1>Bravo 도전</h1>
                </div>
            </div>
            <div class="bravo-result">
                <div class="bravo-result-emoji">🎉</div>
                <div class="bravo-result-title">잘했어!</div>
                <div class="bravo-result-coins">
                    <span class="bravo-result-coin">🪙</span>
                    <span class="bravo-result-coin">🪙</span>
                    <span class="bravo-result-coin">🪙</span>
                </div>
                <div class="bravo-result-coin-text">코인 3개 획득!</div>

                <div class="bravo-result-score">
                    <div class="bravo-result-score-title">📊 테스트 결과</div>
                    <div class="bravo-result-score-row">
                        <span class="bravo-result-score-label">📝 단어 퀴즈</span>
                        <div class="bravo-result-score-bar">
                            <div class="bravo-result-score-fill" style="width:${qPct}%;background:${qPct >= 60 ? '#059669' : '#EF4444'};"></div>
                        </div>
                        <span class="bravo-result-score-num" style="color:${qPct >= 60 ? '#059669' : '#EF4444'};">${qc}/${qt}</span>
                    </div>
                    ${bt > 0 ? `
                    <div class="bravo-result-score-row">
                        <span class="bravo-result-score-label">🧱 블록</span>
                        <div class="bravo-result-score-bar">
                            <div class="bravo-result-score-fill" style="width:${bPct}%;background:${bPct >= 60 ? '#059669' : '#EF4444'};"></div>
                        </div>
                        <span class="bravo-result-score-num" style="color:${bPct >= 60 ? '#059669' : '#EF4444'};">${bc}/${bt}</span>
                    </div>` : ''}
                    <div style="margin-top:10px;padding:8px 12px;border-radius:10px;text-align:center;font-size:13px;font-weight:700;
                        background:${result.auto_result === 'pass' ? '#ECFDF5' : '#FEF2F2'};
                        color:${result.auto_result === 'pass' ? '#059669' : '#EF4444'};">
                        자동 채점: ${result.auto_result === 'pass' ? 'PASS ✅' : 'FAIL ❌'}
                    </div>
                </div>

                <div class="bravo-result-msg">
                    코치 선생님이 결과를 확인할 거야<br>결과 나오면 알려줄게 ✨
                </div>

                <button class="bravo-result-home" onclick="BravoApp.goHome()">처음으로 🏠</button>
            </div>`;
    }

    function spawnConfetti() {
        const emojis = ['🎉', '🎊', '⭐', '🌟', '💫', '✨'];
        for (let i = 0; i < 30; i++) {
            const el = document.createElement('div');
            el.className = 'bravo-confetti';
            el.textContent = emojis[Math.floor(Math.random() * emojis.length)];
            el.style.left = Math.random() * 100 + 'vw';
            el.style.animationDelay = Math.random() * 2 + 's';
            el.style.animationDuration = (2 + Math.random() * 2) + 's';
            document.body.appendChild(el);
            setTimeout(() => el.remove(), 5000);
        }
    }

    function goHome() {
        if (embeddedMode) {
            AceStudentApp.reloadDashboard();
        } else {
            loadStatus();
        }
    }

    // ACE 페이지에서 Bravo 테스트 시작
    function startFromAce(level) {
        embeddedMode = true;
        startLevel(level);
    }

    // ========================================
    // Init
    // ========================================
    // standalone (/bravo/) 일때만 자동 초기화
    if (document.body.classList.contains('bravo-page')) {
        document.addEventListener('DOMContentLoaded', init);
    }

    return {
        startLevel, startFromAce, switchPart, prevItem, nextItem, exitTest,
        selectQuiz, selectBlock, startRec, stopRec, playRec,
        submitTest, goHome,
    };
})();
