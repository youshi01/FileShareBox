<?php

declare(strict_types=1);

use App\Services\FormatService;

$totalShares = (int) ($summary['total'] ?? 0);
$activeShares = (int) ($summary['active'] ?? 0);
$fileShares = (int) ($summary['files'] ?? 0);
$textShares = (int) ($summary['texts'] ?? 0);
$fileRatio = $totalShares > 0 ? (int) round(($fileShares / $totalShares) * 100) : 0;
$activeRatio = $totalShares > 0 ? (int) round(($activeShares / $totalShares) * 100) : 0;
$diskUsageUpdatedAt = (string) ($summary['upload_disk_usage_updated_at'] ?? '');
?>
<!-- 仪表盘聚合展示 summary 和 recent 两组后台数据，便于进入后台后先看整体状态。 -->
<section class="page-head">
    <div>
        <p class="eyebrow">Overview</p>
        <h1>仪表盘</h1>
        <p>查看当前分享规模、活跃比例、文件占用和最近新增记录，快速判断站点运行状态。</p>
    </div>
    <div class="page-chip-group">
        <span class="chip chip-primary">活跃占比 <?= $activeRatio ?>%</span>
        <span class="chip chip-success">文件占比 <?= $fileRatio ?>%</span>
    </div>
</section>

<div class="stats-grid">
    <article class="stat-card stat-card-primary">
        <span class="stat-label">总分享数</span>
        <strong><?= $totalShares ?></strong>
        <p>当前数据库中的文件与文本累计记录总量。</p>
    </article>
    <article class="stat-card">
        <span class="stat-label">有效分享</span>
        <strong><?= $activeShares ?></strong>
        <p>仍可正常提取或下载的记录数量。</p>
    </article>
    <article class="stat-card">
        <span class="stat-label">文件分享</span>
        <strong><?= $fileShares ?></strong>
        <p>更适合交付安装包、截图、资料和压缩包。</p>
    </article>
    <article class="stat-card">
        <span class="stat-label">文本分享</span>
        <strong><?= $textShares ?></strong>
        <p>更适合部署说明、代码片段和临时备注。</p>
    </article>
    <article class="stat-card">
        <span class="stat-label">文件记录累计大小</span>
        <strong><?= htmlspecialchars(FormatService::bytes((int) ($summary['file_bytes_total'] ?? 0))) ?></strong>
        <p>基于数据库中文件记录的总容量统计。</p>
    </article>
    <article class="stat-card">
        <span class="stat-label">上传目录磁盘占用</span>
        <strong><?= htmlspecialchars(FormatService::bytes((int) ($summary['upload_disk_usage_bytes'] ?? 0))) ?></strong>
        <p>基于最近一次缓存统计<?= $diskUsageUpdatedAt !== '' ? '（' . htmlspecialchars($diskUsageUpdatedAt) . '）' : '' ?>。</p>
    </article>
</div>

<div class="dashboard-layout">
    <section class="table-card">
        <div class="table-toolbar">
            <div>
                <h2>最近记录</h2>
                <p>用于快速确认近期上传、文本分享和状态变化。</p>
            </div>
        </div>

        <?php if (empty($recent)): ?>
            <div class="empty-state">暂时没有最近记录。</div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>类型</th>
                        <th>提取码</th>
                        <th>标题</th>
                        <th>文件大小</th>
                        <th>创建时间</th>
                        <th>状态</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $row): ?>
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
                            <td><?= htmlspecialchars((string) $row['created_at']) ?></td>
                            <td><span class="status-pill <?= (int) $row['status'] === 1 ? 'is-ok' : 'is-muted' ?>"><?= (int) $row['status'] === 1 ? '有效' : '失效' ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

</div>
