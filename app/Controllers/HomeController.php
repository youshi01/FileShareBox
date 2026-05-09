<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\View;
use App\Services\ConfigService;

/**
 * 公共页面控制器。
 *
 * 首页 `/` 和上传页 `/upload` 都依赖同一套站点配置、上传限制和表单默认值，
 * 因此这里统一组装公共视图数据，避免模板之间重复读取配置。
 */
final class HomeController
{
    public function index(): void
    {
        View::render('home', $this->publicViewData());
    }

    public function upload(): void
    {
        View::render('upload', $this->publicViewData());
    }

    /**
     * 组装公共前台页面所需的共享数据。
     *
     * 首页和上传页都依赖站点名称、公告、提取码规则、有效期规则和上传上限快照，
     * 因此统一在这里生成，保证两个页面展示和后端校验依据保持一致。
     */
    private function publicViewData(): array
    {
        $configService = new ConfigService();
        $config = $configService->all();

        return [
            'siteName' => $config['site_name'] ?? 'FileShareBox PHP',
            'siteTagline' => $config['site_tagline'] ?? '像取快递一样取文件，匿名分享文本和文件。',
            'siteNotice' => $config['site_notice'] ?? '',
            'showAdminEntry' => ($config['show_admin_entry'] ?? '1') === '1',
            'allowGuestUpload' => ($config['allow_guest_upload'] ?? '1') === '1',
            'csrfInput' => Csrf::input(),
            'csrfToken' => Csrf::token(),
            'defaultExpireStyle' => $configService->effectiveDefaultExpireStyle(),
            'defaultExpireValue' => $config['default_expire_value'] ?? '1',
            'allowCustomCode' => ($config['allow_custom_code'] ?? '1') === '1',
            'allowedExpireStyles' => $configService->allowedExpireStyles(),
            'maxSaveSeconds' => $configService->maxSaveSeconds(),
            'maxTextLength' => (int) ($config['max_text_length'] ?? 20000),
            'codeLength' => (int) ($config['code_length'] ?? 6),
            'uploadLimit' => $configService->uploadLimitSnapshot(),
        ];
    }
}
