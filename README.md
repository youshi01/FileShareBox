# FileCodeBox PHP (V2)

基于 `PHP + MySQL` 的匿名分享系统，支持文件上传、文本分享、提取码取件、后台管理、频控与自动清理。

项目参考了成品 FileCodeBox 的产品思路，但当前实现为 **PHP 独立版本**，重点是把常用分享流程、后台配置、便携部署和文档说明做完整。

## 1. 当前版本概览

适用场景：

- 临时分享安装包、截图、压缩包、交付资料
- 分享代码片段、部署说明、连接信息、临时文档
- 在内网、私有云或个人服务器中快速搭建“提取码取件”服务

当前版本重点：

- 前台改为 **双页结构**
  - `/`：默认只做提取码取件
  - `/upload`：单独处理上传文件 / 上传文本
- 文件上传支持：
  - 拖拽上传
  - 粘贴上传
  - 批量上传
- 文本分享支持独立表单
- 上传成功后弹窗展示提取码
- 后台支持查看记录、调整规则、修改密码
- 支持上传频控、提取码失败频控、过期清理
- 已同步适配 `dist/portable/app` 便携副本

## 2. 当前已实现能力

### 2.1 前台公共页面

#### 首页 `/`

- 默认只展示提取码取件入口
- 输入提取码后自动识别文件 / 文本记录
- 返回文件下载入口或文本内容详情
- 顶部导航可直接跳转到上传页和后台登录页

#### 上传页 `/upload`

- 第一层切换：
  - 上传文件
  - 上传文本
- 文件上传页内第二层切换：
  - 拖拽上传
  - 粘贴上传
  - 批量上传
- 支持标题、自定义提取码、有效期类型和有效期数值
- 成功后统一弹窗展示提取码和结果信息

### 2.2 分享规则

支持以下有效期类型：

- `day`
- `hour`
- `minute`
- `count`
- `forever`

后台可控制：

- 允许的有效期类型
- 默认有效期类型
- 默认有效期数值
- 最长保存时长 `max_save_seconds`
- 是否允许自定义提取码
- 随机提取码长度

说明：

- 当设置了最长保存时长后，超过限制的时间型分享会被拒绝
- 若后台限制了最长保存时长，`forever` 可被禁用
- 前台展示、后台配置和后端校验已保持一致

### 2.3 后台管理

后台路由：`/admin`

已实现页面：

- 仪表盘
  - 总分享数
  - 有效分享数
  - 文件 / 文本占比
  - 文件记录累计大小
  - 上传目录磁盘占用
  - 最近记录列表
- 分享记录
  - 按类型筛选
  - 按关键词搜索提取码 / 标题 / 文件名
  - 查看状态、过期规则、次数使用情况
  - 删除记录并同步删除物理文件
- 系统设置
  - 站点名称 / 副标题 / 公告
  - 是否显示后台入口
  - 游客上传开关
  - 自定义提取码开关
  - 默认有效期规则
  - 最长保存时长
  - 文本长度限制
  - 上传频控与提取码失败频控
- 修改密码

### 2.4 安全与运维

- 提取码格式校验与唯一性校验
- SQL 预处理
- 视图输出转义
- 管理端 CSRF 防护
- Session 登录态鉴权
- 上传频率限制
- 提取码失败次数限制
- 清理脚本支持过期与失效记录巡检

## 3. 当前未纳入首轮的能力

以下能力在成品 FileCodeBox 中更完整，但当前 PHP 版未实现：

- 分片上传 / 断点续传
- 多存储后端（S3 / OneDrive / OpenDAL 等）
- 国际化
- 独立主题系统
- 前后端分离架构

README 不对这些能力做“已支持”承诺，后续如继续对齐成品，再单独规划。

## 4. 目录结构

- `public/`：Web 入口目录
- `public/index.php`：路由入口
- `public/assets/`：前端静态资源（CSS / JS）
- `app/Controllers/`：控制器层
- `app/Services/`：核心业务层（分享、配置、鉴权、清理、频控）
- `app/Helpers/`：基础工具（安全、响应、视图等）
- `app/Views/`：前台和后台模板
- `config/config.php`：系统配置读取入口
- `database/schema.sql`：数据库表结构
- `storage/uploads/`：上传文件目录
- `storage/logs/`：日志目录
- `scripts/install.php`：初始化脚本
- `scripts/cleanup.php`：清理脚本
- `dist/portable/`：便携运行时和分发目录

## 5. 环境要求

- PHP `8.1+`（推荐 `8.2`）
- MySQL `5.7+` / `8.0+`
- Web Server：Nginx / Apache

## 6. 快速开始

### 6.1 初始化

```bash
php scripts/install.php
```

默认管理员（首次登录后请立即修改）：

- 用户名：`admin`
- 密码：`admin123456`

### 6.2 开发模式运行

```bash
php -S 0.0.0.0:8000 -t public
```

访问：

- 首页：`http://127.0.0.1:8000`
- 上传页：`http://127.0.0.1:8000/upload`
- 后台：`http://127.0.0.1:8000/admin/login`

## 7. 配置说明

### 7.1 `.env` 示例

