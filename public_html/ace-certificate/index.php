<?php
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#673AB7">
    <title>성장 인증서 - 소리튠 주니어</title>
    <link rel="icon" type="image/svg+xml" href="/images/favicon.svg">
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/variable/pretendardvariable-dynamic-subset.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Pretendard Variable', -apple-system, sans-serif;
            background: #F5F5F5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px 16px;
        }

        #loading {
            text-align: center;
            padding: 60px 20px;
        }
        .spinner {
            width: 32px; height: 32px;
            border: 3px solid #E0E0E0; border-top-color: #673AB7;
            border-radius: 50%; animation: spin .8s linear infinite;
            margin: 0 auto 12px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* 인증서 */
        #certificate {
            width: 360px;
            background: #fff;
            border-radius: 0;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(0,0,0,.1);
        }

        .cert-border {
            border: 6px solid #673AB7;
            border-radius: 4px;
            margin: 12px;
            padding: 24px 20px;
            position: relative;
        }
        .cert-border::before {
            content: '';
            position: absolute;
            top: 4px; left: 4px; right: 4px; bottom: 4px;
            border: 1.5px solid #D1C4E9;
            border-radius: 2px;
            pointer-events: none;
        }

        .cert-header {
            text-align: center;
            margin-bottom: 20px;
        }
        .cert-logo {
            width: 40px; height: 40px;
            background: linear-gradient(135deg, #673AB7, #9C27B0);
            border-radius: 10px;
            display: inline-flex;
            align-items: center; justify-content: center;
            color: #fff; font-weight: 900; font-size: 18px;
            margin-bottom: 8px;
        }
        .cert-title {
            font-size: 22px;
            font-weight: 900;
            color: #673AB7;
            margin-bottom: 2px;
        }
        .cert-subtitle {
            font-size: 12px;
            color: #9E9E9E;
            letter-spacing: 2px;
        }

        .cert-divider {
            width: 60px;
            height: 2px;
            background: linear-gradient(90deg, #673AB7, #9C27B0);
            margin: 16px auto;
            border-radius: 1px;
        }

        .cert-body { text-align: center; }
        .cert-name {
            font-size: 28px;
            font-weight: 900;
            color: #333;
            margin-bottom: 4px;
        }
        .cert-class {
            font-size: 13px;
            color: #999;
            margin-bottom: 16px;
        }
        .cert-desc {
            font-size: 13px;
            color: #666;
            line-height: 1.6;
            margin-bottom: 16px;
        }

        .cert-levels {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 16px;
        }
        .cert-level {
            width: 72px;
            padding: 10px 4px;
            border-radius: 10px;
            text-align: center;
            border: 1.5px solid #E0E0E0;
            background: #FAFAFA;
        }
        .cert-level.passed {
            border-color: #4CAF50;
            background: #E8F5E9;
        }
        .cert-level-icon { font-size: 20px; }
        .cert-level-name { font-size: 11px; font-weight: 700; color: #333; margin-top: 2px; }
        .cert-level-status { font-size: 9px; font-weight: 700; margin-top: 2px; }
        .cert-level.passed .cert-level-status { color: #4CAF50; }

        .cert-footer {
            text-align: center;
            margin-top: 16px;
            padding-top: 12px;
            border-top: 1px solid #eee;
        }
        .cert-date { font-size: 12px; color: #999; margin-bottom: 4px; }
        .cert-coach { font-size: 13px; font-weight: 600; color: #666; }
        .cert-school { font-size: 10px; color: #BDBDBD; margin-top: 8px; letter-spacing: 1px; }

        /* 다운로드 버튼 */
        .cert-actions {
            margin-top: 20px;
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        .cert-download-btn {
            padding: 12px 24px;
            background: linear-gradient(135deg, #673AB7, #9C27B0);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            font-family: inherit;
            box-shadow: 0 3px 12px rgba(103,58,183,.2);
        }
        .cert-download-btn:active { transform: scale(0.97); }
        .cert-back-link {
            display: inline-block;
            margin-top: 12px;
            color: #999;
            font-size: 13px;
            text-decoration: none;
        }

        #error-msg {
            text-align: center;
            padding: 60px 20px;
            display: none;
        }
    </style>
</head>
<body>
    <div id="loading">
        <div class="spinner"></div>
        <div style="color:#999; font-size:14px;">인증서를 준비하는 중...</div>
    </div>

    <div id="certificate" style="display:none;"></div>

    <div class="cert-actions" id="actions" style="display:none;">
        <button class="cert-download-btn" id="btn-download">📥 이미지 저장</button>
    </div>
    <div style="text-align:center;" id="back-link" style="display:none;">
        <a class="cert-back-link" id="back-href" href="/">← 돌아가기</a>
    </div>

    <div id="error-msg"></div>

    <script src="/js/common.js?v=20260221"></script>
    <script>
    (async function() {
        const params = new URLSearchParams(window.location.search);
        const token = params.get('token') || '';

        let url = '/api/ace.php?action=certificate_data';
        if (token) url += '&token=' + encodeURIComponent(token);

        try {
            const resp = await fetch(url);
            const result = await resp.json();

            if (!result.success) {
                showError(result.error || '인증서를 불러올 수 없습니다.');
                return;
            }

            renderCertificate(result);
        } catch (e) {
            showError('네트워크 오류가 발생했습니다.');
        }

        function showError(msg) {
            document.getElementById('loading').style.display = 'none';
            const el = document.getElementById('error-msg');
            el.style.display = 'block';
            el.innerHTML = `
                <div style="font-size:48px; margin-bottom:16px;">😔</div>
                <div style="font-size:18px; font-weight:700; color:#333;">${escapeHtml(msg)}</div>
            `;
        }

        function escapeHtml(s) {
            const d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        }

        function renderCertificate(data) {
            document.getElementById('loading').style.display = 'none';
            const cert = document.getElementById('certificate');
            cert.style.display = 'block';
            document.getElementById('actions').style.display = 'flex';
            document.getElementById('back-link').style.display = 'block';

            if (token) {
                document.getElementById('back-href').href = '/ace-report/?token=' + encodeURIComponent(token);
                document.getElementById('back-href').textContent = '← 리포트로 돌아가기';
            }

            const passed = data.passed_levels || [];
            const passedSet = new Set(passed.map(p => parseInt(p.ace_level)));

            const today = new Date();
            const dateStr = `${today.getFullYear()}년 ${today.getMonth()+1}월 ${today.getDate()}일`;

            // 가장 최근 pass 날짜
            let latestDate = dateStr;
            if (passed.length > 0) {
                const d = new Date(passed[passed.length - 1].created_at);
                latestDate = `${d.getFullYear()}년 ${d.getMonth()+1}월 ${d.getDate()}일`;
            }

            cert.innerHTML = `
                <div class="cert-border">
                    <div class="cert-header">
                        <div class="cert-logo">S</div>
                        <div class="cert-title">Growth Certificate</div>
                        <div class="cert-subtitle">성장 인증서</div>
                    </div>

                    <div class="cert-divider"></div>

                    <div class="cert-body">
                        <div class="cert-name">${escapeHtml(data.student_name)}</div>
                        <div class="cert-class">${escapeHtml(data.class_name)}</div>

                        <div class="cert-desc">
                            위 학생은 소리튠 주니어 영어학교에서<br>
                            White 레벨 ACE 소리 인증 과정을<br>
                            성실히 수행하였음을 인증합니다.
                        </div>

                        <div class="cert-levels">
                            <div class="cert-level ${passedSet.has(1) ? 'passed' : ''}">
                                <div class="cert-level-icon">${passedSet.has(1) ? '✅' : '⬜'}</div>
                                <div class="cert-level-name">ACE 1</div>
                                <div class="cert-level-status">${passedSet.has(1) ? 'PASS' : '-'}</div>
                            </div>
                            <div class="cert-level ${passedSet.has(2) ? 'passed' : ''}">
                                <div class="cert-level-icon">${passedSet.has(2) ? '✅' : '⬜'}</div>
                                <div class="cert-level-name">ACE 2</div>
                                <div class="cert-level-status">${passedSet.has(2) ? 'PASS' : '-'}</div>
                            </div>
                            <div class="cert-level ${passedSet.has(3) ? 'passed' : ''}">
                                <div class="cert-level-icon">${passedSet.has(3) ? '✅' : '⬜'}</div>
                                <div class="cert-level-name">ACE 3</div>
                                <div class="cert-level-status">${passedSet.has(3) ? 'PASS' : '-'}</div>
                            </div>
                        </div>
                    </div>

                    <div class="cert-footer">
                        <div class="cert-date">${latestDate}</div>
                        <div class="cert-coach">${escapeHtml(data.coach_name || '')} Coach</div>
                        <div class="cert-school">SORITUNE JUNIOR ENGLISH ACADEMY</div>
                    </div>
                </div>
            `;

            // 다운로드 버튼
            document.getElementById('btn-download').addEventListener('click', async () => {
                const btn = document.getElementById('btn-download');
                btn.textContent = '저장 중...';
                btn.disabled = true;

                try {
                    const canvas = await html2canvas(cert, {
                        scale: 2,
                        backgroundColor: '#fff',
                        useCORS: true,
                    });
                    canvas.toBlob(blob => {
                        const url = URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = `ACE_Certificate_${data.student_name}.png`;
                        a.click();
                        URL.revokeObjectURL(url);
                        btn.textContent = '📥 이미지 저장';
                        btn.disabled = false;
                    }, 'image/png');
                } catch (e) {
                    alert('이미지 저장에 실패했습니다. 스크린샷을 이용해주세요.');
                    btn.textContent = '📥 이미지 저장';
                    btn.disabled = false;
                }
            });
        }
    })();
    </script>
</body>
</html>
