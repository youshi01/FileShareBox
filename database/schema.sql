CREATE TABLE IF NOT EXISTS shares (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    share_type ENUM('file','text') NOT NULL,
    code VARCHAR(32) NOT NULL UNIQUE,
    title VARCHAR(255) NULL,
    text_content LONGTEXT NULL,
    file_name VARCHAR(255) NULL,
    file_path VARCHAR(500) NULL,
    file_size BIGINT NULL,
    mime_type VARCHAR(100) NULL,
    expire_style ENUM('day','hour','minute','count','forever') NOT NULL,
    expire_value INT NULL,
    expire_at DATETIME NULL,
    max_fetch_count INT NULL,
    current_fetch_count INT NOT NULL DEFAULT 0,
    status TINYINT NOT NULL DEFAULT 1,
    created_ip VARCHAR(64) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    deleted_at DATETIME NULL,
    INDEX idx_share_type (share_type),
    INDEX idx_status (status),
    INDEX idx_expire_at (expire_at),
    INDEX idx_created_at (created_at),
    INDEX idx_status_expire (status, expire_style, expire_at),
    INDEX idx_status_type_created (status, share_type, created_at),
    INDEX idx_status_code_created (status, code, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admin_users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(64) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    last_login_at DATETIME NULL,
    last_login_ip VARCHAR(64) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS system_config (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) NOT NULL UNIQUE,
    config_value TEXT NOT NULL,
    config_group VARCHAR(50) NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_config_group (config_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS access_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    share_id BIGINT UNSIGNED NULL,
    action_type ENUM('upload','fetch_success','fetch_fail','delete','login') NOT NULL,
    ip VARCHAR(64) NOT NULL,
    user_agent VARCHAR(255) NULL,
    remark VARCHAR(500) NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_share_id (share_id),
    INDEX idx_action_type (action_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rate_limits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(64) NOT NULL,
    action_type VARCHAR(40) NOT NULL,
    hit_count INT NOT NULL DEFAULT 0,
    window_start DATETIME NOT NULL,
    blocked_until DATETIME NULL,
    UNIQUE KEY uk_ip_action (ip, action_type),
    INDEX idx_blocked_until (blocked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
