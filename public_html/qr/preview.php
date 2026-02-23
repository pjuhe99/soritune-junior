<?php
/**
 * QR OG 메타태그 미리보기 (카카오 공유)
 * 배경이미지 위 QR 코드 오버레이
 */
require_once __DIR__ . '/config.php';

$code = trim($_GET['code'] ?? '');
if (!$code) {
    http_response_code(400);
    die('Missing code');
}

$siteName = getSetting('site_name', '소리튠 주니어 영어학교');
$siteUrl = getSetting('site_url', 'https://j.soritune.com');
$bgImage = getSetting('qr_bg_image', 'og/qr_background.png');
$ogTitle = $siteName . ' 출석체크';
$ogDescription = 'QR 코드를 스캔하여 출석체크 해주세요';
$ogImage = $siteUrl . '/images/' . $bgImage;
$scanUrl = $siteUrl . '/qr/scan.php?code=' . $code;
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- OG 메타태그 -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= e($ogTitle) ?>">
    <meta property="og:description" content="<?= e($ogDescription) ?>">
    <meta property="og:image" content="<?= e($ogImage) ?>">
    <meta property="og:url" content="<?= e($scanUrl) ?>">

    <!-- 카카오 -->
    <meta property="og:site_name" content="<?= e($siteName) ?>">

    <title><?= e($ogTitle) ?></title>

    <script>
        // 즉시 스캔 페이지로 리다이렉트
        window.location.href = '<?= e($scanUrl) ?>';
    </script>
</head>
<body>
    <p>리다이렉트 중... <a href="<?= e($scanUrl) ?>">여기를 클릭하세요</a></p>
</body>
</html>
