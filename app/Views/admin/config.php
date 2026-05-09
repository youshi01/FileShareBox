<?php

declare(strict_types=1);

$cfg = $config ?? [];
$uploadLimit = $uploadLimit ?? [
    'configured_mb' => (int) ($cfg['max_upload_mb'] ?? 0),
    'php_upload_mb' => 0,
    'php_post_mb' => 0,
    'effective_mb' => (int) ($cfg['max_upload_mb'] ?? 0),
    'is_capped_by_runtime' => false,
];
$allowedExpireStyles = array_filter(array_map('trim', explode(',', (string) ($cfg['allowed_expire_styles'] ?? 'day,hour,minute,count,forever'))));
$expireLabels = ['day' => '按天', 'hour' => '按小时', 'minute' => '按分钟', 'count' => '按次数', 'forever' => '永久'];
?>
<section class="page-head">
    <div>
        <p class="eyebrow">Configuration</p>
        <h1>系统设置</h1>
        <p>统一管理站点展示、分享规则、提取码策略、运行时限制和风控参数。</p>
    </div>
    <div class="page-chip-group">
        <span class="chip chip-primary">驱动 <?= htmlspecialchars((string) ($cfg['storage_driver'] ?? 'local')) ?></span>
        <span class="chip">Runtime <?= (int) ($uploadLimit['effective_mb'] ?? 0) ?> MB</span>
    </div>
</section>

