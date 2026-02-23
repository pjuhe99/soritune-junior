/**
 * ACE MediaRecorder 래퍼
 * 매 녹음마다 fresh stream을 사용하여 모바일 브라우저 호환성 보장
 */
const AceRecorder = (() => {
    let mediaRecorder = null;
    let audioChunks = [];
    let stream = null;
    let mimeType = '';

    const audioConstraints = {
        echoCancellation: true,
        noiseSuppression: true,
        autoGainControl: true,
    };

    function getSupportedMimeType() {
        const types = [
            'audio/webm;codecs=opus',
            'audio/webm',
            'audio/ogg;codecs=opus',
            'audio/mp4',
        ];
        for (const type of types) {
            if (typeof MediaRecorder !== 'undefined' && MediaRecorder.isTypeSupported(type)) return type;
        }
        return '';
    }

    /**
     * 초기 마이크 권한 확인 + MIME 타입 감지
     * 세션 시작 시 1회 호출
     */
    async function requestMic() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            throw new Error('이 브라우저에서는 마이크를 사용할 수 없습니다');
        }
        mimeType = getSupportedMimeType();
        if (!mimeType) throw new Error('이 브라우저에서는 오디오 녹음을 지원하지 않습니다');

        // 권한 확인용 — 스트림은 start()에서 새로 획득
        const testStream = await navigator.mediaDevices.getUserMedia({ audio: audioConstraints });
        testStream.getTracks().forEach(t => t.stop());
    }

    /**
     * 녹음 시작 — 매번 새 스트림 획득
     */
    async function start() {
        // 이전 리소스 정리
        releaseResources();

        // 새 스트림 획득
        stream = await navigator.mediaDevices.getUserMedia({ audio: audioConstraints });

        audioChunks = [];
        mediaRecorder = new MediaRecorder(stream, { mimeType });
        mediaRecorder.ondataavailable = (e) => {
            if (e.data.size > 0) audioChunks.push(e.data);
        };
        mediaRecorder.start();
    }

    /**
     * 녹음 정지 → Blob 반환 + 리소스 해제
     */
    function stop() {
        return new Promise((resolve, reject) => {
            if (!mediaRecorder || mediaRecorder.state === 'inactive') {
                reject(new Error('녹음 중이 아닙니다'));
                return;
            }
            mediaRecorder.onstop = () => {
                const blob = new Blob(audioChunks, { type: mimeType });
                audioChunks = [];
                releaseResources();
                resolve(blob);
            };
            mediaRecorder.onerror = (e) => {
                releaseResources();
                reject(e);
            };
            mediaRecorder.stop();
        });
    }

    function releaseResources() {
        if (mediaRecorder) {
            mediaRecorder.ondataavailable = null;
            mediaRecorder.onstop = null;
            mediaRecorder.onerror = null;
            if (mediaRecorder.state !== 'inactive') {
                try { mediaRecorder.stop(); } catch (e) {}
            }
            mediaRecorder = null;
        }
        if (stream) {
            stream.getTracks().forEach(t => t.stop());
            stream = null;
        }
    }

    function isRecording() {
        return mediaRecorder && mediaRecorder.state === 'recording';
    }

    function getMimeType() {
        return mimeType;
    }

    function cleanup() {
        releaseResources();
        audioChunks = [];
    }

    return { requestMic, start, stop, isRecording, getMimeType, getSupportedMimeType, cleanup };
})();
