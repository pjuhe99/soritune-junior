<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#FF6B1A">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>소리튠 주니어 영어학교</title>
    <link rel="icon" type="image/svg+xml" href="/images/favicon.svg">
    <link rel="apple-touch-icon" href="/images/favicon.svg">
    <meta property="og:title" content="소리튠 주니어 영어학교">
    <meta property="og:description" content="영어를 소리로 배우는 주니어 영어학교 - 카드를 모아 코인 랭킹에 도전해봐!">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://j.soritune.com">
    <meta property="og:site_name" content="SoriTune Junior">
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/variable/pretendardvariable-dynamic-subset.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/css/common.css?v=20260220d">
    <link rel="stylesheet" href="/css/toast.css?v=20260220d">
    <link rel="stylesheet" href="/css/student.css?v=20260220e">
</head>
<body class="student-page">
    <div class="app-container" id="app">
        <!-- 로딩 -->
        <div id="view-loading" class="view-loading">
            <div class="loading-brand">
                <div class="loading-logo-ring">
                    <div class="loading-logo-inner">S</div>
                </div>
                <div class="loading-brand-text">SoriTune Junior</div>
                <div class="loading-brand-sub">English Academy</div>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- 랜딩 페이지 (비로그인 메인 / 로그인 홈) -->
        <!-- ============================================ -->
        <div id="view-login" class="hidden">
            <!-- 히어로 섹션 -->
            <div class="landing-hero">
                <div class="landing-hero-bg">
                    <div class="landing-particle p1"></div>
                    <div class="landing-particle p2"></div>
                    <div class="landing-particle p3"></div>
                    <div class="landing-particle p4"></div>
                    <div class="landing-particle p5"></div>
                </div>
                <div class="landing-hero-content">
                    <div class="landing-logo-wrap">
                        <div class="landing-logo">
                            <span>S</span>
                        </div>
                    </div>
                    <h1 class="landing-title">SoriTune <span>Junior</span></h1>
                    <p class="landing-desc">영어를 소리로 배우는 주니어 영어학교</p>
                    <div class="landing-stats" id="landing-stats">
                        <div class="landing-stat">
                            <div class="landing-stat-num" id="ls-students">-</div>
                            <div class="landing-stat-label">친구들</div>
                        </div>
                        <div class="landing-stat-divider"></div>
                        <div class="landing-stat">
                            <div class="landing-stat-num" id="ls-coins">-</div>
                            <div class="landing-stat-label">코인 획득</div>
                        </div>
                        <div class="landing-stat-divider"></div>
                        <div class="landing-stat">
                            <div class="landing-stat-num" id="ls-classes">-</div>
                            <div class="landing-stat-label">반</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 로그인 버튼 (비로그인 시에만 표시) -->
            <div class="landing-login-btn-wrap" id="landing-login-cta">
                <a href="/login.php" class="landing-login-btn">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:middle;margin-right:6px;margin-top:-2px;"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>로그인
                </a>
            </div>

            <!-- 실시간 랭킹 미리보기 -->
            <div class="landing-section">
                <div class="landing-section-header">
                    <div class="landing-section-icon">🏆</div>
                    <div>
                        <div class="landing-section-title">이번 주 TOP 랭커</div>
                        <div class="landing-section-sub">지금 가장 많은 코인을 모은 친구들!</div>
                    </div>
                </div>
                <div class="landing-ranking" id="landing-ranking">
                    <div class="landing-ranking-loading">잠깐만...</div>
                </div>
            </div>

            <!-- 카드 컬렉션 소개 -->
            <div class="landing-section">
                <div class="landing-section-header">
                    <div class="landing-section-icon">🃏</div>
                    <div>
                        <div class="landing-section-title">7종 카드를 모아봐!</div>
                        <div class="landing-section-sub">여러 활동으로 카드를 받을 수 있어!</div>
                    </div>
                </div>
                <div class="landing-cards" id="landing-cards">
                    <div class="landing-card-item" style="--card-color:#4CAF50" onclick="this.classList.toggle('flipped')" title="소리과제를 꾸준히 제출하면 받는 카드">
                        <div class="landing-card-front">
                            <img src="/images/cards/steady.png" alt="꾸준왕" loading="lazy">
                            <div class="landing-card-name">꾸준왕</div>
                            <div class="landing-card-coin">5코인</div>
                        </div>
                        <div class="landing-card-back" style="background:#4CAF50;">
                            <div class="landing-card-back-title">꾸준왕</div>
                            <div class="landing-card-back-desc">소리과제를 꾸준히 제출하면 받는 카드!</div>
                            <div class="landing-card-back-coin">5코인</div>
                            <div class="landing-card-back-coin-limit">일주일 최대 1장</div>
                        </div>
                    </div>
                    <div class="landing-card-item" style="--card-color:#2196F3" onclick="this.classList.toggle('flipped')" title="밴드에서 리스닝 과제 댓글 및 응원 댓글수 top 10">
                        <div class="landing-card-front">
                            <img src="/images/cards/leader.png" alt="리더왕" loading="lazy">
                            <div class="landing-card-name">리더왕</div>
                            <div class="landing-card-coin">2코인</div>
                        </div>
                        <div class="landing-card-back" style="background:#2196F3;">
                            <div class="landing-card-back-title">리더왕</div>
                            <div class="landing-card-back-desc">밴드에서 리스닝 과제 댓글 및 응원 댓글수 TOP 10에 들면 받는 카드!</div>
                            <div class="landing-card-back-coin">2코인</div>
                            <div class="landing-card-back-coin-limit">일주일 최대 1장</div>
                        </div>
                    </div>
                    <div class="landing-card-item" style="--card-color:#FF9800" onclick="this.classList.toggle('flipped')" title="매일 밴드에 올라오는 생활미션 할때마다 발급">
                        <div class="landing-card-front">
                            <img src="/images/cards/mission.png" alt="미션왕" loading="lazy">
                            <div class="landing-card-name">미션왕</div>
                            <div class="landing-card-coin">1코인</div>
                        </div>
                        <div class="landing-card-back" style="background:#FF9800;">
                            <div class="landing-card-back-title">미션왕</div>
                            <div class="landing-card-back-desc">매일 밴드에 올라오는 생활미션을 할 때마다 받는 카드!</div>
                            <div class="landing-card-back-coin">1코인</div>
                            <div class="landing-card-back-coin-limit">일주일 최대 5장</div>
                        </div>
                    </div>
                    <div class="landing-card-item" style="--card-color:#9C27B0" onclick="this.classList.toggle('flipped')" title="줌수업 중 바른 자세를 유지하면 받는 카드">
                        <div class="landing-card-front">
                            <img src="/images/cards/posture.png" alt="바른자세왕" loading="lazy">
                            <div class="landing-card-name">바른자세왕</div>
                            <div class="landing-card-coin">1코인</div>
                        </div>
                        <div class="landing-card-back" style="background:#9C27B0;">
                            <div class="landing-card-back-title">바른자세왕</div>
                            <div class="landing-card-back-desc">줌수업 중 바른 자세를 유지하면 받는 카드!</div>
                            <div class="landing-card-back-coin">1코인</div>
                            <div class="landing-card-back-coin-limit">일주일 최대 5장</div>
                        </div>
                    </div>
                    <div class="landing-card-item" style="--card-color:#F44336" onclick="this.classList.toggle('flipped')" title="줌수업에 열정적으로 참여하면 받는 카드">
                        <div class="landing-card-front">
                            <img src="/images/cards/passion.png" alt="열정왕" loading="lazy">
                            <div class="landing-card-name">열정왕</div>
                            <div class="landing-card-coin">1코인</div>
                        </div>
                        <div class="landing-card-back" style="background:#F44336;">
                            <div class="landing-card-back-title">열정왕</div>
                            <div class="landing-card-back-desc">줌수업에 열정적으로 참여하면 받는 카드!</div>
                            <div class="landing-card-back-coin">1코인</div>
                            <div class="landing-card-back-coin-limit">일주일 최대 5장</div>
                        </div>
                    </div>
                    <div class="landing-card-item" style="--card-color:#00BCD4" onclick="this.classList.toggle('flipped')" title="3일 이상 쉬었다가 다시 과제를 제출하면 받는 카드">
                        <div class="landing-card-front">
                            <img src="/images/cards/reboot.jpg" alt="리부트" loading="lazy">
                            <div class="landing-card-name">리부트</div>
                            <div class="landing-card-coin">2코인</div>
                        </div>
                        <div class="landing-card-back" style="background:#00BCD4;">
                            <div class="landing-card-back-title">리부트</div>
                            <div class="landing-card-back-desc">3일 이상 쉬었다가 다시 과제를 제출하면 받는 복귀 카드!</div>
                            <div class="landing-card-back-coin">2코인</div>
                            <div class="landing-card-back-coin-limit">일주일 최대 1장</div>
                        </div>
                    </div>
                    <div class="landing-card-item" style="--card-color:#F9A825" onclick="this.classList.toggle('flipped')" title="ACE/BRAVO 도전에서 성공하면 받는 카드">
                        <div class="landing-card-front">
                            <img src="/images/cards/challenge.jpg" alt="도전왕" loading="lazy">
                            <div class="landing-card-name">도전왕</div>
                            <div class="landing-card-coin">3코인</div>
                        </div>
                        <div class="landing-card-back" style="background:#F9A825;">
                            <div class="landing-card-back-title">도전왕</div>
                            <div class="landing-card-back-desc">ACE/BRAVO 도전에서 성공하면 받는 카드!</div>
                            <div class="landing-card-back-coin">3코인</div>
                            <div class="landing-card-back-coin-limit">일주일 최대 1장</div>
                        </div>
                    </div>
                </div>
                <div class="landing-cards-nav-bar">
                    <button class="landing-cards-nav" id="cards-prev" aria-label="이전">&#8249;</button>
                    <div class="landing-cards-dots" id="cards-dots">
                        <span class="landing-cards-dot active"></span>
                        <span class="landing-cards-dot"></span>
                        <span class="landing-cards-dot"></span>
                        <span class="landing-cards-dot"></span>
                        <span class="landing-cards-dot"></span>
                        <span class="landing-cards-dot"></span>
                        <span class="landing-cards-dot"></span>
                    </div>
                    <button class="landing-cards-nav" id="cards-next" aria-label="다음">&#8250;</button>
                </div>
                <p class="landing-cards-hint">카드를 눌러서 확인해봐!</p>
            </div>

            <!-- 푸터 -->
            <div class="landing-footer">
                <div class="landing-footer-logo">SoriTune Junior English Academy</div>
                <div class="landing-footer-copy">Empowering Kids Through Sound</div>
            </div>
        </div>

        <!-- 형제 선택 -->
        <div id="view-sibling" class="hidden">
            <div class="login-hero login-hero-compact">
                <div class="hero-decoration">
                    <div class="hero-circle hero-circle-1"></div>
                    <div class="hero-circle hero-circle-2"></div>
                </div>
                <div class="hero-content">
                    <div class="hero-logo">
                        <div class="hero-logo-icon">S</div>
                    </div>
                    <h1 class="hero-title">누구로 접속할까?</h1>
                </div>
            </div>
            <div class="login-body">
                <div class="sibling-list" id="sibling-list"></div>
            </div>
        </div>

        <!-- 마이페이지 -->
        <div id="view-mypage" class="hidden">
            <div class="mypage-hero">
                <div class="mypage-hero-bg">
                    <div class="hero-circle hero-circle-1"></div>
                    <div class="hero-circle hero-circle-2"></div>
                    <div class="hero-circle hero-circle-3"></div>
                </div>
                <div class="mypage-hero-top">
                    <div class="mypage-brand">SoriTune Junior</div>
                    <div style="width:36px"></div>
                </div>
                <div class="mypage-profile">
                    <div class="mypage-avatar" id="mp-avatar">S</div>
                    <div class="mypage-student-name" id="mp-name"></div>
                    <div class="mypage-tags" id="mp-tags"></div>
                </div>
                <div class="mypage-coin-card">
                    <div class="coin-card-glow"></div>
                    <div class="coin-card-content">
                        <div class="coin-card-icon"><span>C</span></div>
                        <div class="coin-card-info">
                            <div class="coin-card-label">My Coins</div>
                            <div class="coin-card-value" id="mp-coins">0</div>
                        </div>
                    </div>
                </div>
                <div class="mypage-rankings">
                    <div class="mypage-rank-card">
                        <div class="rank-card-label">반 랭킹</div>
                        <div class="rank-card-value" id="mp-class-rank">-</div>
                    </div>
                    <div class="rank-divider"></div>
                    <div class="mypage-rank-card">
                        <div class="rank-card-label">전체 랭킹</div>
                        <div class="rank-card-value" id="mp-overall-rank">-</div>
                    </div>
                </div>
            </div>
            <div class="mypage-body">
                <!-- ACE 도전 바로가기 -->
                <a href="/ace/" id="ace-shortcut" style="display:block; text-decoration:none; margin:0 0 16px; padding:14px 18px; background:linear-gradient(135deg,#FF5722,#FF9800); border-radius:16px; color:#fff; box-shadow:0 4px 16px rgba(255,87,34,.2);">
                    <div style="display:flex; align-items:center; gap:12px;">
                        <div style="font-size:32px;">🎤</div>
                        <div style="flex:1;">
                            <div style="font-size:16px; font-weight:800;">ACE/BRAVO 도전하기</div>
                            <div style="font-size:12px; opacity:0.85; margin-top:2px;">영어 소리 성장 도전</div>
                        </div>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                    </div>
                </a>

                <div class="card-collection">
                    <div class="card-collection-header">
                        <div class="card-collection-title">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#FF6B1A" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                            나의 카드 컬렉션
                        </div>
                    </div>
                    <div class="card-list" id="mp-cards"></div>
                </div>
                <div class="mypage-footer">
                    <div class="footer-logo">SoriTune Junior English Academy</div>
                </div>
            </div>
        </div>

        <!-- 전체 랭킹 -->
        <div id="view-ranking" class="hidden ranking-page">
            <div class="ranking-header">
                <button class="ranking-back-btn" id="btn-back-mypage">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                </button>
                <h1 class="ranking-header-title">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20V10"/><path d="M18 20V4"/><path d="M6 20v-4"/></svg>
                    전체 랭킹
                </h1>
                <div style="width:36px"></div>
            </div>
            <div class="ranking-body">
                <div class="ranking-podium" id="ranking-podium"></div>
                <div class="ranking-list" id="ranking-list"></div>
            </div>
        </div>

        <!-- 하단 네비게이션 바 -->
        <nav id="bottom-nav" class="bottom-nav hidden">
            <button class="bottom-nav-item" data-nav="home">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                <span>홈</span>
            </button>
            <button class="bottom-nav-item active" data-nav="mypage">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M20 21a8 8 0 0 0-16 0"/></svg>
                <span>마이</span>
            </button>
            <button class="bottom-nav-item" data-nav="ranking">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20V10"/><path d="M18 20V4"/><path d="M6 20v-4"/></svg>
                <span>랭킹</span>
            </button>
            <button class="bottom-nav-item" data-nav="logout">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                <span>나가기</span>
            </button>
        </nav>
    </div>

    <link rel="stylesheet" href="/css/admin-dock.css?v=20260220d">
    <script src="/js/toast.js?v=20260220d"></script>
    <script src="/js/fingerprint.js?v=20260220d"></script>
    <script src="/js/common.js?v=20260220d"></script>
    <script>
    // 시스템관리자 학생 대행 로그인 (시스템관리자 세션이 있으면 항상 표시)
    (async () => {
        try {
            const r = await App.get('/api/system.php?action=check_session');
            if (!r.logged_in) return;
            // CTA 영역 아래에 대행 패널 삽입
            const cta = document.getElementById('landing-cta');
            if (!cta) return;
            const panel = document.createElement('div');
            panel.id = 'admin-impersonate';
            panel.style.cssText = 'max-width:480px; margin:0 auto 24px; padding:0 16px;';
            panel.innerHTML = `
                <div style="background:linear-gradient(135deg,#E8F5E9,#C8E6C9); border:1.5px solid #A5D6A7; border-radius:14px; padding:14px; margin-bottom:12px;">
                    <div style="font-weight:700; font-size:14px; color:#2E7D32;">시스템관리자 모드</div>
                    <div style="font-size:12px; color:#4CAF50;">반을 선택하고 학생을 클릭하면 해당 학생으로 로그인됩니다</div>
                </div>
                <select id="imp-class-select" style="width:100%; padding:10px 14px; border:1.5px solid #E0E0E0; border-radius:12px; font-size:14px; margin-bottom:8px; font-family:inherit;">
                    <option value="">반을 선택하세요</option>
                </select>
                <div id="imp-student-list" style="display:flex; flex-direction:column; gap:6px; max-height:300px; overflow-y:auto;"></div>
            `;
            cta.parentNode.insertBefore(panel, cta);

            // 반 목록 로드
            const cr = await App.get('/api/system.php?action=classes');
            if (cr.success && cr.classes) {
                const sel = document.getElementById('imp-class-select');
                cr.classes.forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.id;
                    opt.textContent = c.display_name + ' (' + c.student_count + '명)';
                    sel.appendChild(opt);
                });
                sel.addEventListener('change', async () => {
                    const cid = sel.value;
                    const list = document.getElementById('imp-student-list');
                    if (!cid) { list.innerHTML = ''; return; }
                    const sr = await App.get('/api/system.php?action=students_by_class', { class_id: cid });
                    if (sr.success && sr.students) {
                        list.innerHTML = sr.students.map(s => `
                            <button onclick="impersonateStudent(${s.id})" style="display:flex; align-items:center; gap:10px; width:100%; padding:10px 12px; border:1.5px solid #FFF3E0; border-radius:10px; background:#fff; cursor:pointer; text-align:left; transition:all .15s; font-family:inherit;">
                                <div style="width:32px; height:32px; background:#FF7E17; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; color:#fff; font-size:13px; flex-shrink:0;">${s.name.charAt(0)}</div>
                                <div style="flex:1;">
                                    <div style="font-weight:600; font-size:13px; color:#333;">${s.name}</div>
                                    <div style="font-size:11px; color:#999;">${s.grade ? s.grade + '세' : ''} ${s.phone_last4 ? '***-' + s.phone_last4 : ''}</div>
                                </div>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#ccc" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                            </button>
                        `).join('');
                    }
                });
            }
        } catch(e) {}
    })();

    async function impersonateStudent(studentId) {
        App.showLoading();
        const result = await App.post('/api/system.php?action=impersonate_student', { student_id: studentId });
        App.hideLoading();
        if (result.success) {
            Toast.success(result.message);
            setTimeout(() => location.reload(), 500);
        }
    }
    </script>
    <script>
    // 카드 좌우 네비게이션 + dot indicator
    (function() {
        const cards = document.getElementById('landing-cards');
        const prev = document.getElementById('cards-prev');
        const next = document.getElementById('cards-next');
        const dots = document.querySelectorAll('#cards-dots .landing-cards-dot');
        if (!cards || !prev || !next) return;

        const items = cards.querySelectorAll('.landing-card-item');
        const step = 112; // card width(100) + gap(12)

        function getActiveIndex() {
            var maxScroll = cards.scrollWidth - cards.clientWidth;
            if (maxScroll <= 0) return 0;
            var pct = cards.scrollLeft / maxScroll;
            return Math.round(pct * (items.length - 1));
        }

        function updateDots() {
            const idx = getActiveIndex();
            dots.forEach(function(dot, i) {
                dot.classList.toggle('active', i === idx);
            });
        }

        prev.addEventListener('click', function() {
            cards.scrollBy({ left: -step, behavior: 'smooth' });
        });
        next.addEventListener('click', function() {
            cards.scrollBy({ left: step, behavior: 'smooth' });
        });

        cards.addEventListener('scroll', updateDots, { passive: true });
        updateDots();
    })();
    </script>
    <script src="/js/student.js?v=20260220d"></script>
    <script src="/js/admin-dock.js?v=20260220c" data-adock-active="student"></script>
</body>
</html>
