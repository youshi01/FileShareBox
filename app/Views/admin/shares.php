<?php

declare(strict_types=1);

use App\Services\FormatService;

$totalPages = (int) ceil(($total ?? 0) / max(1, (int) ($pageSize ?? 20)));
?>
<section class="page-head">
    <div>
        <p class="eyebrow">Records</p>
        <h1>分享记录</h1>
        <p>支持按类型和关键词筛选，适合追踪提取码、容量占用、过期规则和删除操作。</p>
    </div>
    <div class="page-chip-group">
        <span class="chip chip-primary">总计 <?= (int) ($total ?? 0) ?> 条</span>
        <span class="chip"><?= max(1, (int) ($page ?? 1)) ?> / <?= max(1, $totalPages) ?> 页</span>
    </div>
</section>

<!-- 记录页通过 query string 保持筛选条件，翻页时也能延续当前搜索上下文。 -->
<form method="get" action="/admin/shares" class="filter-form filter-form-inline">
    <div class="field compact-field">
        <label for="shareType">类型</label>
        <select id="shareType" name="type">
            <option value="">全部类型</option>
            <option value="file" <?= ($type ?? '') === 'file' ? 'selected' : '' ?>>文件</option>
            <option value="text" <?= ($type ?? '') === 'text' ? 'selected' : '' ?>>文本</option>
        </select>
    </div>

    <div class="field field-grow">
        <label for="shareKeyword">关键词</label>
        <input id="shareKeyword" type="text" name="keyword" value="<?= htmlspecialchars((string) ($keyword ?? '')) ?>" placeholder="搜索提取码、标题或文件名">
    </div>

    <div class="filter-actions">
        <button type="submit">筛选记录</button>
    </div>
</form>

<section class="table-card">
    <div class="table-toolbar">
        <div>
            <h2>分享列表</h2>
            <p>文件记录会显示大小与过期信息，删除操作会同时清理物理文件。</p>
        </div>
        <div class="pager">
            <?php if (($page ?? 1) > 1): ?>
                <a href="/admin/shares?page=<?= (int) $page - 1 ?>&type=<?= urlencode((string) ($type ?? '')) ?>&keyword=<?= urlencode((string) ($keyword ?? '')) ?>">上一页</a>
            <?php endif; ?>
            <span>第 <?= (int) ($page ?? 1) ?> / <?= max(1, $totalPages) ?> 页</span>
            <?php if (($page ?? 1) < $totalPages): ?>
                <a href="/admin/shares?page=<?= (int) $page + 1 ?>&type=<?= urlencode((string) ($type ?? '')) ?>&keyword=<?= urlencode((string) ($keyword ?? '')) ?>">下一页</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($rows)): ?>
        <div class="empty-state">当前筛选条件下没有记录。</div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>类型</th>
                    <th>提取码</th>
                    <th>标题</th>
                    <th>文件大小</th>
                    <th>过期规则</th>
                    <th>已用次数</th>
                    <th>状态</th>
                    <th>创建时间</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $expireText = '-';
                    if (($row['expire_style'] ?? '') === 'forever') {
                        $expireText = '永久有效';
                    } elseif (($row['expire_style'] ?? '') === 'count') {
                        $expireText = (int) ($row['max_fetch_count'] ?? 0) . ' 次';
                    } elseif (!empty($row['expire_at'])) {
                        $expireText = (string) $row['expire_at'];
                    }
                    ?>
                    <tr>
                        <td><?= (int) $row['id'] ?></td>
                        <td><?= $row['share_type'] === 'file' ? '文件' : '文本' ?></td>
                        <td><?= htmlspecialchars((string) $row['code']) ?></td>
                        <td><?= htmlspecialchars((string) ($row['title'] ?: '-')) ?></td>
                        <td>
                            <?= $row['share_type'] === 'file'
                                ? htmlspecialchars(FormatService::bytes((int) ($row['file_size'] ?? 0)))
                                : '-' ?>
                        </td>
                        <td><?= htmlspecialchars($expireText) ?></td>
                        <td><?= (int) $row['current_fetch_count'] ?>/<?= $row['max_fetch_count'] ? (int) $row['max_fetch_count'] : '-' ?></td>
                        <td><span class="status-pill <?= (int) $row['status'] === 1 ? 'is-ok' : 'is-muted' ?>"><?= (int) $row['status'] === 1 ? '有效' : '失效' ?></span></td>
                        <td><?= htmlspecialchars((string) $row['created_at']) ?></td>
                        <td>
                            <form
                                method="post"
                                action="/admin/share/delete"
                                class="js-delete-form"
                                data-id="<?= (int) $row['id'] ?>"
                                data-code="<?= htmlspecialchars((string) $row['code'], ENT_QUOTES) ?>"
                                data-title="<?= htmlspecialchars((string) ($row['title'] ?: '-'), ENT_QUOTES) ?>"
                                data-size="<?= $row['share_type'] === 'file'
                                    ? htmlspecialchars(FormatService::bytes((int) ($row['file_size'] ?? 0)), ENT_QUOTES)
                                    : '-' ?>"
                            >
                                <?= $csrfInput ?>
                                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                <button type="submit" class="danger">删除</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<div id="deleteConfirmModal" class="modal" hidden>
    <div class="modal-mask" data-close-delete-modal></div>
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="deleteConfirmTitle">
        <h3 id="deleteConfirmTitle">确认删除分享记录</h3>
        <p class="modal-tip">删除后不可恢复，请再次确认。</p>
        <div class="delete-meta">
            <p><strong>ID：</strong><span id="deleteMetaId">-</span></p>
            <p><strong>提取码：</strong><span id="deleteMetaCode">-</span></p>
            <p><strong>标题：</strong><span id="deleteMetaTitle">-</span></p>
            <p><strong>文件大小：</strong><span id="deleteMetaSize">-</span></p>
        </div>

        <div class="delete-actions">
            <button id="cancelDeleteBtn" type="button">取消</button>
            <button id="confirmDeleteBtn" type="button" class="danger">确认删除</button>
        </div>
    </div>
</div>
