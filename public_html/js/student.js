/**
 * 소리튠 주니어 영어학교 - 학생 SPA 로직 (프리미엄)
 * 보안: 이름 + 전화번호 본인확인, 핑거프린트 관리
 */
const StudentApp = (() => {
    let currentView = 'loading';
    let selectedClassId = null;
    let selectedClassName = null;
    let myPageData = null;
    let isLoggedIn = false;

    // ACE/BRAVO 진행 뱃지 (통과한 레벨 표시)
    function getProgressBadge(aceLevel, bravoLevel) {
        if (!aceLevel || aceLevel <= 1) return '';
        if (aceLevel < 4) return `<span class="progress-badge badge-ace">ACE${aceLevel - 1}</span>`;
        // ACE 전체 통과
        const bl = bravoLevel || 1;
        if (bl <= 1) return '<span class="progress-badge badge-ace">ACE3</span>';
        if (bl > 6) return '<span class="progress-badge badge-clear">ALL CLEAR</span>';
        return `<span class="progress-badge badge-bravo">BRAVO${bl - 1}</span>`;
    }

    // 아바타 색상 팔레트
    const AVATAR_COLORS = [
        ['#FF6B6B', '#EE5A5A'], ['#4ECDC4', '#3DBDB5'], ['#45B7D1', '#35A7C1'],
        ['#96E6A1', '#7DD68E'], ['#DDA0DD', '#CC8FCC'], ['#F7DC6F', '#E8CD5F'],
        ['#82E0AA', '#72D09A'], ['#F0B27A', '#E0A26A'], ['#85C1E9', '#75B1D9'],
        ['#C39BD3', '#B38BC3'],
    ];

    function getAvatarColor(name) {
        let hash = 0;
        for (let i = 0; i < name.length; i++) hash = name.charCodeAt(i) + ((hash << 5) - hash);
        return AVATAR_COLORS[Math.abs(hash) % AVATAR_COLORS.length];
    }

    // ============================================
    // 초기화
    // ============================================
    async function init() {
        bindEvents();

        // 1. 세션 확인
        const session = await App.api('/api/student.php?action=check_session', { showError: false });
        if (session.logged_in) {
            isLoggedIn = true;
        }

        // 2. 핑거프린트 자동 로그인 시도 (세션 없을 때만, 직전 로그아웃 아닐 때만)
        if (!isLoggedIn && !sessionStorage.getItem('student_logged_out')) {
            try {
                const fp = await DeviceFingerprint.generate();
                const result = await App.post('/api/student.php?action=auto_login', {
                    fingerprint: fp
                });
                if (result.success && result.found) {
                    if (result.auto_login) {
                        isLoggedIn = true;
                    } else if (result.students && result.students.length > 1) {
                        showSiblingSelect(result.students);
                        return;
                    }
                }
            } catch (e) {}
        }
        // 로그아웃 플래그 해제 (한 번의 페이지 로드에서만 적용)
        sessionStorage.removeItem('student_logged_out');

        // 3. 항상 랜딩 페이지 먼저 표시
        showView('login');
        loadLandingData();

        // 로그인 상태면 로그인 버튼 숨기고 하단 네비 표시
        if (isLoggedIn) {
            const loginCta = document.getElementById('landing-login-cta');
            if (loginCta) loginCta.classList.add('hidden');
            showBottomNav(true);
            updateBottomNav('home');
        }
    }

    function bindEvents() {
        // 랭킹 뒤로가기
        document.getElementById('btn-back-mypage').addEventListener('click', () => {
            if (isLoggedIn) {
                showView('mypage');
                updateBottomNav('mypage');
            } else {
                showView('login');
            }
        });

        // 하단 네비게이션 바
        document.querySelectorAll('#bottom-nav .bottom-nav-item').forEach(btn => {
            btn.addEventListener('click', () => handleBottomNav(btn.dataset.nav));
        });
    }

    // ============================================
    // 하단 네비게이션
    // ============================================
    function handleBottomNav(action) {
        switch (action) {
            case 'home':
                showView('login');
                loadLandingData();
                // 로그인 상태에서는 로그인 버튼 숨기기
                const loginCta = document.getElementById('landing-login-cta');
                if (loginCta) loginCta.classList.toggle('hidden', isLoggedIn);
                updateBottomNav('home');
                break;
            case 'mypage':
                loadMyPage();
                updateBottomNav('mypage');
                break;
            case 'ranking':
                loadRanking();
                updateBottomNav('ranking');
                break;
            case 'logout':
                doLogout();
                break;
        }
    }

    function updateBottomNav(active) {
        document.querySelectorAll('#bottom-nav .bottom-nav-item').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.nav === active);
        });
    }

    function showBottomNav(show) {
        document.getElementById('bottom-nav').classList.toggle('hidden', !show);
    }

    // ============================================
    // 뷰 전환
    // ============================================
    function showView(name) {
        currentView = name;
        ['loading', 'login', 'sibling', 'mypage', 'ranking'].forEach(v => {
            document.getElementById(`view-${v}`).classList.toggle('hidden', v !== name);
        });

        // 하단 네비 표시: 로그인 상태에서만
        showBottomNav(isLoggedIn);
    }

    // ============================================
    // 랜딩 페이지 데이터 로딩
    // ============================================
    async function loadLandingData() {
        const [rankResult, classResult] = await Promise.all([
            App.get('/api/ranking.php?action=overall&limit=5').catch(() => null),
            App.get('/api/student.php?action=classes').catch(() => null),
        ]);

        if (rankResult && rankResult.success) {
            const rankings = rankResult.rankings || [];
            animateNumber(document.getElementById('ls-students'), rankResult.total_students || 0);
            animateNumber(document.getElementById('ls-coins'), rankResult.total_coins || 0);
            renderLandingRanking(rankings);
        }

        if (classResult && classResult.success) {
            animateNumber(document.getElementById('ls-classes'), (classResult.classes || []).length);
        }
    }

    function renderLandingRanking(rankings) {
        const container = document.getElementById('landing-ranking');
        if (!rankings || rankings.length === 0) {
            container.innerHTML = '<div class="landing-ranking-loading">아직 도전한 친구가 없어. 첫 번째가 되어볼까?</div>';
            return;
        }

        const trophies = ['', '🏆', '🥈', '🥉'];

        container.innerHTML = rankings.map((r, i) => {
            const rank = i + 1;
            const colors = getAvatarColor(r.name);
            const rankClass = rank <= 3 ? ` rank-${rank}` : '';
            return `
                <div class="landing-ranking-item${rankClass}">
                    <div class="landing-rank-badge">${rank <= 3 ? trophies[rank] : rank}</div>
                    <div class="landing-rank-avatar" style="background:linear-gradient(135deg,${colors[0]},${colors[1]})">${r.name.charAt(0)}</div>
                    <div class="landing-rank-info">
                        <div class="landing-rank-name">${r.name}</div>
                        <div class="landing-rank-class">${r.class_name || ''}</div>
                    </div>
                    <div class="landing-rank-coins">${App.coinBadge(r.total_coins)}</div>
                </div>
            `;
        }).join('');
    }

    // ============================================
    // 로그아웃 (핑거프린트 비활성화)
    // ============================================
    async function doLogout() {
        App.confirm('로그아웃할까?', async () => {
            try {
                const fp = await DeviceFingerprint.generate();
                await App.post('/api/student.php?action=logout', {
                    fingerprint: fp,
                });
            } catch (e) {
                await App.post('/api/student.php?action=logout');
            }

            // 자동 로그인 방지 플래그 (페이지 새로고침 시 auto_login 스킵)
            sessionStorage.setItem('student_logged_out', '1');

            isLoggedIn = false;
            selectedClassId = null;
            myPageData = null;
            showBottomNav(false);
            showView('login');
            loadLandingData();
            const loginCta2 = document.getElementById('landing-login-cta');
            if (loginCta2) loginCta2.classList.remove('hidden');
            Toast.info('다음에 또 만나!');
        });
    }

    // ============================================
    // 형제 선택
    // ============================================
    function showSiblingSelect(students) {
        const list = document.getElementById('sibling-list');
        list.innerHTML = students.map(s => {
            const colors = getAvatarColor(s.name);
            return `
                <div class="sibling-item" data-student-id="${s.id}">
                    <div class="sibling-avatar" style="background:linear-gradient(135deg,${colors[0]},${colors[1]})">${s.name.charAt(0)}</div>
                    <div>
                        <div class="sibling-item-name">${s.name}</div>
                        <div class="sibling-item-class">${s.class_name || ''}</div>
                    </div>
                </div>
            `;
        }).join('');

        list.querySelectorAll('.sibling-item').forEach(item => {
            item.addEventListener('click', async () => {
                App.showLoading();
                const result = await App.post('/api/student.php?action=choose_student', {
                    student_id: parseInt(item.dataset.studentId)
                });
                App.hideLoading();
                if (result.success) {
                    isLoggedIn = true;
                    Toast.success(`${result.student.name}, 반가워!`);
                    await loadMyPage();
                }
            });
        });

        showView('sibling');
    }

    // ============================================
    // 마이페이지
    // ============================================
    async function loadMyPage() {
        const result = await App.get('/api/student.php?action=my_page');
        if (!result.success) {
            isLoggedIn = false;
            showView('login');
            loadLandingData();
            return;
        }

        myPageData = result;
        const { student, total_coins, class_rank, overall_rank, rewards } = result;

        // 아바타
        const colors = getAvatarColor(student.name);
        const avatarEl = document.getElementById('mp-avatar');
        avatarEl.textContent = student.name.charAt(0);
        avatarEl.style.background = `linear-gradient(135deg, ${colors[0]}, ${colors[1]})`;

        // 학생명
        document.getElementById('mp-name').textContent = student.name;

        // 태그
        const tagsHtml = [];
        if (student.class_name) tagsHtml.push(`<span class="mypage-tag">${student.class_name}</span>`);
        if (student.coach_name) tagsHtml.push(`<span class="mypage-tag">${student.coach_name} Coach</span>`);
        const badge = getProgressBadge(student.ace_current_level, student.bravo_current_level);
        if (badge) tagsHtml.push(badge);
        document.getElementById('mp-tags').innerHTML = tagsHtml.join('');

        // 코인 (카운트업 애니메이션)
        animateNumber(document.getElementById('mp-coins'), total_coins);

        // 랭킹
        const classRankEl = document.getElementById('mp-class-rank');
        const overallRankEl = document.getElementById('mp-overall-rank');

        classRankEl.innerHTML = class_rank
            ? `${App.getRankTrophy(class_rank)}<span>${class_rank}위</span>`
            : '-';
        overallRankEl.innerHTML = overall_rank
            ? `${App.getRankTrophy(overall_rank)}<span>${overall_rank}위</span>`
            : '-';

        // 카드 컬렉션
        const cardsHtml = rewards.map(r => `
            <div class="reward-card" data-code="${r.code}" data-color="${r.code}">
                <div class="reward-card-image">
                    <img src="/images/cards/${r.image_file}" alt="${r.name_ko}" loading="lazy">
                </div>
                <div class="reward-card-info">
                    <div class="reward-card-name" style="color:${r.color}">${r.name_ko}</div>
                    <div class="reward-card-coins">${r.coin_value}코인 / 장</div>
                    <div class="reward-card-hint">눌러서 기록 보기</div>
                </div>
                <div class="reward-card-count">
                    <div class="reward-card-count-number" style="color:${r.color}">${r.quantity}</div>
                    <div class="reward-card-count-label">장</div>
                </div>
            </div>
        `).join('');
        document.getElementById('mp-cards').innerHTML = cardsHtml;

        // 카드 클릭 이벤트
        document.querySelectorAll('.reward-card').forEach(card => {
            card.addEventListener('click', () => showCardHistory(card.dataset.code));
        });

        showView('mypage');
        updateBottomNav('mypage');
    }

    // 숫자 카운트업 애니메이션
    function animateNumber(el, target) {
        const duration = 1000;
        const start = 0;
        const startTime = performance.now();

        function update(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 4);
            const current = Math.round(start + (target - start) * eased);
            el.textContent = App.formatNumber(current);
            if (progress < 1) requestAnimationFrame(update);
        }

        requestAnimationFrame(update);
    }

    // ============================================
    // 카드 히스토리 (획득 날짜 태그 스타일)
    // ============================================
    async function showCardHistory(code) {
        App.showLoading();
        const result = await App.get(`/api/student.php?action=card_detail&code=${code}`);
        App.hideLoading();

        if (!result.success) return;

        const reward = myPageData.rewards.find(r => r.code === code);
        if (!reward) return;

        let html = `
            <div class="card-detail-header">
                <div class="card-detail-img-wrap" style="border-color:${reward.color}">
                    <img src="/images/cards/${reward.image_file}" alt="${reward.name_ko}">
                </div>
                <div class="card-detail-info">
                    <div class="card-detail-name" style="color:${reward.color}">${reward.name_ko}</div>
                    <div class="card-detail-meta">${reward.coin_value}코인 / 장</div>
                    <div class="card-detail-qty"><strong style="color:${reward.color};font-size:22px">${reward.quantity}</strong>장 보유</div>
                </div>
            </div>
        `;

        // 획득 날짜별 그룹핑 (양수만)
        const dateMap = {};
        result.history.forEach(h => {
            if (h.change_amount <= 0) return;
            const dateStr = h.created_at.substring(0, 10);
            dateMap[dateStr] = (dateMap[dateStr] || 0) + h.change_amount;
        });

        const dates = Object.entries(dateMap).sort((a, b) => b[0].localeCompare(a[0]));

        if (dates.length === 0) {
            html += `
                <div class="card-detail-empty">
                    <div class="card-detail-empty-icon">📭</div>
                    <div class="card-detail-empty-text">아직 기록이 없어</div>
                    <div class="card-detail-empty-hint">카드를 모아보자!</div>
                </div>
            `;
        } else {
            const tags = dates.map(([date, count]) => {
                const suffix = count > 1 ? ` *${count}개` : '';
                return `<span class="date-tag">${date}${suffix}</span>`;
            }).join('');

            html += `
                <div class="card-detail-dates">
                    <div class="card-detail-dates-title">획득 날짜</div>
                    <div class="card-detail-date-list">${tags}</div>
                </div>
            `;
        }

        App.openModal(reward.name_ko, html);
    }

    // ============================================
    // 전체 랭킹 (포디움 + 리스트)
    // ============================================
    async function loadRanking() {
        App.showLoading();
        const result = await App.get('/api/ranking.php?action=overall&limit=500');
        App.hideLoading();

        if (!result.success) return;

        const rankings = result.rankings || [];

        // 탑3 포디움
        const podium = document.getElementById('ranking-podium');
        const top3 = rankings.slice(0, 3);

        if (top3.length >= 3) {
            podium.innerHTML = top3.map((r, i) => {
                const rank = i + 1;
                const colors = getAvatarColor(r.name);
                const trophies = ['', '🏆', '🥈', '🥉'];
                return `
                    <div class="podium-item rank-${rank}">
                        <div class="podium-avatar" style="background:linear-gradient(135deg,${colors[0]},${colors[1]})">
                            ${r.name.charAt(0)}
                            <span class="podium-trophy">${trophies[rank]}</span>
                        </div>
                        <div class="podium-name">${r.name}</div>
                        <div class="podium-class">${r.class_name || ''}</div>
                        <div class="podium-progress">${getProgressBadge(r.ace_current_level, r.bravo_current_level)}</div>
                        <div class="podium-coins">${App.coinBadge(r.total_coins)}</div>
                        <div class="podium-bar">${rank}</div>
                    </div>
                `;
            }).join('');
            podium.style.display = '';
        } else {
            podium.style.display = 'none';
        }

        // 4위 이하 리스트
        const list = document.getElementById('ranking-list');
        const rest = rankings.slice(3);

        if (rest.length > 0) {
            list.innerHTML = rest.map(r => {
                const colors = getAvatarColor(r.name);
                return `
                    <div class="ranking-item">
                        <div class="ranking-rank">${r.rank}</div>
                        <div class="ranking-avatar" style="background:linear-gradient(135deg,${colors[0]},${colors[1]})">${r.name.charAt(0)}</div>
                        <div class="ranking-info">
                            <div class="ranking-name">${r.name}</div>
                            <div class="ranking-class">${r.class_name || ''}</div>
                        </div>
                        ${getProgressBadge(r.ace_current_level, r.bravo_current_level)}
                        <div class="ranking-coins">
                            ${App.coinBadge(r.total_coins)}
                        </div>
                    </div>
                `;
            }).join('');
        } else if (rankings.length === 0) {
            list.innerHTML = '<div class="empty-state" style="padding:48px 16px"><div class="empty-state-text">아직 도전한 친구가 없어. 첫 번째가 되어볼까?</div></div>';
        } else {
            list.innerHTML = '';
        }

        showView('ranking');
        updateBottomNav('ranking');
    }

    // ============================================
    // 시작
    // ============================================
    document.addEventListener('DOMContentLoaded', init);

    return { init, loadMyPage };
})();
