<?php

declare(strict_types=1);
?>
<!-- 首页只保留取件入口；实际取件逻辑由 public/assets/app.js 绑定 #fetchForm 处理。 -->
<section id="fetch" class="service-panels home-service-panels">
    <article class="card service-panel is-active home-fetch-panel">
        <div class="panel-shell panel-shell-single home-fetch-shell">
            <div class="panel-main">
                <div class="section-heading">
                    <p class="panel-kicker">Quick Fetch</p>
                    <h3>输入提取码，立即查看文件或文本</h3>
                    <p>适合临时传输资料、截图、代码片段和说明文档；输入提取码后即可直接取件。</p>
                </div>

                <form id="fetchForm" action="/api/share/fetch" method="post" class="form-shell">
                    <?= $csrfInput ?>
                    <div class="field">
                        <label for="fetchCode">提取码</label>
                        <input id="fetchCode" type="text" name="code" maxlength="32" placeholder="请输入提取码" required>
                        <p class="field-tip">支持 4-32 位字符；若包含字母，请按原样输入。</p>
                    </div>

                    <button type="submit">开始取件</button>
                </form>

                <div id="fetchResult" class="result empty">等待输入提取码后展示取件结果。</div>
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
