<?php

declare(strict_types=1);

namespace App\Services;

use Throwable;

/**
 * 动态配置服务。
 *
 * 统一读取 system_config 表和代码默认值，并负责把上传大小、有效期规则等配置
 * 转换为业务层可直接消费的结构。这里同时承担“数据库配置 + 代码默认值”的回退逻辑。
 */
final class ConfigService
{
    private const SUPPORTED_EXPIRE_STYLES = ['day', 'hour', 'minute', 'count', 'forever'];

    private static ?array $sharedCache = null;

    private ?array $cache = null;

    private array $defaults = [
        'site_name' => 'FileCodeBox PHP',
        'site_tagline' => '像取快递一样取文件，匿名分享文本和文件。',
        'site_notice' => '',
        'show_admin_entry' => '1',
        'allow_guest_upload' => '1',
        'allow_custom_code' => '1',
        'allowed_expire_styles' => 'day,hour,minute,count,forever',
        'default_expire_style' => 'day',
        'default_expire_value' => '1',
        'max_save_seconds' => '0',
        'max_upload_mb' => '200',
        'max_text_length' => '20000',
        'code_length' => '6',
        'upload_window_seconds' => '300',
        'upload_max_hits' => '10',
        'upload_block_minutes' => '10',
        'fetch_fail_window_seconds' => '300',
        'fetch_fail_max_hits' => '8',
        'fetch_fail_block_minutes' => '10',
        'cleanup_interval_minutes' => '30',
        'storage_driver' => 'local',
        'upload_disk_usage_bytes' => '0',
        'upload_disk_usage_updated_at' => '',
    ];

    public function get(string $key, ?string $fallback = null): string
    {
        $all = $this->all();
        return $all[$key] ?? $fallback ?? ($this->defaults[$key] ?? '');
    }

    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        if (self::$sharedCache !== null) {
            $this->cache = self::$sharedCache;
            return $this->cache;
        }

        $cache = $this->defaults;

        try {
            $pdo = Database::pdo();
            $stmt = $pdo->query('SELECT config_key, config_value FROM system_config');
            foreach ($stmt->fetchAll() as $row) {
                $cache[$row['config_key']] = (string) $row['config_value'];
            }
        } catch (Throwable) {
            // Schema may not be installed yet. Keep defaults.
        }

        $this->cache = $cache;
        self::$sharedCache = $cache;
        return $this->cache;
    }

    public function save(array $config): void
    {
        $pdo = Database::pdo();
        $sql = 'INSERT INTO system_config (config_key, config_value, config_group, updated_at)
                VALUES (:key, :value, :group_name, NOW())
                ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), config_group = VALUES(config_group), updated_at = NOW()';
        $stmt = $pdo->prepare($sql);

        foreach ($config as $key => $value) {
            $stmt->execute([
                'key' => (string) $key,
                'value' => (string) $value,
                'group_name' => $this->groupForKey((string) $key),
            ]);
        }

        $this->cache = null;
        self::$sharedCache = null;
    }

    public function configuredUploadLimitMb(): int
    {
        return max(1, (int) $this->get('max_upload_mb', $this->defaults['max_upload_mb']));
    }

    public function phpUploadLimitMb(): int
    {
        return $this->bytesToMb($this->iniSizeToBytes((string) ini_get('upload_max_filesize')));
    }

    public function phpPostLimitMb(): int
    {
        return $this->bytesToMb($this->iniSizeToBytes((string) ini_get('post_max_size')));
    }

    /**
     * 计算当前请求真实可用的上传上限。
     *
     * 后台配置的 MB 值只是业务期望值，真正生效时还要受 PHP 的
     * upload_max_filesize 和 post_max_size 限制，因此这里取三者中的最小值。
     */
    public function effectiveUploadLimitMb(): int
    {
        $configuredMb = $this->configuredUploadLimitMb();
        $runtimeLimits = array_filter([
            $this->phpUploadLimitMb(),
            $this->phpPostLimitMb(),
        ], static fn (int $value): bool => $value > 0);

        if ($runtimeLimits === []) {
            return $configuredMb;
        }

        return min($configuredMb, min($runtimeLimits));
    }

    public function uploadLimitSnapshot(): array
    {
        $configuredMb = $this->configuredUploadLimitMb();
        $phpUploadMb = $this->phpUploadLimitMb();
        $phpPostMb = $this->phpPostLimitMb();
        $effectiveMb = $this->effectiveUploadLimitMb();

        return [
            'configured_mb' => $configuredMb,
            'php_upload_mb' => $phpUploadMb,
            'php_post_mb' => $phpPostMb,
            'effective_mb' => $effectiveMb,
            'is_capped_by_runtime' => $effectiveMb > 0 && $configuredMb > 0 && $effectiveMb < $configuredMb,
        ];
    }

    public function allowedExpireStyles(): array
    {
        $raw = (string) $this->get('allowed_expire_styles', implode(',', self::SUPPORTED_EXPIRE_STYLES));
        $styles = array_values(array_filter(array_map(
            static fn (string $value): string => trim($value),
            explode(',', $raw)
        )));

        $styles = array_values(array_intersect(self::SUPPORTED_EXPIRE_STYLES, $styles));

        return $styles !== [] ? $styles : self::SUPPORTED_EXPIRE_STYLES;
    }

    public function normalizeExpireStyle(string $style): string
    {
        $style = trim($style);
        if (!in_array($style, self::SUPPORTED_EXPIRE_STYLES, true)) {
            return 'day';
        }

        return $style;
    }

    public function normalizeAllowedExpireStyles(array $styles): string
    {
        $normalized = [];
        foreach ($styles as $style) {
            $style = $this->normalizeExpireStyle((string) $style);
            if (!in_array($style, $normalized, true)) {
                $normalized[] = $style;
            }
        }

        if ($normalized === []) {
            $normalized = self::SUPPORTED_EXPIRE_STYLES;
        }

        return implode(',', $normalized);
    }

    public function effectiveDefaultExpireStyle(): string
    {
        $default = $this->normalizeExpireStyle((string) $this->get('default_expire_style', 'day'));
        $allowed = $this->allowedExpireStyles();

        return in_array($default, $allowed, true) ? $default : $allowed[0];
    }

    public function maxSaveSeconds(): int
    {
        return max(0, (int) $this->get('max_save_seconds', '0'));
    }

    public function iniSizeToBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        $unit = strtolower(substr($value, -1));
        $number = (float) $value;

        return match ($unit) {
            'g' => (int) ($number * 1024 * 1024 * 1024),
            'm' => (int) ($number * 1024 * 1024),
            'k' => (int) ($number * 1024),
            default => (int) $number,
        };
    }

    private function groupForKey(string $key): string
    {
        return match (true) {
            in_array($key, ['site_name', 'site_tagline', 'site_notice', 'show_admin_entry'], true) => 'display',
            in_array($key, ['allow_guest_upload', 'allow_custom_code', 'allowed_expire_styles', 'default_expire_style', 'default_expire_value', 'max_save_seconds', 'code_length'], true) => 'rules',
            str_starts_with($key, 'upload_') => 'upload',
            str_starts_with($key, 'fetch_') => 'security',
            str_starts_with($key, 'max_') => 'upload',
            $key === 'storage_driver' => 'storage',
            default => 'general',
        };
    }

    private function bytesToMb(int $bytes): int
    {
        if ($bytes <= 0) {
            return 0;
        }

        return max(1, (int) floor($bytes / 1024 / 1024));
    }
}
