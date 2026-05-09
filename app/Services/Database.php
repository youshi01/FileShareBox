<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use PDOException;
use RuntimeException;

/**
 * PDO 连接工厂。
 *
 * 通过单例方式在一次请求内复用数据库连接，并统一启用异常模式和原生预处理，
 * 避免不同服务层各自创建连接或退回到不安全的模拟预处理。
 */
final class Database
{
    private static ?PDO $instance = null;

    /**
     * 返回当前请求共享的 PDO 实例。
     *
     * 所有服务层都通过这里拿连接，保证事务、错误处理和默认抓取模式一致。
     */
    public static function pdo(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $host = (string) app_config('db.host');
        $port = (int) app_config('db.port');
        $database = (string) app_config('db.database');
        $charset = (string) app_config('db.charset');
        $username = (string) app_config('db.username');
        $password = (string) app_config('db.password');

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $database, $charset);

        try {
            self::$instance = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('Database connection failed: ' . $e->getMessage());
        }

        return self::$instance;
    }
}
