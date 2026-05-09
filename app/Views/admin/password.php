<?php

declare(strict_types=1);
?>
<section class="page-head">
    <div>
        <p class="eyebrow">Security</p>
        <h1>修改密码</h1>
        <p>及时更新管理员密码，避免默认账号长期暴露在外部环境中。</p>
    </div>
</section>

<!-- 改密页只保留核心表单，提交后由 AdminController::changePassword() 处理。 -->
<div class="split-layout panel-shell-single">
    <form method="post" action="/admin/password/change" class="config-form">
        <?= $csrfInput ?>

        <div class="field">
            <label for="oldPassword">旧密码</label>
            <input id="oldPassword" type="password" name="old_password" required>
        </div>

        <div class="field">
            <label for="newPassword">新密码（至少 8 位）</label>
            <input id="newPassword" type="password" name="new_password" minlength="8" required>
            <p class="field-tip">建议使用字母、数字和特殊字符组合，避免与其他系统共用。</p>
        </div>

        <div class="form-actions">
            <button type="submit">更新密码</button>
        </div>
    </form>
</div>
