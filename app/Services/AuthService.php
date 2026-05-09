<?php

declare(strict_types=1);

namespace App\Services;

/**
 * 后台认证服务。
 *
 * 负责管理员登录、登出、会话续期和密码修改。
 * 登录失败频控、Session 写入和超时失效都统一在这里处理，避免散落在控制器中。
 */
final class AuthService
{
    private RateLimitService $rateLimiter;
    private AccessLogService $logService;

    public function __construct()
    {
        $this->rateLimiter = new RateLimitService();
        $this->logService = new AccessLogService();
    }

    /**
     * 执行管理员登录。
     *
     * 登录先走独立的 login_fail 频控，再校验用户名密码；
     * 只有成功登录后才会清空失败计数并刷新 Session，降低暴力尝试风险。
     */
    public function login(string $username, string $password, string $ip, string $ua = ''): array
    {
        $window = (int) app_config('limits.fetch_fail_window_seconds', 300);
        $maxHits = 6;
        $block = 15;

        $rate = $this->rateLimiter->enforce($ip, 'login_fail', $window, $maxHits, $block);
        if (!$rate['ok']) {
            return ['ok' => false, 'message' => $rate['message']];
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT id, username, password_hash FROM admin_users WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => $username]);
        $admin = $stmt->fetch();

        if (!$admin || !password_verify($password, (string) $admin['password_hash'])) {
            $this->logService->log(null, 'login', $ip, $ua, 'admin login failed');
            return ['ok' => false, 'message' => 'Invalid username or password.'];
        }

        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $admin['id'];
        $_SESSION['admin_username'] = (string) $admin['username'];
        $_SESSION['admin_last_activity'] = time();

        $update = $pdo->prepare('UPDATE admin_users SET last_login_at = NOW(), last_login_ip = :ip WHERE id = :id');
        $update->execute(['ip' => $ip, 'id' => (int) $admin['id']]);

        $this->rateLimiter->clear($ip, 'login_fail');
        $this->logService->log(null, 'login', $ip, $ua, 'admin login success');

        return ['ok' => true, 'message' => ''];
    }

    public function logout(): void
    {
        unset($_SESSION['admin_id'], $_SESSION['admin_username'], $_SESSION['admin_last_activity']);
    }

    /**
     * 检查当前后台会话是否仍有效。
     *
     * 只要超过配置的 Session TTL，就主动清空登录态，
     * 避免后台长期挂着旧会话而不失效。
     */
    public function check(): bool
    {
        if (empty($_SESSION['admin_id'])) {
            return false;
        }

        $ttl = (int) app_config('security.session_ttl', 7200);
        $last = (int) ($_SESSION['admin_last_activity'] ?? 0);
        if ($last > 0 && (time() - $last) > $ttl) {
            $this->logout();
            return false;
        }

        $_SESSION['admin_last_activity'] = time();
        return true;
    }

    public function changePassword(int $adminId, string $oldPassword, string $newPassword): array
    {
        if (strlen($newPassword) < 8) {
            return ['ok' => false, 'message' => 'New password must be at least 8 characters.'];
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT password_hash FROM admin_users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $adminId]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($oldPassword, (string) $row['password_hash'])) {
            return ['ok' => false, 'message' => 'Old password is incorrect.'];
        }

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $update = $pdo->prepare('UPDATE admin_users SET password_hash = :hash, updated_at = NOW() WHERE id = :id');
        $update->execute(['hash' => $hash, 'id' => $adminId]);

        return ['ok' => true, 'message' => 'Password updated successfully.'];
    }
}
