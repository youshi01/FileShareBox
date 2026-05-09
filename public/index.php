<?php

declare(strict_types=1);

/**
 * 应用前控制器。
 *
 * 所有 Web 请求都先进入这里，再根据请求方法和路径分发到对应控制器。
 * 这里不承载业务逻辑，只负责启动应用、标准化请求路径、定义路由表和兜底异常处理。
 */
require __DIR__ . '/../app/bootstrap.php';

use App\Controllers\AdminController;
use App\Controllers\ApiController;
use App\Controllers\HomeController;
use App\Services\Logger;

// 统一规范化请求路径，避免 /foo/ 和 /foo 被当成两个不同路由。
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = $path === '/' ? '/' : rtrim($path, '/');
$path = $path === '' ? '/' : $path;
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// 使用 PHP 内置开发服务器时，静态资源应直接返回给服务器处理，避免被路由表吞掉。
if (PHP_SAPI === 'cli-server') {
    $target = __DIR__ . $path;
    if (is_file($target)) {
        return false;
    }
}

$home = new HomeController();
$api = new ApiController();
$admin = new AdminController();

// 路由表只做“方法 + 路径 -> 控制器动作”的静态映射，具体业务由控制器和服务层处理。
$routes = [
    'GET' => [
        '/' => [$home, 'index'],
        '/upload' => [$home, 'upload'],
        '/api/share/download' => [$api, 'download'],
        '/api/share/detail' => [$api, 'detail'],
        '/admin' => [$admin, 'dashboard'],
        '/admin/login' => [$admin, 'loginPage'],
        '/admin/shares' => [$admin, 'shares'],
        '/admin/config' => [$admin, 'config'],
        '/admin/password' => [$admin, 'passwordPage'],
    ],
    'POST' => [
        '/api/share/file' => [$api, 'shareFile'],
        '/api/share/text' => [$api, 'shareText'],
        '/api/share/fetch' => [$api, 'fetchShare'],
        '/admin/login' => [$admin, 'login'],
        '/admin/logout' => [$admin, 'logout'],
        '/admin/share/delete' => [$admin, 'deleteShare'],
        '/admin/config/save' => [$admin, 'saveConfig'],
        '/admin/password/change' => [$admin, 'changePassword'],
    ],
];

try {
    // 命中路由后立即把请求交给对应控制器动作；未命中则返回简单的文本 404。
    if (isset($routes[$method][$path])) {
        [$controller, $action] = $routes[$method][$path];
        $controller->{$action}();
        exit;
    }

    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo '404 Not Found';
} catch (Throwable $e) {
    Logger::error($e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo '500 Internal Server Error';
}
