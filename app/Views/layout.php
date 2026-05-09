<?php

declare(strict_types=1);

use App\Helpers\Security;

// 用文件修改时间做静态资源版本号，发布样式或脚本更新后浏览器会自动拉取新文件。
$assetDir = dirname(__DIR__, 2) . '/public/assets/';
$styleVersion = (string) (@filemtime($assetDir . 'style.css') ?: time());
$scriptVersion = (string) (@filemtime($assetDir . 'app.js') ?: time());
$siteTitle = (string) ($siteName ?? 'FileCodeBox PHP');
$siteTagline = (string) ($siteTagline ?? '像取快递一样取文件，匿名分享文本和文件。');
$showAdminEntry = (bool) ($showAdminEntry ?? true);
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= Security::escape($siteTitle) ?></title>
    <meta name="theme-color" content="#0f7c90">
    <link rel="stylesheet" href="/assets/style.css?v=<?= Security::escape($styleVersion) ?>">
</head>
<body class="site-body">
    <div class="fx-stage" aria-hidden="true">
        <span class="fx-orb fx-orb-a"></span>
        <span class="fx-orb fx-orb-b"></span>
        <span class="fx-orb fx-orb-c"></span>
    </div>
    <div id="cursorGlow" class="cursor-glow" aria-hidden="true"></div>

    <header class="topbar">
        <div class="container topbar-inner">
            <a class="brand" href="/">
                <span class="brand-mark">FC</span>
                <span class="brand-copy">
                    <strong><?= Security::escape($siteTitle) ?></strong>
                    <small><?= Security::escape($siteTagline) ?></small>
                </span>
            </a>

            <div class="topbar-actions">
                <a class="topbar-link" href="/">快速取件</a>
                <a class="topbar-link" href="/upload">上传内容</a>
                <?php if ($showAdminEntry): ?>
                    <a class="admin-link" href="/admin/login">管理后台</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="container page-shell landing-shell">
        <?= $content ?>
    </main>

    <div id="toastStack" class="toast-stack" aria-live="polite" aria-atomic="true"></div>

    <script src="/assets/app.js?v=<?= Security::escape($scriptVersion) ?>"></script>
</body>
</html>
