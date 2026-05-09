<?php

declare(strict_types=1);

/**
 * 应用启动文件。
 *
 * 负责加载 .env、注册自动加载、初始化配置和时区，并在首次进入请求时启动 Session。
 * 同时定义全局的 app_config() 访问入口，供控制器、服务和工具层按点路径读取配置。
 */
if (!function_exists('app_config')) {
    // 优先把项目根目录下的 .env 注入到当前进程，便于本地部署和便携运行时复用同一套配置。
    $envFile = dirname(__DIR__) . '/.env';
    if (is_file($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if ($key === '') {
                continue;
            }

            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv($key . '=' . $value);
        }
    }

    // 采用最轻量的 PSR-4 风格自动加载，把 App\\ 命名空间映射到 app/ 目录。
    spl_autoload_register(static function (string $class): void {
        $prefix = 'App\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relative = substr($class, strlen($prefix));
        $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require $file;
        }
    });

    $appConfig = require __DIR__ . '/../config/config.php';

    // 时区统一在启动阶段设置，避免日期计算、过期时间和日志时间不一致。
    date_default_timezone_set((string) ($appConfig['app']['timezone'] ?? 'Asia/Shanghai'));

    // Session 仅在尚未启动时初始化，并强制启用 HttpOnly / SameSite，减少后台登录态暴露面。
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'httponly' => true,
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    /**
     * 通过点路径读取配置，例如 app_config('db.host')。
     *
     * 这里会在首次调用时缓存整个配置数组，避免在一次请求里反复 require 配置文件。
     */
    function app_config(?string $key = null, mixed $default = null): mixed
    {
        static $config = null;
        if ($config === null) {
            $config = require __DIR__ . '/../config/config.php';
        }

        if ($key === null || $key === '') {
            return $config;
        }

        $segments = explode('.', $key);
        $value = $config;
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}
