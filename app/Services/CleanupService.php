<?php

declare(strict_types=1);

namespace App\Services;

/**
 * 清理服务。
 *
 * 供 CLI 定时任务调用，负责把已过期分享标记失效、删除失效文件、刷新上传目录磁盘占用。
 * 这些操作设计成可重复执行，便于 cron 或任务计划周期性调用。
 */
final class CleanupService
{
    private const BATCH_SIZE = 100;

    private ConfigService $configService;

    public function __construct()
    {
        $this->configService = new ConfigService();
    }

    public function run(): array
    {
        $expired = $this->cleanupExpiredShares();
        $orphan = $this->cleanupDeletedFiles();
        $diskUsage = $this->refreshUploadDiskUsage();

        return [
            'expired_marked' => $expired,
            'orphan_files_deleted' => $orphan,
            'upload_disk_usage_bytes' => $diskUsage,
        ];
    }

    /**
     * 批量清理已经达到失效条件的分享记录。
     *
     * 这里一次只处理固定批次，避免在记录很多时单次清理占用过长时间。
     * 对文件分享，会在标记失效后尝试顺手删除物理文件。
     */
    private function cleanupExpiredShares(): int
    {
        $pdo = Database::pdo();
        $total = 0;

        $select = $pdo->prepare('SELECT id, share_type, file_path FROM shares WHERE status = 1 AND (
            (expire_style IN ("day", "hour", "minute") AND expire_at IS NOT NULL AND expire_at <= NOW())
            OR (expire_style = "count" AND max_fetch_count IS NOT NULL AND current_fetch_count >= max_fetch_count)
        ) ORDER BY id ASC LIMIT :limit');
        $update = $pdo->prepare('UPDATE shares SET status = 0, deleted_at = NOW(), updated_at = NOW() WHERE id = :id AND status = 1');

        while (true) {
            $select->bindValue(':limit', self::BATCH_SIZE, \PDO::PARAM_INT);
            $select->execute();
            $shares = $select->fetchAll();
            if ($shares === []) {
                break;
            }

            foreach ($shares as $share) {
                $shareId = (int) $share['id'];
                $update->execute(['id' => $shareId]);
                if ($update->rowCount() < 1) {
                    continue;
                }

                if ($share['share_type'] === 'file' && !empty($share['file_path'])) {
                    $absolute = $this->absolutePath((string) $share['file_path']);
                    if ($absolute !== null && is_file($absolute)) {
                        @unlink($absolute);
                    }
                }

                $total++;
            }

            if (count($shares) < self::BATCH_SIZE) {
                break;
            }
        }

        return $total;
    }

    private function cleanupDeletedFiles(): int
    {
        $pdo = Database::pdo();
        $deleted = 0;

        $select = $pdo->prepare('SELECT id, file_path FROM shares WHERE share_type = "file" AND status = 0 AND file_path IS NOT NULL AND file_path <> "" ORDER BY id ASC LIMIT :limit');
        $clear = $pdo->prepare('UPDATE shares SET file_path = NULL, updated_at = NOW() WHERE id = :id');

        while (true) {
            $select->bindValue(':limit', self::BATCH_SIZE, \PDO::PARAM_INT);
            $select->execute();
            $rows = $select->fetchAll();
            if ($rows === []) {
                break;
            }

            foreach ($rows as $row) {
                $absolute = $this->absolutePath((string) $row['file_path']);
                $removed = false;

                if ($absolute === null || !is_file($absolute)) {
                    $removed = true;
                } elseif (@unlink($absolute)) {
                    $removed = true;
                    $deleted++;
                }

                if ($removed) {
                    $clear->execute(['id' => (int) $row['id']]);
                }
            }

            if (count($rows) < self::BATCH_SIZE) {
                break;
            }
        }

        return $deleted;
    }

    private function refreshUploadDiskUsage(): int
    {
        $uploadDir = (string) app_config('storage.upload_dir');
        $diskUsage = $this->directorySize($uploadDir);

        $this->configService->save([
            'upload_disk_usage_bytes' => (string) $diskUsage,
            'upload_disk_usage_updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $diskUsage;
    }

    private function absolutePath(string $relativePath): ?string
    {
        $trimmed = ltrim($relativePath, '/');
        if ($trimmed === '') {
            return null;
        }

        return dirname(__DIR__, 2) . '/' . $trimmed;
    }

    private function directorySize(string $path): int
    {
        if (!is_dir($path)) {
            return 0;
        }

        $size = 0;

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($iterator as $entry) {
                if ($entry instanceof \SplFileInfo && $entry->isFile()) {
                    $size += (int) $entry->getSize();
                }
            }
        } catch (\Throwable) {
            return $size;
        }

        return $size;
    }
}
