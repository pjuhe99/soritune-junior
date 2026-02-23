/**
 * 관리자 IP 전용 하단 독 메뉴
 * - 관리자 IP(settings의 system_auto_login_ips)에서만 표시
 * - 시스템관리 / 코치 / 학생화면 메뉴 + 슈퍼관리자 모드 토글
 * - 학생에게는 절대 보이지 않음
 *
 * 슈퍼관리자 모드 ON:
 *   - 학생화면 접속 시 자동으로 시스템관리 페이지로 이동
 *   - 시스템관리 클릭 시 IP 자동 로그인
 * 슈퍼관리자 모드 OFF:
 *   - 일반 사용자와 동일한 화면 (학생화면에서 자동 이동 안함)
 *   - 시스템관리 클릭 시 기존 세션 없으면 로그인 페이지로 이동
 *
 * 대행 로그인 패널: 시스템관리자 세션이 있으면 항상 표시 (슈퍼모드와 무관)
 */
(async function() {
    const SUPER_KEY = 'soritune_super_mode';
    const script = document.querySelector('script[src*="admin-dock"]');
    const activePage = script ? (script.getAttribute('data-adock-active') || 'student') : 'student';

    // 1. IP 확인
    let isAdminIp = false;
    try {
        const r = await (window.App || { get: (u) => fetch(u).then(r=>r.json()) }).get('/api/system.php?action=check_ip_auto_login');
        isAdminIp = r.success && r.allowed;
    } catch(e) {}

    if (!isAdminIp) return;

    const isSuperOn = () => localStorage.getItem(SUPER_KEY) === 'on';
    window.__superMode = isSuperOn();

    // 2. 슈퍼관리자 모드 ON + 학생페이지 → 시스템관리로 리다이렉트
    if (activePage === 'student' && isSuperOn()) {
        try {
            const sess = await App.api('/api/system.php?action=check_session', { showError: false });
            if (sess.logged_in) { window.location.href = '/system/'; return; }
            const login = await App.api('/api/system.php?action=ip_auto_login', { method: 'POST', showError: false });
            if (login.success) { window.location.href = '/system/'; return; }
        } catch(e) {}
    }

    // 3. 독 메뉴 생성
    const dock = document.createElement('div');
    dock.id = 'admin-dock';
    dock.className = 'adock';
    dock.innerHTML = `
        <div class="adock-bar">
            <a href="/system/" class="adock-item ${activePage==='system'?'adock-active':''}" id="adock-system">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                <span>시스템관리</span>
            </a>
            <a href="/coach/" class="adock-item ${activePage==='coach'?'adock-active':''}" id="adock-coach">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span>코치</span>
            </a>
            <a href="/teacher/" class="adock-item ${activePage==='teacher'?'adock-active':''}" id="adock-teacher">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/><line x1="9" y1="12" x2="15" y2="12"/><line x1="9" y1="16" x2="15" y2="16"/></svg>
                <span>관리쌤</span>
            </a>
            <a href="/admin/" class="adock-item ${activePage==='admin'?'adock-active':''}" id="adock-admin">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                <span>학부모</span>
            </a>
            <a href="/" class="adock-item ${activePage==='student'?'adock-active':''}" id="adock-student">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <span>학생화면</span>
            </a>
            <div class="adock-sep"></div>
            <label class="adock-auto" title="ON: 학생화면 접속 시 자동으로 시스템관리 이동">
                <input type="checkbox" id="adock-super-chk">
                <span class="adock-auto-track"><span class="adock-auto-thumb"></span></span>
                <span class="adock-auto-text">슈퍼</span>
            </label>
        </div>
    `;
    document.body.appendChild(dock);
    document.body.classList.add('has-adock');

    // 4. 슈퍼관리자 토글 상태 복원
    const chk = document.getElementById('adock-super-chk');
    chk.checked = isSuperOn();
    chk.addEventListener('change', () => {
        localStorage.setItem(SUPER_KEY, chk.checked ? 'on' : 'off');
        window.__superMode = chk.checked;
        const Toast = window.Toast;
        if (chk.checked) {
            if (Toast) Toast.success('슈퍼모드 ON - 다음 접속 시 자동으로 시스템관리');
        } else {
            if (Toast) Toast.info('슈퍼모드 OFF - 일반 사용자 화면');
        }
    });

    // 5. 시스템관리 클릭
    //    슈퍼모드 ON → 기존 세션 확인 후 IP 자동 로그인
    //    슈퍼모드 OFF → 기존 세션 있으면 이동, 없으면 로그인 페이지
    document.getElementById('adock-system').addEventListener('click', async (e) => {
        e.preventDefault();
        if (activePage === 'system') return;
        if (window.App) App.showLoading();
        try {
            // 기존 세션 확인 (슈퍼모드와 무관하게 세션 있으면 이동)
            const sess = await App.get('/api/system.php?action=check_session');
            if (sess.logged_in) { window.location.href = '/system/'; return; }

            // 슈퍼모드 ON일 때만 IP 자동 로그인 시도
            if (isSuperOn()) {
                const login = await App.post('/api/system.php?action=ip_auto_login');
                if (login.success) { window.location.href = '/system/'; return; }
            }

            // 세션 없음 → 로그인 페이지로 이동
            if (window.App) App.hideLoading();
            window.location.href = '/system/login.php';
        } catch(err) {
            if (window.App) App.hideLoading();
            window.location.href = '/system/login.php';
        }
    });

    // 6. 코치 클릭
    document.getElementById('adock-coach').addEventListener('click', (e) => {
        e.preventDefault();
        if (activePage === 'coach') return;
        window.location.href = '/coach/';
    });

    // 6-2. 관리쌤 클릭
    document.getElementById('adock-teacher').addEventListener('click', (e) => {
        e.preventDefault();
        if (activePage === 'teacher') return;
        window.location.href = '/teacher/';
    });

    // 6-3. 학부모 클릭
    document.getElementById('adock-admin').addEventListener('click', (e) => {
        e.preventDefault();
        if (activePage === 'admin') return;
        window.location.href = '/admin/';
    });

    // 7. 학생화면 클릭
    document.getElementById('adock-student').addEventListener('click', (e) => {
        e.preventDefault();
        if (activePage === 'student') {
            if (window.Toast) Toast.info('현재 학생 화면입니다');
            return;
        }
        window.location.href = '/';
    });
})();
