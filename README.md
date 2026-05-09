# FileShareBox PHP

基于 `PHP + MySQL` 的匿名分享系统，支持文件上传、文本分享、提取码取件。

## 功能

- 文件上传：拖拽、粘贴、批量上传
- 文本分享：代码片段、临时文档等
- 提取码取件：输入提取码自动识别文件/文本
- 后台管理：记录查看、系统配置、密码修改
- 安全机制：频控、CSRF 防护、SQL 预处理

## 快速部署（Docker）

```bash
git clone https://github.com/youshi01/FileShareBox.git
cd FileShareBox
cp .env.example .env
docker compose up -d
```

访问 `http://localhost:8080`

默认管理员：`admin` / `admin123456`（首次登录后请修改）

### 更新

```bash
docker compose pull
docker compose up -d
```

### 回滚到指定版本

```bash
# 编辑 docker-compose.yml，将 image 改为指定版本
# image: ghcr.io/youshi01/filesharebox:v1.0.0
docker compose pull
docker compose up -d
```

### 查看可用版本

GitHub Packages 页面：`https://github.com/youshi01/FileShareBox/pkgs/container/filesharebox`

## 本地构建（开发）

```bash
docker compose -f docker-compose.dev.yml up -d --build
```

## 手动部署

### 环境要求

- PHP 8.1+（推荐 8.2）
- MySQL 5.7+ / 8.0+
- Nginx / Apache

### 安装

```bash
cp .env.example .env
# 编辑 .env 配置数据库连接等信息
php scripts/install.php
php -S 0.0.0.0:8000 -t public
```

访问 `http://localhost:8000`

## 使用说明

### 取件

1. 打开首页 `/`
2. 输入提取码
3. 文件返回下载入口，文本直接展示

### 上传

进入 `/upload`：

- **文件上传**：支持拖拽、粘贴、批量上传，可设置标题、自定义提取码、有效期
- **文本分享**：适合代码片段、部署说明、临时文档

### 后台

访问 `/admin/login`，可管理：

- 分享记录查看与删除
- 站点名称、公告、上传规则等配置
- 上传频控与提取码失败频控

## 配置说明

编辑 `.env` 文件：

```env
DB_HOST=127.0.0.1
DB_DATABASE=filesharebox
DB_USERNAME=root
DB_PASSWORD=

MAX_UPLOAD_MB=200
ALLOW_GUEST_UPLOAD=1
```

后台 `/admin/config` 支持在线调整大部分配置。

## 清理

建议定时执行清理脚本：

```bash
php scripts/cleanup.php
```

Linux cron 示例：

```bash
*/30 * * * * /usr/bin/php /path/to/scripts/cleanup.php >> /path/to/storage/logs/cleanup.log 2>&1
```
