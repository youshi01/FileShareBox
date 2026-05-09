<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\Response;
use App\Helpers\Security;
use App\Helpers\View;
use App\Services\AuthService;
use App\Services\ConfigService;
use App\Services\Logger;
use App\Services\ShareService;
use Throwable;

/**
 * 管理后台控制器。
 *
 * 负责登录态相关页面和后台操作入口，本身不实现分享业务规则，
 * 只负责权限检查、表单校验、调用服务层并组织后台模板渲染。
 */
final class AdminController
{
    private AuthService $authService;
    private ShareService $shareService;
    private ConfigService $configService;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->shareService = new ShareService();
        $this->configService = new ConfigService();
    }

    public function loginPage(): void
    {
        if ($this->authService->check()) {
            Response::redirect('/admin');
        }

        View::render('admin/login', [
            'csrfInput' => Csrf::input(),
            'flash' => $this->pullFlash(),
        ], '');
    }

    /**
     * 处理后台登录提交。
     *
     * 控制器层先做 CSRF 校验，再把用户名密码交给 AuthService，
     * 登录失败和成功后的提示都通过 flash session 传递给下一次页面渲染。
     */
    public function login(): void
    {
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            $this->flash('error', 'Invalid CSRF token.');
            Response::redirect('/admin/login');
        }

        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $ip = Security::clientIp();
        $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');

        try {
            $result = $this->authService->login($username, $password, $ip, $ua);
            if (!$result['ok']) {
                $this->flash('error', $result['message']);
                Response::redirect('/admin/login');
            }

            Response::redirect('/admin');
        } catch (Throwable $e) {
            Logger::error($e->getMessage());
            $this->flash('error', 'Login failed due to server error.');
            Response::redirect('/admin/login');
        }
    }

    public function logout(): void
    {
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            Response::redirect('/admin');
        }

        $this->authService->logout();
        Response::redirect('/admin/login');
    }

    public function dashboard(): void
    {
        $this->ensureLoggedIn();

        $summary = $this->shareService->summary();
        $recent = $this->shareService->listShares(1, 10);

        View::render('admin/dashboard', [
            'summary' => $summary,
            'recent' => $recent['list'],
            'csrfInput' => Csrf::input(),
            'flash' => $this->pullFlash(),
            'adminUsername' => (string) ($_SESSION['admin_username'] ?? 'admin'),
        ], 'admin/layout');
    }

    public function shares(): void
    {
        $this->ensureLoggedIn();

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $type = (string) ($_GET['type'] ?? '');
        $keyword = trim((string) ($_GET['keyword'] ?? ''));

        $result = $this->shareService->listShares($page, 20, $type ?: null, $keyword ?: null);

        View::render('admin/shares', [
            'rows' => $result['list'],
            'total' => $result['total'],
            'page' => $result['page'],
            'pageSize' => $result['page_size'],
            'type' => $type,
            'keyword' => $keyword,
            'csrfInput' => Csrf::input(),
            'flash' => $this->pullFlash(),
            'adminUsername' => (string) ($_SESSION['admin_username'] ?? 'admin'),
        ], 'admin/layout');
    }

    public function deleteShare(): void
    {
        $this->ensureLoggedIn();

        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            $this->flash('error', 'Invalid CSRF token.');
            Response::redirect('/admin/shares');
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->flash('error', 'Invalid share id.');
            Response::redirect('/admin/shares');
        }

        $ok = $this->shareService->deleteShare($id, Security::clientIp(), (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $this->flash($ok ? 'success' : 'error', $ok ? 'Share deleted.' : 'Share not found.');
        Response::redirect('/admin/shares');
    }

    public function config(): void
    {
        $this->ensureLoggedIn();

        View::render('admin/config', [
            'config' => $this->configService->all(),
            'uploadLimit' => $this->configService->uploadLimitSnapshot(),
            'csrfInput' => Csrf::input(),
            'flash' => $this->pullFlash(),
            'adminUsername' => (string) ($_SESSION['admin_username'] ?? 'admin'),
        ], 'admin/layout');
    }

    /**
     * 保存后台动态配置。
     *
     * 这里会把表单输入先归一化到允许范围，再交给 ConfigService 落库，
     * 避免非法默认值、空的有效期列表或负数限制直接进入系统配置表。
     */
    public function saveConfig(): void
    {
        $this->ensureLoggedIn();

        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            $this->flash('error', 'Invalid CSRF token.');
            Response::redirect('/admin/config');
        }

        $allowedExpireStyles = $_POST['allowed_expire_styles'] ?? [];
        if (!is_array($allowedExpireStyles)) {
            $allowedExpireStyles = [];
        }

        $payload = [
            'site_name' => trim((string) ($_POST['site_name'] ?? 'FileShareBox PHP')),
            'site_tagline' => trim((string) ($_POST['site_tagline'] ?? '像取快递一样取文件，匿名分享文本和文件。')),
            'site_notice' => trim((string) ($_POST['site_notice'] ?? '')),
            'show_admin_entry' => isset($_POST['show_admin_entry']) ? '1' : '0',
            'allow_guest_upload' => isset($_POST['allow_guest_upload']) ? '1' : '0',
            'allow_custom_code' => isset($_POST['allow_custom_code']) ? '1' : '0',
            'allowed_expire_styles' => $this->configService->normalizeAllowedExpireStyles($allowedExpireStyles),
            'default_expire_style' => $this->configService->normalizeExpireStyle((string) ($_POST['default_expire_style'] ?? 'day')),
            'default_expire_value' => (string) max(1, (int) ($_POST['default_expire_value'] ?? 1)),
            'max_save_seconds' => (string) max(0, (int) ($_POST['max_save_seconds'] ?? 0)),
            'max_upload_mb' => (string) max(1, (int) ($_POST['max_upload_mb'] ?? 200)),
            'max_text_length' => (string) max(100, (int) ($_POST['max_text_length'] ?? 20000)),
            'code_length' => (string) max(4, min(12, (int) ($_POST['code_length'] ?? 6))),
            'upload_window_seconds' => (string) max(60, (int) ($_POST['upload_window_seconds'] ?? 300)),
            'upload_max_hits' => (string) max(1, (int) ($_POST['upload_max_hits'] ?? 10)),
            'upload_block_minutes' => (string) max(1, (int) ($_POST['upload_block_minutes'] ?? 10)),
            'fetch_fail_window_seconds' => (string) max(60, (int) ($_POST['fetch_fail_window_seconds'] ?? 300)),
            'fetch_fail_max_hits' => (string) max(1, (int) ($_POST['fetch_fail_max_hits'] ?? 8)),
            'fetch_fail_block_minutes' => (string) max(1, (int) ($_POST['fetch_fail_block_minutes'] ?? 10)),
            'cleanup_interval_minutes' => (string) max(5, (int) ($_POST['cleanup_interval_minutes'] ?? 30)),
            'storage_driver' => (string) ($_POST['storage_driver'] ?? 'local'),
        ];

        $normalizedAllowed = explode(',', $payload['allowed_expire_styles']);
        if (!in_array($payload['default_expire_style'], $normalizedAllowed, true)) {
            $payload['default_expire_style'] = $normalizedAllowed[0] ?? 'day';
        }

        try {
            $this->configService->save($payload);
            $runtimeLimit = $this->configService->effectiveUploadLimitMb();
            $message = 'Configuration saved.';

            if ($runtimeLimit > 0) {
                $message .= ' Current server runtime upload limit is about ' . $runtimeLimit . 'MB.';
            }

            $this->flash('success', $message);
        } catch (Throwable $e) {
            Logger::error($e->getMessage());
            $this->flash('error', 'Failed to save configuration.');
        }

        Response::redirect('/admin/config');
    }

    public function passwordPage(): void
    {
        $this->ensureLoggedIn();

        View::render('admin/password', [
            'csrfInput' => Csrf::input(),
            'flash' => $this->pullFlash(),
            'adminUsername' => (string) ($_SESSION['admin_username'] ?? 'admin'),
        ], 'admin/layout');
    }

    public function changePassword(): void
    {
        $this->ensureLoggedIn();

        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            $this->flash('error', 'Invalid CSRF token.');
            Response::redirect('/admin/password');
        }

        $old = (string) ($_POST['old_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');

        $result = $this->authService->changePassword((int) $_SESSION['admin_id'], $old, $new);
        $this->flash($result['ok'] ? 'success' : 'error', $result['message']);
        Response::redirect('/admin/password');
    }

    private function ensureLoggedIn(): void
    {
        if (!$this->authService->check()) {
            Response::redirect('/admin/login');
        }
    }

    private function flash(string $type, string $message): void
    {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }

    private function pullFlash(): ?array
    {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return $flash;
    }
}
