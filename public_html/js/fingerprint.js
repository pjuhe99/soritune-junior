/**
 * 소리튠 주니어 영어학교 - 디바이스 핑거프린트 생성
 * Canvas + WebGL + 화면정보 + 시스템정보 조합
 */
const DeviceFingerprint = (() => {
    async function generate() {
        const components = [];

        // Screen info
        components.push(`${screen.width}x${screen.height}`);
        components.push(`${screen.colorDepth}`);
        components.push(`${window.devicePixelRatio || 1}`);

        // Timezone
        components.push(Intl.DateTimeFormat().resolvedOptions().timeZone || '');

        // Languages
        components.push((navigator.languages || [navigator.language]).join(','));

        // Platform
        components.push(navigator.platform || '');

        // Hardware concurrency
        components.push(`${navigator.hardwareConcurrency || 0}`);

        // Touch support
        components.push(`${navigator.maxTouchPoints || 0}`);

        // Canvas fingerprint
        try {
            const canvas = document.createElement('canvas');
            canvas.width = 200;
            canvas.height = 50;
            const ctx = canvas.getContext('2d');
            ctx.textBaseline = 'top';
            ctx.font = '14px Arial';
            ctx.fillStyle = '#f60';
            ctx.fillRect(125, 1, 62, 20);
            ctx.fillStyle = '#069';
            ctx.fillText('SoriTune Junior', 2, 15);
            ctx.fillStyle = 'rgba(102, 204, 0, 0.7)';
            ctx.fillText('SoriTune Junior', 4, 17);
            components.push(canvas.toDataURL().slice(-50));
        } catch (e) {
            components.push('canvas-error');
        }

        // WebGL info
        try {
            const canvas = document.createElement('canvas');
            const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
            if (gl) {
                const debugInfo = gl.getExtension('WEBGL_debug_renderer_info');
                if (debugInfo) {
                    components.push(gl.getParameter(debugInfo.UNMASKED_VENDOR_WEBGL) || '');
                    components.push(gl.getParameter(debugInfo.UNMASKED_RENDERER_WEBGL) || '');
                }
            }
        } catch (e) {
            components.push('webgl-error');
        }

        // Generate hash
        const raw = components.join('|');
        const hash = await sha256(raw);
        return hash;
    }

    async function sha256(message) {
        if (window.crypto && window.crypto.subtle) {
            const msgBuffer = new TextEncoder().encode(message);
            const hashBuffer = await crypto.subtle.digest('SHA-256', msgBuffer);
            const hashArray = Array.from(new Uint8Array(hashBuffer));
            return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
        }
        // Fallback: simple hash
        let hash = 0;
        for (let i = 0; i < message.length; i++) {
            const char = message.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash;
        }
        return Math.abs(hash).toString(16).padStart(16, '0');
    }

    function getDeviceInfo() {
        return {
            userAgent: navigator.userAgent,
            screen: `${screen.width}x${screen.height}`,
            pixelRatio: window.devicePixelRatio || 1,
            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || '',
            language: navigator.language,
            platform: navigator.platform || '',
            touchPoints: navigator.maxTouchPoints || 0,
        };
    }

    return { generate, getDeviceInfo };
})();
