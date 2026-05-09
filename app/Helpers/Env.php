<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * 环境变量读取工具。
 *
 * 统一从 $_ENV、$_SERVER 和进程环境中取值，
 * 并在值为空时回退到调用方给定的默认值。
 */
final class Env
{
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return $value;
    }
}
