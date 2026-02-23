/**
 * 소리튠 주니어 - ACE 부모 성장 리포트
 * 토큰 기반 공개 페이지 (로그인 불필요)
 */
const AceReportApp = (() => {
    let reportData = null;
    let token = '';
    let playingAudio = null;

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    async function init() {
        const params = new URLSearchParams(window.location.search);
        token = params.get('token') || '';

        if (!token) {
            showError('유효하지 않은 링크입니다.');
            return;
        }

        try {
            const resp = await fetch('/api/ace.php?action=report&token=' + encodeURIComponent(token));
            const result = await resp.json();

            if (!result.success) {
                showError(result.error || '리포트를 불러올 수 없습니다.');
                return;
            }

            reportData = result;
            renderReport();
        } catch (e) {
            showError('네트워크 오류가 발생했습니다.');
        }
    }

    function showError(msg) {
        document.getElementById('report-loading').style.display = 'none';
        const el = document.getElementById('report-error');
        el.style.display = 'block';
        el.innerHTML = `
            <div style="text-align:center; padding:60px 20px;">
                <div style="font-size:48px; margin-bottom:16px;">😔</div>
                <div style="font-size:18px; font-weight:700; color:#333; margin-bottom:8px;">${esc(msg)}</div>
                <div style="font-size:14px; color:#999;">문제가 계속되면 코치 선생님에게 문의해주세요.</div>
            </div>
        `;
    }

    function renderReport() {
        document.getElementById('report-loading').style.display = 'none';
        const container = document.getElementById('report-content');
        container.style.display = 'block';

        const d = reportData;
        const levelNames = { 1: 'ACE 1', 2: 'ACE 2', 3: 'ACE 3' };
        const levelDescs = { 1: '1음절 단어', 2: '긴 단어', 3: '문장' };

        // Before/After 녹음 분리
        const beforeRecs = d.recordings.filter(r => r.role === 'before');
        const afterRecs = d.recordings.filter(r => r.role === 'after');

        let html = `
            <!-- 헤더 -->
            <div class="report-header">
                <div class="report-brand">
                    <div class="report-brand-logo">S</div>
                    <div>
                        <div class="report-brand-name">SoriTune Junior</div>
                        <div class="report-brand-sub">소리 성장 리포트</div>
                    </div>
                </div>
            </div>

            <!-- 학생 카드 -->
            <div class="report-student-card">
                <div class="report-student-avatar" style="background:${d.class_color || '#673AB7'}">${esc(d.student_name).charAt(0)}</div>
                <div class="report-student-info">
                    <div class="report-student-name">${esc(d.student_name)}</div>
                    <div class="report-student-class">${esc(d.class_name)}</div>
                </div>
                <div class="report-student-coach">
                    <div style="font-size:11px; color:#999;">코치</div>
                    <div style="font-size:13px; font-weight:700; color:#333;">${esc(d.coach_name || '')}</div>
                </div>
            </div>

            <!-- ACE 현황 -->
            <div class="report-section">
                <div class="report-section-title">🏅 ACE 인증 현황</div>
                <div class="report-ace-badges">
        `;

        const allEvals = d.all_evaluations || [];
        [1, 2, 3].forEach(level => {
            const eval_ = allEvals.find(e => parseInt(e.ace_level) === level && e.result === 'pass');
            const isPassed = !!eval_;
            const isEvaluated = parseInt(d.ace_level) === level;
            html += `
                <div class="report-ace-badge ${isPassed ? 'passed' : (isEvaluated ? 'current' : 'locked')}">
                    <div class="report-ace-badge-icon">${isPassed ? '✅' : (isEvaluated ? '🎯' : '🔒')}</div>
                    <div class="report-ace-badge-name">${levelNames[level]}</div>
                    <div class="report-ace-badge-desc">${levelDescs[level]}</div>
                    <div class="report-ace-badge-status">${isPassed ? 'PASS' : (isEvaluated ? (d.result === 'pass' ? 'PASS' : '도전중') : '대기')}</div>
                </div>
            `;
        });

        html += `
                </div>
            </div>

            <!-- 소리 비교 -->
            <div class="report-section">
                <div class="report-section-title">🎧 소리 비교 (Before / After)</div>
                <div class="report-section-hint">${levelNames[d.ace_level]} · ${levelDescs[d.ace_level]}</div>
                <div class="report-sound-compare">
        `;

        // 항목별 매칭
        const itemMap = new Map();
        beforeRecs.forEach(r => {
            itemMap.set(r.item_index, { ...(itemMap.get(r.item_index) || {}), before: r, text: r.item_text, type: r.item_type });
        });
        afterRecs.forEach(r => {
            itemMap.set(r.item_index, { ...(itemMap.get(r.item_index) || {}), after: r, text: r.item_text, type: r.item_type });
        });

        for (const [idx, item] of [...itemMap.entries()].sort((a, b) => a[0] - b[0])) {
            html += `
                <div class="report-sound-item">
                    <div class="report-sound-text ${item.type === 'sentence' ? 'sentence' : ''}">${esc(item.text)}</div>
                    <div class="report-sound-players">
            `;
            if (item.before) {
                html += `<button class="report-play-btn before" onclick="AceReportApp.play(${item.before.recording_id}, this)">
                    ▶ Before
                </button>`;
            }
            if (item.after) {
                html += `<button class="report-play-btn after" onclick="AceReportApp.play(${item.after.recording_id}, this)">
                    ▶ After
                </button>`;
            }
            html += `
                    </div>
                </div>
            `;
        }

        html += `
                </div>
            </div>
        `;

        // 코치 코멘트
        if (d.comment_text) {
            const typeLabels = { excellent: '🌟 우수', growing: '🌱 성장', support: '💪 보완' };
            html += `
                <div class="report-section">
                    <div class="report-section-title">💬 코치 코멘트</div>
                    <div class="report-comment-card">
                        <div class="report-comment-type">${typeLabels[d.comment_type] || ''}</div>
                        <div class="report-comment-text">${esc(d.comment_text)}</div>
                        <div class="report-comment-coach">— ${esc(d.coach_name || '')} 코치</div>
                    </div>
                </div>
            `;
        }

        // 인증서 링크
        html += `
            <div class="report-section" style="text-align:center; padding-bottom:40px;">
                <a href="/ace-certificate/?token=${encodeURIComponent(token)}" class="report-cert-btn">
                    📜 성장 인증서 보기
                </a>
            </div>

            <!-- 푸터 -->
            <div class="report-footer">
                <div class="report-footer-logo">SoriTune Junior English Academy</div>
                <div class="report-footer-text">소리로 배우는 주니어 영어학교</div>
            </div>
        `;

        container.innerHTML = html;
    }

    function play(recordingId, btnEl) {
        if (playingAudio) {
            playingAudio.pause();
            playingAudio = null;
            document.querySelectorAll('.report-play-btn.playing').forEach(b => b.classList.remove('playing'));
        }

        const audio = new Audio('/api/ace.php?action=audio&id=' + recordingId + '&token=' + encodeURIComponent(token));
        btnEl.classList.add('playing');
        audio.onended = () => { btnEl.classList.remove('playing'); playingAudio = null; };
        audio.onerror = () => { btnEl.classList.remove('playing'); };
        audio.play();
        playingAudio = audio;
    }

    document.addEventListener('DOMContentLoaded', init);

    return { init, play };
})();
