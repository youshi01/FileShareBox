<?php

declare(strict_types=1);

namespace App\Services;

/**
 * 访问日志服务。
 *
 * 统一记录上传、取件成功/失败、删除等关键动作，
 * 便于后台追踪分享生命周期和定位异常行为。
 */
final class AccessLogService
{
    public function log(?int $shareId, string $action, string $ip, string $userAgent = '', string $remark = ''): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('INSERT INTO access_logs (share_id, action_type, ip, user_agent, remark, created_at) VALUES (:share_id, :action, :ip, :ua, :remark, NOW())');
        $stmt->execute([
            'share_id' => $shareId,
            'action' => $action,
            'ip' => $ip,
            'ua' => substr($userAgent, 0, 255),
            'remark' => substr($remark, 0, 500),
        ]);
    }
}
