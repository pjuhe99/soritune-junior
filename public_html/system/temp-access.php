<?php
/**
 * 임시 IP 접속 - 토큰으로 IP를 일시 등록
 * 시스템관리자가 IP가 변경되어 접근 못할 때 사용
 */
require_once __DIR__ . '/../config.php';

$token = trim($_GET['token'] ?? '');
$siteUrl = getSetting('site_url', 'https://j.soritune.com');
$clientIp = getClientIP();

// 토큰 검증
$tokenInfo = null;
$error = null;
$alreadyUsed = false;
$alreadyRegistered = false;

if ($token) {
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM junior_temp_ip_access WHERE token = ? LIMIT 1');
    $stmt->execute([$token]);
    $tokenInfo = $stmt->fetch();

    if (!$tokenInfo) {
        $error = '유효하지 않은 링크입니다.';
    } elseif (!$tokenInfo['is_active']) {
        $error = '비활성화된 링크입니다.';
    } elseif (strtotime($tokenInfo['expires_at']) < time()) {
        $error = '만료된 링크입니다. (' . $tokenInfo['expires_at'] . ' 만료)';
    } elseif ($tokenInfo['ip_address'] && $tokenInfo['ip_address'] === $clientIp) {
        $alreadyRegistered = true;
    } elseif ($tokenInfo['ip_address']) {
        $alreadyUsed = true;
    }
} else {
    $error = '토큰이 없습니다.';
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>임시 IP 접속 - 소리튠 주니어</title>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/variable/pretendardvariable-dynamic-subset.min.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; }
        body { font-family: 'Pretendard Variable', Pretendard, -apple-system, sans-serif; background: #F5F5F5; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .card { background: #fff; border-radius: 20px; padding: 32px 28px; max-width: 420px; width: 100%; box-shadow: 0 8px 32px rgba(0,0,0,.08); }
        .icon { text-align: center; font-size: 48px; margin-bottom: 16px; }
        .title { text-align: center; font-size: 20px; font-weight: 800; color: #333; margin-bottom: 8px; }
        .desc { text-align: center; font-size: 14px; color: #757575; margin-bottom: 24px; line-height: 1.5; }
        .info-box { background: #F5F5F5; border-radius: 14px; padding: 16px; margin-bottom: 20px; }
        .info-row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 13px; }
        .info-label { color: #9E9E9E; }
        .info-value { color: #333; font-weight: 600; }
        .btn { width: 100%; padding: 15px; border: none; border-radius: 14px; font-size: 16px; font-weight: 700; cursor: pointer; font-family: inherit; transition: all .2s; }
        .btn:active { transform: scale(.98); }
        .btn-primary { background: linear-gradient(135deg, #37474F, #263238); color: #fff; }
        .btn-success { background: linear-gradient(135deg, #4CAF50, #388E3C); color: #fff; }
        .btn-outline { background: #fff; color: #37474F; border: 1.5px solid #E0E0E0; margin-top: 10px; }
        .error-text { color: #F44336; }
        .success-text { color: #4CAF50; }
        .warn { background: #FFF3E0; border: 1.5px solid #FFE0B2; border-radius: 12px; padding: 12px 14px; margin-bottom: 20px; font-size: 13px; color: #E65100; line-height: 1.4; }
        .result { display: none; }
        .result.show { display: block; }
        #btn-register:disabled { background: #ccc; }
    </style>
</head>
<body>
<div class="card">
<?php if ($error): ?>
    <div class="icon">🚫</div>
    <div class="title error-text"><?= e($error) ?></div>
    <div class="desc">시스템관리자에게 새 링크를 요청하세요.</div>
    <a href="/" class="btn btn-outline" style="display:block; text-align:center; text-decoration:none;">홈으로</a>

<?php elseif ($alreadyRegistered): ?>
    <div class="icon">✅</div>
    <div class="title success-text">이미 등록된 IP입니다</div>
    <div class="desc">현재 IP(<?= e($clientIp) ?>)가 이미 임시 등록되어 있습니다.</div>
    <div class="info-box">
        <div class="info-row"><span class="info-label">만료 시간</span><span class="info-value"><?= e($tokenInfo['expires_at']) ?></span></div>
        <div class="info-row"><span class="info-label">생성자</span><span class="info-value"><?= e($tokenInfo['created_by_name'] ?? '-') ?></span></div>
    </div>
    <a href="/system/" class="btn btn-primary" style="display:block; text-align:center; text-decoration:none;">시스템관리 접속</a>

<?php elseif ($alreadyUsed): ?>
    <div class="icon">🔒</div>
    <div class="title">이미 사용된 링크입니다</div>
    <div class="desc">다른 IP(<?= e(substr($tokenInfo['ip_address'], 0, -3) . '***') ?>)에서 이미 사용되었습니다.</div>
    <a href="/" class="btn btn-outline" style="display:block; text-align:center; text-decoration:none;">홈으로</a>

<?php else: ?>
    <div class="icon">🔑</div>
    <div class="title">임시 IP 접속</div>
    <div class="desc">이 링크를 사용하면 현재 IP가 임시로 시스템관리자 접속 허용 목록에 등록됩니다.</div>

    <div class="info-box">
        <div class="info-row"><span class="info-label">현재 IP</span><span class="info-value"><?= e($clientIp) ?></span></div>
        <div class="info-row"><span class="info-label">만료 시간</span><span class="info-value"><?= e($tokenInfo['expires_at']) ?></span></div>
        <div class="info-row"><span class="info-label">생성자</span><span class="info-value"><?= e($tokenInfo['created_by_name'] ?? '-') ?></span></div>
        <div class="info-row"><span class="info-label">생성 시간</span><span class="info-value"><?= e($tokenInfo['created_at']) ?></span></div>
    </div>

    <div class="warn">
        이 링크는 1회만 사용 가능합니다. IP 등록 후 만료 시간까지 시스템관리에 접속할 수 있습니다.
    </div>

    <div id="register-area">
        <button class="btn btn-primary" id="btn-register">IP 등록하고 접속하기</button>
    </div>

    <div class="result" id="result-success">
        <div class="icon" style="margin-top:8px;">✅</div>
        <div class="title success-text" style="margin-bottom:12px;">IP 등록 완료!</div>
        <div class="info-box">
            <div class="info-row"><span class="info-label">등록 IP</span><span class="info-value" id="reg-ip"></span></div>
            <div class="info-row"><span class="info-label">유효 기간</span><span class="info-value" id="reg-expires"></span></div>
        </div>
        <a href="/system/" class="btn btn-success" style="display:block; text-align:center; text-decoration:none;">시스템관리 접속</a>
    </div>

    <div class="result" id="result-error">
        <div class="title error-text" id="reg-error-msg" style="margin-bottom:12px;"></div>
    </div>

    <script>
    document.getElementById('btn-register').addEventListener('click', async function() {
        this.disabled = true;
        this.textContent = '등록 중...';
        try {
            const resp = await fetch('/api/system.php?action=use_temp_access', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ token: '<?= e($token) ?>' })
            });
            const data = await resp.json();
            if (data.success) {
                document.getElementById('register-area').style.display = 'none';
                document.getElementById('reg-ip').textContent = data.ip;
                document.getElementById('reg-expires').textContent = data.expires_at;
                document.getElementById('result-success').classList.add('show');
            } else {
                document.getElementById('reg-error-msg').textContent = data.error || '등록에 실패했습니다';
                document.getElementById('result-error').classList.add('show');
                this.disabled = false;
                this.textContent = 'IP 등록하고 접속하기';
            }
        } catch(e) {
            document.getElementById('reg-error-msg').textContent = '네트워크 오류가 발생했습니다';
            document.getElementById('result-error').classList.add('show');
            this.disabled = false;
            this.textContent = 'IP 등록하고 접속하기';
        }
    });
    </script>
<?php endif; ?>
</div>
</body>
</html>
