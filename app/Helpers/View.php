<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * 轻量模板渲染器。
 *
 * 先把具体模板渲染为内容片段，再选择性套入 layout。
 * 这样既支持完整页面，也支持登录页这类不需要共享布局的模板。
 */
final class View
{
    /**
     * 渲染模板并按需套用布局。
     *
     * $data 会被 extract 到模板作用域；layout 传空字符串时，表示直接输出模板本身，
     * 适合登录页这类独立页面。
     */
    public static function render(string $template, array $data = [], string $layout = 'layout'): void
    {
        $templateFile = __DIR__ . '/../Views/' . $template . '.php';
        if (!is_file($templateFile)) {
            http_response_code(500);
            echo 'Template not found';
            exit;
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $templateFile;
        $content = (string) ob_get_clean();

        if ($layout === '') {
            echo $content;
            return;
        }

        $layoutFile = __DIR__ . '/../Views/' . $layout . '.php';
        if (!is_file($layoutFile)) {
            echo $content;
            return;
        }

        require $layoutFile;
    }
}
