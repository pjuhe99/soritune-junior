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
    <meta name="theme-color" content="#FF5722">
    <title>ACE 도전 - 소리튠 주니어</title>
    <link rel="icon" type="image/svg+xml" href="/images/favicon.svg">
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/variable/pretendardvariable-dynamic-subset.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/css/common.css?v=20260221">
    <link rel="stylesheet" href="/css/toast.css?v=20260221">
    <link rel="stylesheet" href="/css/ace.css?v=20260221a">
    <link rel="stylesheet" href="/css/bravo.css?v=20260222">
</head>
<body class="ace-page">
    <div class="app-container" id="app">
        <div id="view-main">
            <div style="text-align:center; padding:60px 20px;">
                <div class="loading-spinner" style="width:32px; height:32px; border:3px solid #E0E0E0; border-top-color:#FF5722; border-radius:50%; animation:spin .8s linear infinite; margin:0 auto 12px;"></div>
                <div style="color:#999; font-size:14px;">로딩 중...</div>
            </div>
        </div>
    </div>
    <style>@keyframes spin { to { transform:rotate(360deg); } }</style>
    <script src="/js/toast.js?v=20260221"></script>
    <script src="/js/common.js?v=20260221"></script>
    <script src="/js/ace-recorder.js?v=20260221a"></script>
    <script src="/js/bravo-student.js?v=20260222"></script>
    <script src="/js/ace-student.js?v=20260222"></script>
</body>
</html>
