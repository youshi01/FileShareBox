<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * 安全相关工具。
 *
 * 统一处理视图转义、提取码规范化、文件名清洗和客户端 IP 推断，
 * 让这些跨层重复逻辑集中在一个地方，避免各处实现不一致。
 */
final class Security
{
    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function sanitizeCode(string $code): string
    {
        $code = strtoupper(trim($code));
        return preg_replace('/[^A-Z0-9_-]/', '', $code) ?? '';
    }

    public static function validateCode(string $code, int $minLen = 4, int $maxLen = 32): bool
    {
        $len = strlen($code);
        if ($len < $minLen || $len > $maxLen) {
            return false;
        }

        return (bool) preg_match('/^[A-Z0-9_-]+$/', $code);
    }

    public static function safeFileName(string $name): string
    {
        $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name) ?? 'file';
        return trim($name, '._-') ?: 'file';
    }

    /**
     * 尽量从常见代理头中提取客户端 IP。
     *
     * 当前实现只取第一个合法地址，适用于内网/单层代理的常见部署；
     * 如果未来接入更复杂的反向代理链，可以在这里统一收口调整。
     */
    public static function clientIp(): string
    {
        $candidates = [
            $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
            $_SERVER['HTTP_X_REAL_IP'] ?? null,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (!$candidate) {
                continue;
            }
            $ip = trim(explode(',', $candidate)[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return '0.0.0.0';
    }
}
