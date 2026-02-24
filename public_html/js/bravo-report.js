/**
 * 소리튠 주니어 - BRAVO 부모 성장 리포트
 * 토큰 기반 공개 페이지 (로그인 불필요)
 */
const BravoReportApp = (() => {
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
            const resp = await fetch('/api/bravo.php?action=report&token=' + encodeURIComponent(token));
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
        const levelsMeta = d.levels_meta || {};
        const bravoLevel = d.bravo_level;
        const meta = levelsMeta[bravoLevel] || {};

        let html = `
            <!-- 헤더 -->
            <div class="report-header">
                <div class="report-brand">
                    <div class="report-brand-logo">B</div>
                    <div>
                        <div class="report-brand-name">SoriTune Junior</div>
                        <div class="report-brand-sub">Bravo 성장 리포트</div>
                    </div>
                </div>
            </div>

            <!-- 학생 카드 -->
            <div class="report-student-card">
                <div class="report-student-avatar" style="background:${d.class_color || '#FF9800'}">${esc(d.student_name).charAt(0)}</div>
                <div class="report-student-info">
                    <div class="report-student-name">${esc(d.student_name)}</div>
                    <div class="report-student-class">${esc(d.class_name)}</div>
                </div>
                <div class="report-student-coach">
                    <div style="font-size:11px; color:#999;">코치</div>
                    <div style="font-size:13px; font-weight:700; color:#333;">${esc(d.coach_name || '')}</div>
                </div>
            </div>

            <!-- BRAVO 레벨 현황 -->
            <div class="report-section">
                <div class="report-section-title">🏅 Bravo 인증 현황</div>
                <div class="report-bravo-badges">
        `;

        const allEvals = d.all_evaluations || [];
        // 6레벨 뱃지 (Yellow 1-3, Green 4-6)
        [1, 2, 3, 4, 5, 6].forEach(level => {
            const lMeta = levelsMeta[level] || {};
            const evalRecord = allEvals.find(e => parseInt(e.bravo_level) === level && e.coach_result === 'pass');
            const isPassed = !!evalRecord;
            const isCurrent = bravoLevel === level;
            const bandEmoji = lMeta.band === 'yellow' ? '🟡' : (lMeta.band === 'green' ? '🟢' : '🔵');

            html += `
                <div class="report-bravo-badge ${isPassed ? 'passed' : (isCurrent ? 'current' : 'locked')}">
                    <div class="report-bravo-badge-icon">${isPassed ? '✅' : (isCurrent ? '🎯' : '🔒')}</div>
                    <div class="report-bravo-badge-name">${bandEmoji} Jr ${level}</div>
                    <div class="report-bravo-badge-status">${isPassed ? 'PASS' : (isCurrent ? (d.result === 'pass' ? 'PASS' : '도전중') : '대기')}</div>
                </div>
            `;
        });

        html += `
                </div>
            </div>
        `;

        // 점수 섹션
        const quizC = d.quiz_correct || 0;
        const quizT = d.quiz_total || 0;
        const blockC = d.block_correct || 0;
        const blockT = d.block_total || 0;
        const totalC = quizC + blockC;
        const totalT = quizT + blockT;
        const rate = totalT > 0 ? Math.round(totalC / totalT * 100) : 0;
        const autoPass = d.auto_result === 'pass';

        html += `
            <div class="report-section">
                <div class="report-section-title">📊 ${meta.bravo || 'Bravo Jr ' + bravoLevel} 점수</div>
                <div class="report-section-hint">${meta.title || ''}</div>
                <div class="report-score-cards">
                    <div class="report-score-card">
                        <div class="report-score-value">${quizC}<span style="font-size:16px; color:#999;">/${quizT}</span></div>
                        <div class="report-score-label">단어 퀴즈</div>
                    </div>
                    <div class="report-score-card">
                        <div class="report-score-value">${blockC}<span style="font-size:16px; color:#999;">/${blockT}</span></div>
                        <div class="report-score-label">블록 만들기</div>
                    </div>
                    <div class="report-score-card">
                        <div class="report-score-value" style="color:${autoPass ? '#2E7D32' : '#C62828'}">${rate}%</div>
                        <div class="report-score-label">종합</div>
                    </div>
                </div>
                <div class="report-auto-result ${autoPass ? 'pass' : 'fail'}">
                    자동 채점: ${rate}% ${autoPass ? '(PASS)' : '(FAIL)'}
                </div>
            </div>
        `;

        // 녹음 재생 섹션
        const recordings = d.recordings || [];
        const sentenceRecs = recordings.filter(r => r.section_type === 'sentence');
        const phonicsRecs = recordings.filter(r => r.section_type === 'phonics');

        if (sentenceRecs.length > 0) {
            html += `
                <div class="report-section">
                    <div class="report-section-title">🎤 문장 읽기</div>
                    <div class="report-sound-list">
            `;
            sentenceRecs.forEach(r => {
                const data = r.item_data || {};
                html += `
                    <div class="report-sound-item">
                        <div class="report-sound-text">${esc(data.s || '')}</div>
                        <button class="report-play-btn" onclick="BravoReportApp.play(${r.recording_id}, this)">
                            ▶ 재생
                        </button>
                    </div>
                `;
            });
            html += `
                    </div>
                </div>
            `;
        }

        if (phonicsRecs.length > 0) {
            html += `
                <div class="report-section">
                    <div class="report-section-title">🔤 파닉스 읽기</div>
                    <div class="report-sound-list">
            `;
            phonicsRecs.forEach(r => {
                const data = r.item_data || {};
                html += `
                    <div class="report-sound-item">
                        <div class="report-sound-text">${esc(data.letters || '')} → ${esc(data.word || '')}</div>
                        <button class="report-play-btn" onclick="BravoReportApp.play(${r.recording_id}, this)">
                            ▶ 재생
                        </button>
                    </div>
                `;
            });
            html += `
                    </div>
                </div>
            `;
        }

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

        // 푸터
        html += `
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

        const audio = new Audio('/api/bravo.php?action=audio&id=' + recordingId + '&token=' + encodeURIComponent(token));
        btnEl.classList.add('playing');
        audio.onended = () => { btnEl.classList.remove('playing'); playingAudio = null; };
        audio.onerror = () => { btnEl.classList.remove('playing'); };
        audio.play();
        playingAudio = audio;
    }

    document.addEventListener('DOMContentLoaded', init);

    return { init, play };
})();
