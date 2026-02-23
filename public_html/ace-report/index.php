<?php
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#673AB7">
    <title>소리 성장 리포트 - 소리튠 주니어</title>
    <link rel="icon" type="image/svg+xml" href="/images/favicon.svg">
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/variable/pretendardvariable-dynamic-subset.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/css/common.css?v=20260221">
    <link rel="stylesheet" href="/css/ace-report.css?v=20260221a">
</head>
<body class="ace-report-page">
    <div class="report-container" id="report-app">
        <div id="report-loading" style="text-align:center; padding:80px 20px;">
            <div style="width:32px; height:32px; border:3px solid #E0E0E0; border-top-color:#673AB7; border-radius:50%; animation:spin .8s linear infinite; margin:0 auto 12px;"></div>
            <div style="color:#999; font-size:14px;">리포트를 불러오는 중...</div>
        </div>
        <div id="report-content" style="display:none;"></div>
        <div id="report-error" style="display:none;"></div>
    </div>
    <style>@keyframes spin { to { transform:rotate(360deg); } }</style>
    <script src="/js/common.js?v=20260221"></script>
    <script src="/js/ace-report.js?v=20260221a"></script>
</body>
</html>
