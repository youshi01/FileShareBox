<?php

declare(strict_types=1);

/**
 * 项目安装脚本。
 *
 * 用于在 CLI 下初始化数据库、导入表结构、写入默认配置，并确保默认管理员账号存在。
 * 这是首次部署和便携版初始化时的入口，不应通过浏览器直接执行。
 */
require __DIR__ . '/../app/bootstrap.php';

use App\Services\ConfigService;
use App\Services\Database;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must run in CLI mode.\n");
    exit(1);
}

try {
    $host = (string) app_config('db.host', '127.0.0.1');
    $port = (int) app_config('db.port', 3306);
    $database = (string) app_config('db.database', 'filesharebox');
    $charset = (string) app_config('db.charset', 'utf8mb4');
    $username = (string) app_config('db.username', 'root');
    $password = (string) app_config('db.password', '');

    // 先连接到实例级别而不是具体业务库，确保目标数据库不存在时也能先创建库。
    $adminDsn = sprintf('mysql:host=%s;port=%d;charset=%s', $host, $port, $charset);
    $adminPdo = new \PDO($adminDsn, $username, $password, [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $adminPdo->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '``', $database) . '` CHARACTER SET ' . $charset);

    $pdo = Database::pdo();
    $schema = file_get_contents(__DIR__ . '/../database/schema.sql');
    if ($schema === false) {
        throw new RuntimeException('Failed to read schema.sql');
    }

    $pdo->exec($schema);

    $cfgService = new ConfigService();
    $cfgService->save($cfgService->all());

    $username = 'admin';
    $password = 'admin123456';

    $stmt = $pdo->prepare('SELECT id FROM admin_users WHERE username = :username LIMIT 1');
    $stmt->execute(['username' => $username]);
    $exists = $stmt->fetch();

    if (!$exists) {
        $insert = $pdo->prepare('INSERT INTO admin_users (username, password_hash, created_at, updated_at) VALUES (:username, :password_hash, NOW(), NOW())');
        $insert->execute([
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);
    }

    fwrite(STDOUT, "Install completed.\n");
    fwrite(STDOUT, "Default admin account: admin / admin123456\n");
    fwrite(STDOUT, "Please login and change password immediately.\n");
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Install failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