<!-- 配置页按展示、规则、上传运行时和频控分组，提交后统一交给 /admin/config/save 归一化落库。 -->
<form method="post" action="/admin/config/save" class="config-form stacked-form">
    <?= $csrfInput ?>

    <section class="form-section">
        <div class="section-heading">
            <p class="panel-kicker">Display</p>
            <h2>站点展示</h2>
            <p>控制首页标题、副标题、公告和管理入口是否对外展示。</p>
        </div>

        <div class="field-grid">
            <div class="field">
                <label for="siteName">站点名称</label>
                <input id="siteName" type="text" name="site_name" value="<?= htmlspecialchars((string) ($cfg['site_name'] ?? 'FileShareBox PHP')) ?>" required>
            </div>
            <div class="field">
                <label for="siteTagline">首页副标题</label>
                <input id="siteTagline" type="text" name="site_tagline" value="<?= htmlspecialchars((string) ($cfg['site_tagline'] ?? '像取快递一样取文件，匿名分享文本和文件。')) ?>">
            </div>
        </div>

        <div class="field">
            <label for="siteNotice">站点公告</label>
            <textarea id="siteNotice" name="site_notice" rows="4"><?= htmlspecialchars((string) ($cfg['site_notice'] ?? '')) ?></textarea>
        </div>

        <div class="toggle-grid">
            <label class="toggle-card">
                <input type="checkbox" name="show_admin_entry" <?= (($cfg['show_admin_entry'] ?? '1') === '1') ? 'checked' : '' ?>>
                <span>显示后台入口</span>
                <small>控制首页顶部和主视觉区域是否显示管理后台入口。</small>
            </label>

            <label class="toggle-card">
                <input type="checkbox" name="allow_guest_upload" <?= (($cfg['allow_guest_upload'] ?? '1') === '1') ? 'checked' : '' ?>>
                <span>开启游客上传</span>
                <small>关闭后，首页只保留提取码取件，不再允许匿名新增内容。</small>
            </label>
        </div>
    </section>

    <section class="form-section">
        <div class="section-heading">
            <p class="panel-kicker">Rules</p>
            <h2>分享规则</h2>
            <p>控制提取码策略、默认有效期、允许的过期方式和最长保存时长。</p>
        </div>

        <div class="toggle-grid">
            <label class="toggle-card">
                <input type="checkbox" name="allow_custom_code" <?= (($cfg['allow_custom_code'] ?? '1') === '1') ? 'checked' : '' ?>>
                <span>允许自定义提取码</span>
                <small>关闭后仅允许系统生成随机提取码。</small>
            </label>
        </div>

        <div class="field">
            <label>允许的有效期类型</label>
            <div class="toggle-grid">
                <?php foreach ($expireLabels as $key => $label): ?>
                    <label class="toggle-card">
                        <input type="checkbox" name="allowed_expire_styles[]" value="<?= $key ?>" <?= in_array($key, $allowedExpireStyles, true) ? 'checked' : '' ?>>
                        <span><?= $label ?></span>
                        <small><?= $key === 'count' ? '按下载/提取次数失效' : ($key === 'forever' ? '永久有效，不建议公开站点开启' : '按时间自动过期') ?></small>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="field-grid field-grid-three">
            <div class="field">
                <label for="defaultExpireStyle">默认有效期类型</label>
                <select id="defaultExpireStyle" name="default_expire_style">
                    <?php foreach ($expireLabels as $k => $v): ?>
                        <option value="<?= $k ?>" <?= (($cfg['default_expire_style'] ?? 'day') === $k) ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="defaultExpireValue">默认有效期数值</label>
                <input id="defaultExpireValue" type="number" name="default_expire_value" min="1" value="<?= (int) ($cfg['default_expire_value'] ?? 1) ?>">
            </div>
            <div class="field">
                <label for="maxSaveSeconds">最长保存时长（秒）</label>
                <input id="maxSaveSeconds" type="number" name="max_save_seconds" min="0" value="<?= (int) ($cfg['max_save_seconds'] ?? 0) ?>">
                <p class="field-tip">填 0 表示不限制；填入后，超过该时长的分享会被拒绝，永久有效也会被禁用。</p>
            </div>
        </div>

        <div class="field-grid">
            <div class="field">
                <label for="codeLength">随机提取码长度</label>
                <input id="codeLength" type="number" name="code_length" min="4" max="12" value="<?= (int) ($cfg['code_length'] ?? 6) ?>">
            </div>
            <div class="field">
                <label for="maxTextLength">文本长度限制</label>
                <input id="maxTextLength" type="number" name="max_text_length" min="100" value="<?= (int) ($cfg['max_text_length'] ?? 20000) ?>">
            </div>
            <div class="field">
                <label for="cleanupInterval">自动清理周期（分钟）</label>
                <input id="cleanupInterval" type="number" name="cleanup_interval_minutes" min="5" value="<?= (int) ($cfg['cleanup_interval_minutes'] ?? 30) ?>">
            </div>
        </div>
    </section>

    <section class="form-section">
        <div class="section-heading">
            <p class="panel-kicker">Upload Runtime</p>
            <h2>上传与运行时</h2>
            <p>应用配置、PHP 运行时与最终生效上限需要同时匹配。</p>
        </div>

        <div class="field-grid field-grid-three">
            <div class="field">
                <label for="maxUploadMb">应用配置上传上限（MB）</label>
                <input id="maxUploadMb" type="number" name="max_upload_mb" min="1" value="<?= (int) ($cfg['max_upload_mb'] ?? 200) ?>">
            </div>
            <div class="field">
                <label for="storageDriver">存储驱动</label>
                <select id="storageDriver" name="storage_driver">
                    <option value="local" <?= (($cfg['storage_driver'] ?? 'local') === 'local') ? 'selected' : '' ?>>local</option>
                    <option value="s3" <?= (($cfg['storage_driver'] ?? 'local') === 's3') ? 'selected' : '' ?>>s3 (预留)</option>
                </select>
            </div>
            <div class="field">
                <label>运行时快照</label>
                <p class="field-tip">
                    应用配置：<?= (int) ($uploadLimit['configured_mb'] ?? 0) ?> MB；
                    PHP upload/post：<?= (int) ($uploadLimit['php_upload_mb'] ?? 0) ?>/<?= (int) ($uploadLimit['php_post_mb'] ?? 0) ?> MB；
                    当前有效上限：<?= (int) ($uploadLimit['effective_mb'] ?? 0) ?> MB。
                    <?php if (!empty($uploadLimit['is_capped_by_runtime'])): ?>
                        当前被 PHP 运行时限制住了，只改后台这里不会继续变大，还需要同时调大 upload_max_filesize 和 post_max_size。
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </section>

    <section class="form-section">
        <div class="section-heading">
            <p class="panel-kicker">Rate Limit</p>
            <h2>上传频控</h2>
            <p>用于限制短时间内的高频上传，降低公开站点被滥用的风险。</p>
        </div>

        <div class="field-grid field-grid-three">
            <div class="field">
                <label for="uploadWindow">窗口（秒）</label>
                <input id="uploadWindow" type="number" name="upload_window_seconds" min="60" value="<?= (int) ($cfg['upload_window_seconds'] ?? 300) ?>">
            </div>
            <div class="field">
                <label for="uploadMaxHits">窗口内最大上传次数</label>
                <input id="uploadMaxHits" type="number" name="upload_max_hits" min="1" value="<?= (int) ($cfg['upload_max_hits'] ?? 10) ?>">
            </div>
            <div class="field">
                <label for="uploadBlock">封禁时长（分钟）</label>
                <input id="uploadBlock" type="number" name="upload_block_minutes" min="1" value="<?= (int) ($cfg['upload_block_minutes'] ?? 10) ?>">
            </div>
        </div>
    </section>

    <section class="form-section">
        <div class="section-heading">
            <p class="panel-kicker">Fetch Protection</p>
            <h2>提取码错误限制</h2>
            <p>用于拦截暴力尝试，减少提取码被穷举的风险。</p>
        </div>

        <div class="field-grid field-grid-three">
            <div class="field">
                <label for="fetchWindow">窗口（秒）</label>
                <input id="fetchWindow" type="number" name="fetch_fail_window_seconds" min="60" value="<?= (int) ($cfg['fetch_fail_window_seconds'] ?? 300) ?>">
            </div>
            <div class="field">
                <label for="fetchMaxHits">最大错误次数</label>
                <input id="fetchMaxHits" type="number" name="fetch_fail_max_hits" min="1" value="<?= (int) ($cfg['fetch_fail_max_hits'] ?? 8) ?>">
            </div>
            <div class="field">
                <label for="fetchBlock">封禁时长（分钟）</label>
                <input id="fetchBlock" type="number" name="fetch_fail_block_minutes" min="1" value="<?= (int) ($cfg['fetch_fail_block_minutes'] ?? 10) ?>">
            </div>
        </div>
    </section>

    <div class="form-actions">
        <button type="submit">保存配置</button>
    </div>
</form>
