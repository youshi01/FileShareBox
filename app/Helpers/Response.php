<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * HTTP 响应工具。
 *
 * 提供最常用的 JSON 输出和重定向能力，调用后立即终止脚本，
 * 避免控制器在输出响应后继续向下执行。
 */
final class Response
{
    public static function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }
}
