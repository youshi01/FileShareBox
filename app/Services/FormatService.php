<?php

declare(strict_types=1);

namespace App\Services;

/**
 * 展示格式化工具。
 *
 * 目前主要用于把字节数转成人类可读格式，供后台表格和统计卡片展示。
 */
final class FormatService
{
    public static function bytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = (int) floor(log($bytes, 1024));
        $power = max(0, min($power, count($units) - 1));
        $value = $bytes / (1024 ** $power);

        return number_format($value, $power === 0 ? 0 : 2) . ' ' . $units[$power];
    }
}
