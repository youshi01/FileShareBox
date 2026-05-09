<?php

declare(strict_types=1);

/**
 * 清理任务 CLI 入口。
 *
 * 通常由 cron 或 Windows 任务计划定时调用，执行过期分享清理、孤儿文件删除和磁盘占用刷新。
 */
require __DIR__ . '/../app/bootstrap.php';

use App\Services\CleanupService;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must run in CLI mode.\n");
    exit(1);
}

try {
    // 输出统一的摘要字段，便于任务计划或日志系统直接采集结果。
    $result = (new CleanupService())->run();
    fwrite(STDOUT, sprintf(
        "Cleanup finished. expired_marked=%d orphan_files_deleted=%d upload_disk_usage_bytes=%d\n",
        $result['expired_marked'],
        $result['orphan_files_deleted'],
        $result['upload_disk_usage_bytes']
    ));
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Cleanup failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
