<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\Security;
use App\Services\ConfigService;
use App\Services\Logger;
use App\Services\RateLimitService;
use App\Services\ShareService;
use Throwable;

/**
 * 公开接口控制器。
 *
 * 这里只负责把 HTTP 请求转换为业务层调用，并把结果按 JSON / 下载响应返回给前端。
 * 文件、文本、取件、下载的具体规则统一下沉到 ShareService。
 */
final class ApiController
{
    private ShareService $shareService;
    private ConfigService $configService;
    private RateLimitService $rateLimiter;

    public function __construct()
    {
        $this->shareService = new ShareService();
        $this->configService = new ConfigService();
        $this->rateLimiter = new RateLimitService();
    }

    /**
     * 处理文件分享请求。
     *
     * 调用顺序固定为：游客上传开关 -> 上传频控 -> 运行时 413 兜底 -> 文件字段检查 -> 业务层创建分享。
     * 这样可以先拦截明显无效请求，避免大文件或恶意频刷过早进入文件处理逻辑。
     */
    public function shareFile(): void
    {
        try {
            $this->ensureGuestUploadAllowed();
            $this->enforceUploadRateLimit();

            if ($this->isOversizedRequest()) {
                Response::json([
                    'ok' => false,
                    'message' => 'Uploaded file exceeds current server runtime upload limit.',
                ], 413);
            }

            if (!isset($_FILES['file'])) {
                Response::json(['ok' => false, 'message' => 'File is required.'], 422);
            }

            $input = $this->input();
            $ip = Security::clientIp();
            $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');

            $result = $this->shareService->createFileShare($_FILES['file'], $input, $ip, $ua);
            Response::json($result, $result['ok'] ? 200 : 422);
        } catch (Throwable $e) {
            Logger::error($e->getMessage());
            Response::json(['ok' => false, 'message' => 'Internal server error.'], 500);
        }
    }

    public function shareText(): void
    {
        try {
            $this->ensureGuestUploadAllowed();
            $this->enforceUploadRateLimit();

            $input = $this->input();
            $ip = Security::clientIp();
            $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');

            $result = $this->shareService->createTextShare($input, $ip, $ua);
            Response::json($result, $result['ok'] ? 200 : 422);
        } catch (Throwable $e) {
            Logger::error($e->getMessage());
            Response::json(['ok' => false, 'message' => 'Internal server error.'], 500);
        }
    }

    /**
     * 根据提取码查询分享内容。
     *
     * 失败取件会累计到 fetch_fail 频控桶里，成功后则清空该 IP 的失败计数，
     * 避免用户在输错几次后即使后续输入正确也继续被旧失败记录阻断。
     */
    public function fetchShare(): void
    {
        try {
            $input = $this->input();
            $code = Security::sanitizeCode((string) ($input['code'] ?? ''));
            if ($code === '') {
                Response::json(['ok' => false, 'message' => 'Code is required.'], 422);
            }

            $ip = Security::clientIp();
            $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
            $window = (int) $this->configService->get('fetch_fail_window_seconds', '300');
            $maxHits = (int) $this->configService->get('fetch_fail_max_hits', '8');
            $blockMinutes = (int) $this->configService->get('fetch_fail_block_minutes', '10');

            $blocked = $this->rateLimiter->checkBlocked($ip, 'fetch_fail');
            if (!$blocked['ok']) {
                Response::json(['ok' => false, 'message' => $blocked['message']], 429);
            }

            $result = $this->shareService->fetchByCode($code, $ip, $ua);
            if (!$result['ok']) {
                $this->rateLimiter->hit($ip, 'fetch_fail', $window, $maxHits, $blockMinutes);
                Response::json($result, 404);
            }

            $this->rateLimiter->clear($ip, 'fetch_fail');
            Response::json($result, 200);
        } catch (Throwable $e) {
            Logger::error($e->getMessage());
            Response::json(['ok' => false, 'message' => 'Internal server error.'], 500);
        }
    }

    public function download(): void
    {
        try {
            $id = (int) ($_GET['id'] ?? 0);
            $code = Security::sanitizeCode((string) ($_GET['code'] ?? ''));
            if ($id <= 0 || $code === '') {
                Response::json(['ok' => false, 'message' => 'Invalid download params.'], 422);
            }

            $ip = Security::clientIp();
            $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
            $result = $this->shareService->downloadFile($id, $code, $ip, $ua);
            if (!$result['ok']) {
                Response::json($result, 404);
            }

            $data = $result['data'];
            $name = basename((string) $data['file_name']);
            $fallbackName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name) ?: 'download.bin';
            $disposition = "attachment; filename=\"{$fallbackName}\"; filename*=UTF-8''" . rawurlencode($name);

            header('Content-Description: File Transfer');
            header('Content-Type: ' . ($data['mime_type'] ?: 'application/octet-stream'));
            header('Content-Disposition: ' . $disposition);
            header('Content-Length: ' . (string) $data['file_size']);
            header('Cache-Control: no-store, no-cache, must-revalidate');

            readfile((string) $data['absolute_path']);
            exit;
        } catch (Throwable $e) {
            Logger::error($e->getMessage());
            Response::json(['ok' => false, 'message' => 'Internal server error.'], 500);
        }
    }

    public function detail(): void
    {
        $code = Security::sanitizeCode((string) ($_GET['code'] ?? ''));
        if ($code === '') {
            Response::json(['ok' => false, 'message' => 'Code is required.'], 422);
        }

        $result = $this->shareService->detailByCode($code);
        Response::json($result, $result['ok'] ? 200 : 404);
    }

    /**
     * 统一读取输入。
     *
     * 表单请求优先取 $_POST；若为空，再尝试把请求体解析成 JSON，
     * 这样同一套控制器既能服务传统表单，也能兼容 fetch/json 请求。
     */
    private function input(): array
    {
        if (!empty($_POST)) {
            return $_POST;
        }

        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $json = json_decode($raw, true);
        return is_array($json) ? $json : [];
    }

    private function ensureGuestUploadAllowed(): void
    {
        if ($this->configService->get('allow_guest_upload', '1') !== '1') {
            Response::json(['ok' => false, 'message' => 'Guest upload is disabled by administrator.'], 403);
        }
    }

    private function enforceUploadRateLimit(): void
    {
        $ip = Security::clientIp();

        $window = (int) $this->configService->get('upload_window_seconds', (string) app_config('limits.upload_window_seconds', 300));
        $maxHits = (int) $this->configService->get('upload_max_hits', (string) app_config('limits.upload_max_hits', 10));
        $blockMinutes = (int) $this->configService->get('upload_block_minutes', (string) app_config('limits.upload_block_minutes', 10));

        $result = $this->rateLimiter->enforce($ip, 'upload', $window, $maxHits, $blockMinutes);
        if (!$result['ok']) {
            Response::json(['ok' => false, 'message' => $result['message']], 429);
        }
    }

    private function isOversizedRequest(): bool
    {
        $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        $limit = $this->iniSizeToBytes((string) ini_get('post_max_size'));

        return $contentLength > 0 && $limit > 0 && $contentLength > $limit;
    }

    private function iniSizeToBytes(string $value): int
    {
        return $this->configService->iniSizeToBytes($value);
    }
}
