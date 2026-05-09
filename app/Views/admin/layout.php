<?php

declare(strict_types=1);

use App\Helpers\Security;

$assetDir = dirname(__DIR__, 3) . '/public/assets/';
$styleVersion = (string) (@filemtime($assetDir . 'style.css') ?: time());
$scriptVersion = (string) (@filemtime($assetDir . 'app.js') ?: time());
$currentPath = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/admin', PHP_URL_PATH) ?: '/admin');
$adminName = (string) ($adminUsername ?? 'admin');

// 后台侧边导航由当前路径驱动高亮，避免每个管理页面重复维护导航结构。
$navItems = [
    [
        'href' => '/admin',
        'label' => '仪表盘',
        'note' => '查看系统概览和最近记录',
        'active' => $currentPath === '/admin',
    ],
    [
        'href' => '/admin/shares',
        'label' => '分享记录',
        'note' => '筛选、查看和删除分享数据',
        'active' => str_starts_with($currentPath, '/admin/shares'),
    ],
    [
        'href' => '/admin/config',
        'label' => '系统设置',
        'note' => '配置展示、规则、上传与频控',
        'active' => str_starts_with($currentPath, '/admin/config'),
    ],
    [
        'href' => '/admin/password',
        'label' => '修改密码',
        'note' => '维护管理员账号安全',
        'active' => str_starts_with($currentPath, '/admin/password'),
    ],
];

// 页面头部文案根据当前子路由切换，让不同后台页面共用同一套 layout。
$sectionTitle = '后台管理';
$sectionDesc = '统一查看分享状态、容量占用、站点设置和管理员安全配置。';

if ($currentPath === '/admin') {
    $sectionTitle = '系统仪表盘';
    $sectionDesc = '快速浏览总分享数、活跃状态、文件占用和最近记录。';
} elseif (str_starts_with($currentPath, '/admin/shares')) {
    $sectionTitle = '分享记录';
    $sectionDesc = '支持按类型和关键词检索，适合做清理、定位和删除操作。';
} elseif (str_starts_with($currentPath, '/admin/config')) {
    $sectionTitle = '系统设置';
    $sectionDesc = '集中管理站点展示、提取码规则、有效期与风控参数。';
} elseif (str_starts_with($currentPath, '/admin/password')) {
    $sectionTitle = '账号安全';
    $sectionDesc = '及时更新管理员密码，降低默认账号长期暴露带来的风险。';
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>后台管理</title>
    <meta name="theme-color" content="#10313e">
    <link rel="stylesheet" href="/assets/style.css?v=<?= Security::escape($styleVersion) ?>">
</head>
<body class="admin-body">
    <div class="admin-shell">
        <aside class="admin-sidebar">
            <div class="admin-brand">
                <span class="admin-brand-mark">FS</span>
                <div class="admin-brand-copy">
                    <h1>FileShareBox</h1>
                    <p>管理控制台</p>
                </div>
            </div>

            <div class="admin-user-card">
                <span class="admin-user-label">当前登录</span>
                <strong><?= Security::escape($adminName) ?></strong>
            </div>

            <nav class="admin-nav">
                <?php foreach ($navItems as $item): ?>
                    <a
                        href="<?= Security::escape($item['href']) ?>"
                        class="admin-nav-link <?= $item['active'] ? 'is-active' : '' ?>"
                        <?= $item['active'] ? 'aria-current="page"' : '' ?>
                    >
                        <strong><?= Security::escape($item['label']) ?></strong>
                        <span><?= Security::escape($item['note']) ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>

            <form action="/admin/logout" method="post" class="logout-form">
                <?= $csrfInput ?? '' ?>
                <button type="submit">退出登录</button>
            </form>
        </aside>

        <main class="admin-main">
            <header class="card admin-header">
                <div>
                    <p class="eyebrow">Admin Console</p>
                    <h2><?= Security::escape($sectionTitle) ?></h2>
                    <p><?= Security::escape($sectionDesc) ?></p>
                </div>
                <div class="admin-header-badge">
                    <span>已登录</span>
                    <strong><?= Security::escape($adminName) ?></strong>
                </div>
            </header>

            <?php if (!empty($flash)): ?>
                <div class="flash <?= Security::escape((string) $flash['type']) ?>"><?= Security::escape((string) $flash['message']) ?></div>
            <?php endif; ?>

            <div class="admin-content">
                <?= $content ?>
            </div>
        </main>
    </div>
    <script src="/assets/app.js?v=<?= Security::escape($scriptVersion) ?>"></script>
</body>
</html>
