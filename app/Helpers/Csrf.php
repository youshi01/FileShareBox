<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * CSRF 工具。
 *
 * 管理端表单统一通过这里生成和校验 token，
 * 避免各页面各自拼装隐藏字段或使用不一致的校验方式。
 */
final class Csrf
{
    private const TOKEN_KEY = '_csrf_token';

    public static function token(): string
    {
        if (empty($_SESSION[self::TOKEN_KEY])) {
            $_SESSION[self::TOKEN_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::TOKEN_KEY];
    }

    /**
     * 生成适用于表单模板的隐藏字段 HTML。
     *
     * 视图层只需要直接输出返回值即可，无需自己处理转义和字段名。
     */
    public static function input(): string
    {
        $token = self::token();
        return '<input type="hidden" name="_csrf" value="' . Security::escape($token) . '">';
    }

    public static function verify(?string $token): bool
    {
        if (empty($_SESSION[self::TOKEN_KEY]) || $token === null) {
            return false;
        }

        return hash_equals($_SESSION[self::TOKEN_KEY], $token);
    }
}
