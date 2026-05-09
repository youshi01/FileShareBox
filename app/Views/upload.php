<?php

declare(strict_types=1);

use App\Helpers\Security;

$uploadDisabled = $allowGuestUpload ? '' : 'disabled';
$customCodeDisabled = ($allowGuestUpload && $allowCustomCode) ? '' : 'disabled';
$allowedExpireStyles = is_array($allowedExpireStyles ?? null) ? $allowedExpireStyles : ['day', 'hour', 'minute', 'count', 'forever'];
$maxTextLength = (int) ($maxTextLength ?? 20000);

$expireOptions = [
    'day' => '按天',
    'hour' => '按小时',
    'minute' => '按分钟',
    'count' => '按次数',
    'forever' => '永久',
];
$expireOptions = array_filter(
    $expireOptions,
    static fn (string $key): bool => in_array($key, $allowedExpireStyles, true),
    ARRAY_FILTER_USE_KEY
);

?>
<!-- 第一层切换：上传文件 / 上传文本；切换行为由 app.js 中的 #publicTabs 逻辑驱动。 -->
<section class="card service-nav upload-service-nav">
    <div id="publicTabs" class="service-tabs service-tabs-dual" role="tablist" aria-label="上传页面功能切换">
        <button class="service-tab is-active" type="button" data-public-tab="file" role="tab" aria-selected="true">上传文件</button>
        <button class="service-tab" type="button" data-public-tab="text" role="tab" aria-selected="false">上传文本</button>
    </div>
</section>

