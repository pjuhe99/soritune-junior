/**
 * 소리튠 주니어 영어학교 - 공통 JS
 * API 헬퍼, 세션 체크, 유틸리티
 */

const App = (() => {
    const BASE_URL = '';

    /**
     * API 호출 헬퍼
     */
    async function api(endpoint, options = {}) {
        const { method = 'GET', data = null, showError = true } = options;

        const fetchOptions = {
            method,
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin',
        };

        let url = `${BASE_URL}${endpoint}`;

        if (data && method !== 'GET') {
            if (data instanceof FormData) {
                fetchOptions.body = data;
            } else {
                fetchOptions.headers['Content-Type'] = 'application/json';
                fetchOptions.body = JSON.stringify(data);
            }
        }

        if (data && method === 'GET') {
            const params = new URLSearchParams(data);
            url += (url.includes('?') ? '&' : '?') + params.toString();
        }

        try {
            const response = await fetch(url, fetchOptions);
            const result = await response.json();

            if (!response.ok || result.success === false) {
                if (response.status === 401) {
                    // 세션 만료
                    Toast.warning('다시 로그인해줘!');
                    return result;
                }
                if (showError && result.error) {
                    Toast.error(result.error);
                }
                return result;
            }

            return result;
        } catch (err) {
            if (showError) {
                Toast.error('📡 연결이 안 돼! 다시 해보자.');
            }
            console.error('API Error:', err);
            return { success: false, error: err.message };
        }
    }

    /**
     * GET 요청
     */
    function get(endpoint, params = null) {
        return api(endpoint, { method: 'GET', data: params });
    }

    /**
     * POST 요청
     */
    function post(endpoint, data = null) {
        return api(endpoint, { method: 'POST', data });
    }

    /**
     * 로딩 오버레이
     */
    function showLoading() {
        let overlay = document.getElementById('app-loading');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'app-loading';
            overlay.className = 'loading-overlay';
            overlay.innerHTML = '<div class="loading-spinner"></div>';
            document.body.appendChild(overlay);
        }
        overlay.style.display = 'flex';
    }

    function hideLoading() {
        const overlay = document.getElementById('app-loading');
        if (overlay) overlay.style.display = 'none';
    }

    /**
     * 날짜 포맷
     */
    function formatDate(date, format = 'YYYY-MM-DD') {
        const d = date instanceof Date ? date : new Date(date);
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        const h = String(d.getHours()).padStart(2, '0');
        const min = String(d.getMinutes()).padStart(2, '0');

        return format
            .replace('YYYY', y)
            .replace('MM', m)
            .replace('DD', day)
            .replace('HH', h)
            .replace('mm', min);
    }

    /**
     * 한국어 날짜
     */
    function formatDateKo(date) {
        const d = date instanceof Date ? date : new Date(date);
        const month = d.getMonth() + 1;
        const day = d.getDate();
        const weekdays = ['일', '월', '화', '수', '목', '금', '토'];
        const weekday = weekdays[d.getDay()];
        return `${month}월 ${day}일 (${weekday})`;
    }

    /**
     * 숫자 포맷 (1,000)
     */
    function formatNumber(num) {
        return Number(num).toLocaleString('ko-KR');
    }

    /**
     * 등수 트로피 이모지
     */
    function getRankTrophy(rank) {
        if (rank === 1) return '<span class="trophy trophy-gold">🏆</span>';
        if (rank === 2) return '<span class="trophy trophy-silver">🥈</span>';
        if (rank === 3) return '<span class="trophy trophy-bronze">🥉</span>';
        return '';
    }

    /**
     * 코인 뱃지 HTML
     */
    function coinBadge(coins, size = '') {
        const cls = size === 'lg' ? 'coin-badge-lg' : '';
        return `<span class="coin-badge ${cls}">
            <span class="coin-icon">C</span>
            <span class="coin-number">${formatNumber(coins)}</span>
        </span>`;
    }

    /**
     * 카드 색상
     */
    const CARD_COLORS = {
        steady: '#4CAF50',
        leader: '#2196F3',
        mission: '#FF9800',
        posture: '#9C27B0',
        passion: '#F44336',
        reboot: '#00BCD4',
    };

    /**
     * Element 생성 헬퍼
     */
    function el(tag, attrs = {}, children = []) {
        const element = document.createElement(tag);
        for (const [key, val] of Object.entries(attrs)) {
            if (key === 'className') element.className = val;
            else if (key === 'innerHTML') element.innerHTML = val;
            else if (key === 'textContent') element.textContent = val;
            else if (key === 'onclick') element.onclick = val;
            else if (key === 'style' && typeof val === 'object') Object.assign(element.style, val);
            else element.setAttribute(key, val);
        }
        for (const child of children) {
            if (typeof child === 'string') element.appendChild(document.createTextNode(child));
            else if (child) element.appendChild(child);
        }
        return element;
    }

    /**
     * 탭 초기화
     */
    function initTabs(container) {
        const tabBtns = container.querySelectorAll('.tab-btn');
        const tabContents = container.querySelectorAll('.tab-content');

        tabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const target = btn.dataset.tab;

                tabBtns.forEach(b => b.classList.remove('active'));
                tabContents.forEach(c => c.classList.remove('active'));

                btn.classList.add('active');
                const content = container.querySelector(`#tab-${target}`);
                if (content) content.classList.add('active');

                // Custom event
                container.dispatchEvent(new CustomEvent('tabChange', { detail: { tab: target } }));
            });
        });
    }

    /**
     * 모달 열기/닫기
     */
    function openModal(title, content, options = {}) {
        const backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop';
        backdrop.innerHTML = `
            <div class="modal">
                <div class="modal-header">
                    <h3 class="modal-title">${title}</h3>
                    <button class="modal-close" data-close>✕</button>
                </div>
                <div class="modal-body">${typeof content === 'string' ? content : ''}</div>
            </div>
        `;

        if (typeof content !== 'string') {
            backdrop.querySelector('.modal-body').appendChild(content);
        }

        // Close handlers
        backdrop.querySelector('[data-close]').onclick = () => closeModal(backdrop);
        backdrop.addEventListener('click', (e) => {
            if (e.target === backdrop) closeModal(backdrop);
        });

        document.body.appendChild(backdrop);
        document.body.style.overflow = 'hidden';

        return backdrop;
    }

    function closeModal(backdrop) {
        if (!backdrop) return;
        backdrop.querySelector('.modal').style.animation = 'slideDown 0.2s ease reverse';
        backdrop.style.animation = 'fadeOut 0.2s ease';
        setTimeout(() => {
            backdrop.remove();
            if (!document.querySelector('.modal-backdrop')) {
                document.body.style.overflow = '';
            }
        }, 200);
    }

    /**
     * Confirm 다이얼로그
     */
    function confirm(message, onConfirm, options) {
        const formal = options?.formal;
        const cancelText = formal ? '취소' : '안 할래';
        const okText = formal ? '확인' : '알겠어!';
        const content = `
            <p style="margin-bottom: 20px; text-align: center;">${message}</p>
            <div style="display: flex; gap: 8px;">
                <button class="btn btn-secondary btn-block" data-close>${cancelText}</button>
                <button class="btn btn-primary btn-block" id="confirm-ok">${okText}</button>
            </div>
        `;
        const modal = openModal('확인', content);
        modal.querySelector('#confirm-ok').onclick = () => {
            closeModal(modal);
            if (onConfirm) onConfirm();
        };
    }

    /**
     * 디바운스
     */
    function debounce(fn, delay = 300) {
        let timer;
        return (...args) => {
            clearTimeout(timer);
            timer = setTimeout(() => fn(...args), delay);
        };
    }

    return {
        api, get, post,
        showLoading, hideLoading,
        formatDate, formatDateKo, formatNumber,
        getRankTrophy, coinBadge,
        CARD_COLORS,
        el, initTabs,
        openModal, closeModal, confirm,
        debounce,
    };
})();
