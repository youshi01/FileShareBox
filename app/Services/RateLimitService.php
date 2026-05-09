<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;

/**
 * 频控服务。
 *
 * 用 rate_limits 表记录某个 IP 在某类动作上的命中次数、窗口起点和封禁截止时间，
 * 用于上传、取件失败、后台登录等需要抗刷的入口。
 */
final class RateLimitService
{
    public function checkBlocked(string $ip, string $action): array
    {
        $now = time();
        $record = $this->get($ip, $action);
        if (!$record || empty($record['blocked_until'])) {
            return ['ok' => true, 'message' => ''];
        }

        if (strtotime((string) $record['blocked_until']) <= $now) {
            return ['ok' => true, 'message' => ''];
        }

        return [
            'ok' => false,
            'message' => 'Too many attempts. Try again after ' . $record['blocked_until'],
        ];
    }

    /**
     * 记录一次命中并返回当前频控状态。
     *
     * 如果窗口已过期则重置计数；如果本次命中超过上限，则写入 blocked_until。
     * 调用方通常在真正执行业务动作前先调用它，用返回值决定是否直接拒绝请求。
     */
    public function hit(string $ip, string $action, int $windowSeconds, int $maxHits, int $blockMinutes): array
    {
        $now = new DateTimeImmutable('now');
        $record = $this->get($ip, $action);

        if ($record && !empty($record['blocked_until']) && strtotime((string) $record['blocked_until']) > $now->getTimestamp()) {
            return [
                'ok' => false,
                'message' => 'Too many attempts. Try again after ' . $record['blocked_until'],
            ];
        }

        if ($record && !empty($record['blocked_until']) && strtotime((string) $record['blocked_until']) <= $now->getTimestamp()) {
            $this->upsert($ip, $action, 1, $now->format('Y-m-d H:i:s'), null);
            return ['ok' => true, 'message' => ''];
        }

        if (!$record || strtotime((string) $record['window_start']) <= ($now->getTimestamp() - $windowSeconds)) {
            $this->upsert($ip, $action, 1, $now->format('Y-m-d H:i:s'), null);
            return ['ok' => true, 'message' => ''];
        }

        $nextCount = ((int) $record['hit_count']) + 1;
        $blockedUntil = null;
        if ($nextCount > $maxHits) {
            $blockedUntil = $now->modify('+' . $blockMinutes . ' minutes')->format('Y-m-d H:i:s');
        }

        $this->upsert($ip, $action, $nextCount, (string) $record['window_start'], $blockedUntil);

        if ($blockedUntil !== null) {
            return [
                'ok' => false,
                'message' => 'Too many attempts. IP blocked until ' . $blockedUntil,
            ];
        }

        return ['ok' => true, 'message' => ''];
    }

    public function enforce(string $ip, string $action, int $windowSeconds, int $maxHits, int $blockMinutes): array
    {
        return $this->hit($ip, $action, $windowSeconds, $maxHits, $blockMinutes);
    }

    public function clear(string $ip, string $action): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('DELETE FROM rate_limits WHERE ip = :ip AND action_type = :action');
        $stmt->execute(['ip' => $ip, 'action' => $action]);
    }

    private function get(string $ip, string $action): ?array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT * FROM rate_limits WHERE ip = :ip AND action_type = :action LIMIT 1');
        $stmt->execute(['ip' => $ip, 'action' => $action]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function upsert(string $ip, string $action, int $count, string $windowStart, ?string $blockedUntil): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO rate_limits (ip, action_type, hit_count, window_start, blocked_until)
             VALUES (:ip, :action, :count, :window_start, :blocked_until)
             ON DUPLICATE KEY UPDATE hit_count = VALUES(hit_count), window_start = VALUES(window_start), blocked_until = VALUES(blocked_until)'
        );

        $stmt->execute([
            'ip' => $ip,
            'action' => $action,
            'count' => $count,
            'window_start' => $windowStart,
            'blocked_until' => $blockedUntil,
        ]);
    }
}