<section class="service-panels upload-page-panels">
    <article class="card service-panel is-active" data-public-panel="file">
        <div class="panel-shell panel-shell-single">
            <div class="panel-main">
                <div class="section-heading">
                    <p class="panel-kicker">File Sharing</p>
                    <h3>上传文件</h3>
                    <p>支持拖拽、粘贴和批量上传。上传成功后会立即返回提取码，便于复制和分发。</p>
                </div>

                <?php if (!$allowGuestUpload): ?>
                    <p class="warn">管理员已关闭游客上传，当前页面不允许匿名新增文件或文本。</p>
                <?php endif; ?>

                <!-- 第二层切换仅作用于文件上传区：拖拽 / 粘贴 / 批量。 -->
                <div id="uploadModeTabs" class="mode-tabs" role="tablist" aria-label="上传模式切换">
                    <button class="mode-tab is-active" type="button" data-mode="drag" role="tab" aria-selected="true">拖拽上传</button>
                    <button class="mode-tab" type="button" data-mode="paste" role="tab" aria-selected="false">粘贴上传</button>
                    <button class="mode-tab" type="button" data-mode="batch" role="tab" aria-selected="false">批量上传</button>
                </div>

                <section class="mode-panel is-active" data-mode-panel="drag">
                    <form id="dragShareForm" action="/api/share/file" method="post" enctype="multipart/form-data" class="form-shell">
                        <?= $csrfInput ?>

                        <div class="field">
                            <label>拖拽区域</label>
                            <div class="dropzone <?= $allowGuestUpload ? '' : 'is-disabled' ?>" data-dropzone tabindex="0">
                                <strong>拖拽文件到这里</strong>
                                <span>或点击选择文件，当前模式仅支持单文件</span>
                            </div>
                            <input class="visually-hidden" type="file" name="file" required <?= $uploadDisabled ?>>
                        </div>

                        <div class="field-grid">
                            <div class="field">
                                <label for="dragTitle">标题（可选）</label>
                                <input id="dragTitle" type="text" name="title" placeholder="例如：交付资料 / 安装包 / 项目文档" <?= $uploadDisabled ?>>
                            </div>
                            <div class="field">
                                <label for="dragCode">提取码（可选）</label>
                                <input id="dragCode" type="text" name="code" maxlength="32" placeholder="留空则自动生成" <?= $customCodeDisabled ?>>
                            </div>
                        </div>

                        <div class="field-grid">
                            <div class="field">
                                <label for="dragExpireStyle">有效期类型</label>
                                <select id="dragExpireStyle" name="expire_style" <?= $uploadDisabled ?>>
                                    <?php foreach ($expireOptions as $k => $v): ?>
                                        <option value="<?= $k ?>" <?= ($defaultExpireStyle === $k) ? 'selected' : '' ?>><?= $v ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label for="dragExpireValue">有效期数值</label>
                                <input id="dragExpireValue" type="number" name="expire_value" min="1" value="<?= Security::escape((string) $defaultExpireValue) ?>" <?= $uploadDisabled ?>>
                            </div>
                        </div>

                        <button type="submit" <?= $uploadDisabled ?>>上传并生成提取码</button>
                    </form>
                </section>

                <section class="mode-panel" data-mode-panel="paste" hidden>
                    <form id="pasteShareForm" action="/api/share/file" method="post" enctype="multipart/form-data" class="form-shell">
                        <?= $csrfInput ?>

                        <div class="field">
                            <label>粘贴区域</label>
                            <div class="pastezone <?= $allowGuestUpload ? '' : 'is-disabled' ?>" data-pastezone tabindex="0">
                                <strong>在这里按 Ctrl+V 粘贴截图或文件</strong>
                                <span>检测到剪贴板文件后即可上传</span>
                            </div>
                            <p id="pasteHint" class="zone-hint">当前未检测到剪贴板文件。</p>
                            <input class="visually-hidden" type="file" name="file" required <?= $uploadDisabled ?>>
                        </div>

                        <div class="field-grid">
                            <div class="field">
                                <label for="pasteTitle">标题（可选）</label>
                                <input id="pasteTitle" type="text" name="title" placeholder="例如：报错截图 / 临时凭证 / 会议纪要" <?= $uploadDisabled ?>>
                            </div>
                            <div class="field">
                                <label for="pasteCode">提取码（可选）</label>
                                <input id="pasteCode" type="text" name="code" maxlength="32" placeholder="留空则自动生成" <?= $customCodeDisabled ?>>
                            </div>
                        </div>

                        <div class="field-grid">
                            <div class="field">
                                <label for="pasteExpireStyle">有效期类型</label>
                                <select id="pasteExpireStyle" name="expire_style" <?= $uploadDisabled ?>>
                                    <?php foreach ($expireOptions as $k => $v): ?>
                                        <option value="<?= $k ?>" <?= ($defaultExpireStyle === $k) ? 'selected' : '' ?>><?= $v ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label for="pasteExpireValue">有效期数值</label>
                                <input id="pasteExpireValue" type="number" name="expire_value" min="1" value="<?= Security::escape((string) $defaultExpireValue) ?>" <?= $uploadDisabled ?>>
                            </div>
                        </div>

                        <button type="submit" <?= $uploadDisabled ?>>上传粘贴文件</button>
                    </form>
                </section>

                <section class="mode-panel" data-mode-panel="batch" hidden>
                    <form id="batchShareForm" action="/api/share/file" method="post" enctype="multipart/form-data" class="form-shell">
                        <?= $csrfInput ?>

                        <div class="field">
                            <label for="batchFiles">选择多个文件</label>
                            <input id="batchFiles" type="file" name="file" multiple required <?= $uploadDisabled ?>>
                            <p class="field-tip">每个文件会独立生成提取码，适合逐个发给不同接收方。</p>
                        </div>

                        <div class="field-grid">
                            <div class="field">
                                <label for="batchTitlePrefix">标题前缀（可选）</label>
                                <input id="batchTitlePrefix" type="text" name="title_prefix" placeholder="例如：第 1 批交付资料" <?= $uploadDisabled ?>>
                            </div>
                            <div class="field">
                                <label for="batchExpireStyle">有效期类型</label>
                                <select id="batchExpireStyle" name="expire_style" <?= $uploadDisabled ?>>
                                    <?php foreach ($expireOptions as $k => $v): ?>
                                        <option value="<?= $k ?>" <?= ($defaultExpireStyle === $k) ? 'selected' : '' ?>><?= $v ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="field">
                            <label for="batchExpireValue">有效期数值</label>
                            <input id="batchExpireValue" type="number" name="expire_value" min="1" value="<?= Security::escape((string) $defaultExpireValue) ?>" <?= $uploadDisabled ?>>
                        </div>

                        <button type="submit" <?= $uploadDisabled ?>>开始批量上传</button>

                        <div id="batchProgress" class="batch-progress empty">
                            <div class="batch-progress-meta">等待开始批量上传。</div>
                            <div class="batch-progress-track"><span class="batch-progress-fill"></span></div>
                        </div>
                        <div id="batchQueue" class="batch-queue empty">尚未选择文件。</div>
                    </form>
                </section>

                <div id="fileOpResult" class="result empty">上传状态会展示在这里。</div>
            </div>

        </div>
    </article>

    <article class="card service-panel" data-public-panel="text" hidden>
        <div class="panel-shell panel-shell-single">
            <div class="panel-main">
                <div class="section-heading">
                    <p class="panel-kicker">Text Delivery</p>
                    <h3>上传文本</h3>
                    <p>用于临时分享代码片段、部署步骤、访问链接、需求备注和说明文档。</p>
                </div>

                <form id="textShareForm" action="/api/share/text" method="post" class="form-shell">
                    <?= $csrfInput ?>

                    <div class="field">
                        <label for="textContent">文本内容</label>
                        <textarea id="textContent" name="text_content" rows="8" placeholder="可以粘贴代码片段、链接说明、部署步骤或临时笔记" required <?= $uploadDisabled ?>></textarea>
                        <p class="field-tip">当前文本长度上限：<?= $maxTextLength ?> 字。</p>
                    </div>

                    <div class="field-grid">
                        <div class="field">
                            <label for="textTitle">标题（可选）</label>
                            <input id="textTitle" type="text" name="title" placeholder="例如：部署说明 / 连接信息 / 变更记录" <?= $uploadDisabled ?>>
                        </div>
                        <div class="field">
                            <label for="textCode">提取码（可选）</label>
                            <input id="textCode" type="text" name="code" maxlength="32" placeholder="留空则自动生成" <?= $customCodeDisabled ?>>
                        </div>
                    </div>

                    <div class="field-grid">
                        <div class="field">
                            <label for="textExpireStyle">有效期类型</label>
                            <select id="textExpireStyle" name="expire_style" <?= $uploadDisabled ?>>
                                <?php foreach ($expireOptions as $k => $v): ?>
                                    <option value="<?= $k ?>" <?= ($defaultExpireStyle === $k) ? 'selected' : '' ?>><?= $v ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label for="textExpireValue">有效期数值</label>
                            <input id="textExpireValue" type="number" name="expire_value" min="1" value="<?= Security::escape((string) $defaultExpireValue) ?>" <?= $uploadDisabled ?>>
                        </div>
                    </div>

                    <button type="submit" <?= $uploadDisabled ?>>保存文本并生成提取码</button>
                </form>

                <div id="textOpResult" class="result empty">文本分享状态会展示在这里。</div>
            </div>

        </div>
    </article>
</section>

<div id="codeModal" class="modal" hidden>
    <div class="modal-mask" data-modal-close></div>
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="codeModalTitle">
        <button class="modal-close" type="button" data-modal-close aria-label="关闭">&times;</button>
        <h3 id="codeModalTitle">提取码已生成</h3>
        <div id="codeModalBody" class="modal-body"></div>
    </div>
</div>