```env
APP_NAME=FileCodeBox PHP
APP_BASE_URL=http://localhost
APP_TIMEZONE=Asia/Shanghai
APP_DEBUG=0

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=filecodebox
DB_USERNAME=root
DB_PASSWORD=root
DB_CHARSET=utf8mb4

UPLOAD_DIR=
MAX_UPLOAD_MB=5120
MAX_TEXT_LENGTH=20000

UPLOAD_WINDOW_SECONDS=300
UPLOAD_MAX_HITS=10
UPLOAD_BLOCK_MINUTES=10

FETCH_FAIL_WINDOW_SECONDS=300
FETCH_FAIL_MAX_HITS=8
FETCH_FAIL_BLOCK_MINUTES=10

CODE_MIN_LEN=4
CODE_MAX_LEN=32
DEFAULT_CODE_LEN=6
SESSION_TTL=7200
ALLOW_GUEST_UPLOAD=1
```

### 7.2 后台动态配置

后台当前支持在线调整：

- 站点名称
- 首页副标题
- 站点公告
- 是否显示后台入口
- 是否允许游客上传
- 是否允许自定义提取码
- 允许的有效期类型
- 默认有效期类型 / 数值
- 最长保存时长（秒）
- 文本长度限制
- 随机提取码长度
- 上传频控
- 提取码失败频控

### 7.3 上传大小说明

上传大小最终受 **应用配置 + PHP 运行时 + Web Server 运行时** 共同限制。

当前项目默认配置：

- `.env` 中 `MAX_UPLOAD_MB=5120`
- 即应用层目标上限为 **5 GB**

但实际生效值仍取决于：

- `upload_max_filesize`
- `post_max_size`
- Web Server（如 Nginx）的请求体限制

如果后台配置看起来已经调大，但前台仍无法上传更大文件，请同时检查 PHP 和 Nginx / Apache 的运行时设置。

## 8. 前台使用说明

### 8.1 凭提取码取件

1. 打开首页 `/`
2. 输入提取码
3. 系统自动识别是文件还是文本
4. 文件返回下载入口，文本直接展示正文
5. 页面同步展示有效期说明

### 8.2 文件上传

进入 `/upload` 后切到“上传文件”，支持：

- 拖拽上传
- 粘贴上传
- 批量上传

可选参数：

- 标题
- 自定义提取码（若后台允许）
- 有效期类型 / 数值（受后台规则限制）

### 8.3 文本分享

进入 `/upload` 后切到“上传文本”，适合：

- 代码片段
- 部署说明
- 链接备注
- 临时文档

提交成功后同样会返回提取码和有效期信息。

## 9. 接口清单

### 9.1 前台 API

- `POST /api/share/file`：上传文件并生成提取码
- `POST /api/share/text`：分享文本并生成提取码
- `POST /api/share/fetch`：凭提取码取件
- `GET /api/share/download?id=xxx&code=xxx`：下载文件
- `GET /api/share/detail?code=xxx`：查询提取码详情

### 9.2 页面与后台路由

- `GET /`
- `GET /upload`
- `GET /admin/login`
- `POST /admin/login`
- `POST /admin/logout`
- `GET /admin`
- `GET /admin/shares`
- `POST /admin/share/delete`
- `GET /admin/config`
- `POST /admin/config/save`
- `GET /admin/password`
- `POST /admin/password/change`

## 10. 清理建议

建议定时执行 `scripts/cleanup.php`，清理过期和失效记录。

Linux cron 示例：

```bash
*/30 * * * * /usr/bin/php /path/to/project/scripts/cleanup.php >> /path/to/project/storage/logs/cleanup.log 2>&1
```

Windows 任务计划可配置等价命令：

```powershell
php D:\path\to\project\scripts\cleanup.php
```

## 11. 常见问题

### Q1：为什么后台设置了上传大小，但前台还是传不了更大的文件？

上传大小最终受 PHP / Web Server 运行时限制影响。

需要同时检查：

- `.env` 中的 `MAX_UPLOAD_MB`
- `upload_max_filesize`
- `post_max_size`
- Nginx / Apache 的请求体大小限制

### Q2：为什么某些有效期选项在前台不可选？

因为后台可以控制“允许的有效期类型”。

当前前台展示、后台配置和后端校验已统一；如果某项未开放，前台不会展示，后端也会拒绝提交。

### Q3：为什么“永久有效”提交失败？

如果后台设置了 `max_save_seconds > 0`，说明站点已经限制最长保存时长，此时永久有效会被禁用。

### Q4：前台为什么分成首页和上传页？

当前版本已改成双页结构：

- 首页默认只做取件
- 上传单独进入 `/upload`

这样更接近成品 FileCodeBox 的使用路径，也更利于把“取件”和“上传”两种任务分开。

## 12. Windows Portable 打包

本仓库仍包含便携运行时打包脚本，可输出可分发目录和安装器。

### 12.1 生成便携包

```powershell
powershell -ExecutionPolicy Bypass -File scripts/package/build_portable.ps1
```

输出目录：

- `dist/portable`

### 12.2 生成安装器 EXE

```powershell
powershell -ExecutionPolicy Bypass -File scripts/package/build_installer.ps1
```

输出目录：

- `dist/installer`
