<?php

declare(strict_types=1);

use App\Helpers\Env;

/**
 * 应用静态配置。
 *
 * 这里负责把 .env 中的原始字符串整理为带类型的配置数组，
 * 供 app_config() 和各服务层读取。动态可调的后台配置不会写回这里，
 * 而是由 ConfigService 从 system_config 表补充覆盖。
 */
return [
    'app' => [
        'name' => Env::get('APP_NAME', 'FileShareBox PHP'),
        'base_url' => rtrim((string) Env::get('APP_BASE_URL', ''), '/'),
        'timezone' => Env::get('APP_TIMEZONE', 'Asia/Shanghai'),
        'debug' => filter_var(Env::get('APP_DEBUG', '0'), FILTER_VALIDATE_BOOL),
    ],
    'db' => [
        'host' => Env::get('DB_HOST', '127.0.0.1'),
        'port' => (int) Env::get('DB_PORT', '3306'),
        'database' => Env::get('DB_DATABASE', 'filesharebox'),
        'username' => Env::get('DB_USERNAME', 'root'),
        'password' => Env::get('DB_PASSWORD', 'root'),
        'charset' => Env::get('DB_CHARSET', 'utf8mb4'),
    ],
    'storage' => [
        'upload_dir' => Env::get('UPLOAD_DIR', dirname(__DIR__) . '/storage/uploads'),
        'public_download_name' => Env::get('DOWNLOAD_NAME_PREFIX', 'download'),
    ],
    'limits' => [
        'max_upload_mb' => (int) Env::get('MAX_UPLOAD_MB', '100'),
        'max_text_length' => (int) Env::get('MAX_TEXT_LENGTH', '20000'),
        'upload_window_seconds' => (int) Env::get('UPLOAD_WINDOW_SECONDS', '300'),
        'upload_max_hits' => (int) Env::get('UPLOAD_MAX_HITS', '10'),
        'upload_block_minutes' => (int) Env::get('UPLOAD_BLOCK_MINUTES', '10'),
        'fetch_fail_window_seconds' => (int) Env::get('FETCH_FAIL_WINDOW_SECONDS', '300'),
        'fetch_fail_max_hits' => (int) Env::get('FETCH_FAIL_MAX_HITS', '8'),
        'fetch_fail_block_minutes' => (int) Env::get('FETCH_FAIL_BLOCK_MINUTES', '10'),
    ],
    'security' => [
        'code_min_len' => (int) Env::get('CODE_MIN_LEN', '4'),
        'code_max_len' => (int) Env::get('CODE_MAX_LEN', '32'),
        'default_code_len' => (int) Env::get('DEFAULT_CODE_LEN', '6'),
        'session_ttl' => (int) Env::get('SESSION_TTL', '7200'),
        'allow_guest_upload' => filter_var(Env::get('ALLOW_GUEST_UPLOAD', '1'), FILTER_VALIDATE_BOOL),
    ],
];
