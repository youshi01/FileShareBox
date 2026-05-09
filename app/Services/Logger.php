<?php

declare(strict_types=1);

namespace App\Services;

/**
 * 极简错误日志工具。
 *
 * 当前只负责把错误信息追加写入 storage/logs/app.log，
 * 适合作为无框架环境下的兜底错误记录入口。
 */
final class Logger
{
    public static function error(string $message): void
    {
        $file = dirname(__DIR__, 2) . '/storage/logs/app.log';
        $line = sprintf("[%s] ERROR %s%s", date('Y-m-d H:i:s'), $message, PHP_EOL);
        file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }
}
