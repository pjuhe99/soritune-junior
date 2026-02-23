/**
 * 소리튠 주니어 영어학교 - 토스트 알림 시스템
 */
const Toast = (() => {
    let container = null;

    const ICONS = {
        success: '✓',
        error: '✕',
        warning: '!',
        info: 'i'
    };

    function init() {
        if (container) return;
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    function show(message, type = 'info', duration = 3000) {
        init();

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.style.position = 'relative';
        toast.innerHTML = `
            <div class="toast-icon">${ICONS[type] || ICONS.info}</div>
            <div class="toast-message">${message}</div>
            <div class="toast-close" onclick="this.parentElement.remove()">✕</div>
            ${duration > 0 ? `<div class="toast-progress" style="animation-duration: ${duration}ms"></div>` : ''}
        `;

        container.appendChild(toast);

        if (duration > 0) {
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.classList.add('removing');
                    setTimeout(() => toast.remove(), 300);
                }
            }, duration);
        }

        return toast;
    }

    return {
        success: (msg, duration) => show(msg, 'success', duration),
        error: (msg, duration) => show(msg, 'error', duration ?? 5000),
        warning: (msg, duration) => show(msg, 'warning', duration),
        info: (msg, duration) => show(msg, 'info', duration),
        show
    };
})();
