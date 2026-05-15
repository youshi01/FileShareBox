# FileShareBox

基于 `PHP + MySQL` 的匿名分享系统，支持文件上传、文本分享、提取码取件。

[![Release](https://img.shields.io/github/v/release/youshi01/FileShareBox)](https://github.com/youshi01/FileShareBox/releases)
[![Docker](https://img.shields.io/badge/docker-ghcr.io-blue)](https://github.com/youshi01/FileShareBox/pkgs/container/filesharebox)

## 功能

- 文件上传：拖拽、粘贴、批量上传
- 文本分享：代码片段、临时文档等
- 提取码取件：输入提取码自动识别文件/文本
- 后台管理：记录查看、系统配置、密码修改
- 安全机制：频控、CSRF 防护、SQL 预处理

## Docker 部署（推荐）

### 一键部署（无需 clone）

```bash
mkdir -p FileShareBox/database && cd FileShareBox && \
  curl -fsSL -o docker-compose.yml https://raw.githubusercontent.com/youshi01/FileShareBox/master/docker-compose.yml && \
  curl -fsSL -o .env.example https://raw.githubusercontent.com/youshi01/FileShareBox/master/.env.example && \
  curl -fsSL -o database/schema.sql https://raw.githubusercontent.com/youshi01/FileShareBox/master/database/schema.sql && \
  cp .env.example .env && \
  docker compose pull && docker compose up -d
```

### 首次部署

```bash
git clone https://github.com/youshi01/FileShareBox.git
cd FileShareBox
cp .env.example .env
docker compose up -d
```

访问 `http://localhost:8080`，默认管理员 `admin` / `admin123456`（首次登录后请修改）。

### 拉取镜像并启动

```bash
docker pull ghcr.io/youshi01/filesharebox:latest
docker compose up -d
```

### 更新版本

```bash
docker compose pull
docker compose up -d
```

### 指定版本

编辑 `docker-compose.yml`，将 image 改为指定版本：

```yaml
image: ghcr.io/youshi01/filesharebox:v1.0.0
```

然后执行：

```bash
docker compose pull && docker compose up -d
```

### 查看日志

```bash
docker compose logs -f app
```

### 停止服务

```bash
docker compose down
```

### 可用版本

- [GitHub Releases](https://github.com/youshi01/FileShareBox/releases) — 版本发布说明
- [GHCR 镜像](https://github.com/youshi01/FileShareBox/pkgs/container/filesharebox) — Docker 镜像列表

### Docker Run（不用 compose）

```bash
# 准备配置文件
mkdir -p FileShareBox/database && cd FileShareBox
curl -fsSL -o .env.example https://raw.githubusercontent.com/youshi01/FileShareBox/master/.env.example
curl -fsSL -o database/schema.sql https://raw.githubusercontent.com/youshi01/FileShareBox/master/database/schema.sql
cp .env.example .env

# 拉取镜像
docker pull mysql:8.0
docker pull ghcr.io/youshi01/filesharebox:latest

# 创建网络
docker network create filesharebox

# 启动 MySQL
docker run -d --name filesharebox-db --network filesharebox \
  -e MYSQL_ROOT_PASSWORD=root \
  -e MYSQL_DATABASE=filesharebox \
  -v mysql_data:/var/lib/mysql \
  -v $(pwd)/database/schema.sql:/docker-entrypoint-initdb.d/init.sql \
  mysql:8.0

# 启动应用
docker run -d --name filesharebox-app --network filesharebox \
  -p 8080:80 \
  -v $(pwd)/.env:/var/www/html/.env \
  -v $(pwd)/storage/uploads:/var/www/html/storage/uploads \
  ghcr.io/youshi01/filesharebox:latest
```

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
DB_HOST=db
DB_DATABASE=filesharebox
DB_USERNAME=root
DB_PASSWORD=root

MAX_UPLOAD_MB=200
ALLOW_GUEST_UPLOAD=1
```

后台 `/admin/config` 支持在线调整大部分配置。

## 清理

Docker 环境下 MySQL 数据持久化在 volume 中，上传文件挂载在 `./storage/uploads`。

建议定时执行清理脚本（自动清理过期记录）：

```bash
# Docker 环境
docker compose exec app php scripts/cleanup.php

# 本地环境
php scripts/cleanup.php
```

Linux cron 示例：

```bash
*/30 * * * * docker compose -f /path/to/docker-compose.yml exec app php scripts/cleanup.php
```

## 二次开发

二开请看 [docs/development.md](docs/development.md)
