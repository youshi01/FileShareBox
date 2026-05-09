<?php

declare(strict_types=1);

use App\Helpers\Security;

$assetDir = dirname(__DIR__, 3) . '/public/assets/';
$styleVersion = (string) (@filemtime($assetDir . 'style.css') ?: time());
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>管理员登录</title>
    <meta name="theme-color" content="#10313e">
    <link rel="stylesheet" href="/assets/style.css?v=<?= Security::escape($styleVersion) ?>">
</head>
<body class="login-body">
    <!-- 登录页不复用后台 layout，避免在未登录时也渲染侧边栏和后台导航。 -->
    <div class="login-shell">
        <section class="card login-card">
            <div class="section-heading">
                <p class="panel-kicker">Sign In</p>
                <h2>管理员登录</h2>
                <p>默认账号密码由安装脚本写入，首次登录后请立即修改密码。</p>
            </div>

            <?php if (!empty($flash)): ?>
                <div class="flash <?= Security::escape((string) $flash['type']) ?>"><?= Security::escape((string) $flash['message']) ?></div>
            <?php endif; ?>

            <form action="/admin/login" method="post" class="form-shell">
                <?= $csrfInput ?>

                <div class="field">
                    <label for="username">账号</label>
                    <input id="username" type="text" name="username" value="admin" required>
                </div>

                <div class="field">
                    <label for="password">密码</label>
                    <input id="password" type="password" name="password" required>
                </div>

                <button type="submit">登录后台</button>
            </form>
        </section>
    </div>
</body>
</html>
