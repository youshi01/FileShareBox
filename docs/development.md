# FileShareBox PHP 开发文档

本文档面向二次开发和维护人员，说明当前 PHP 版本的项目结构、请求流程、接口实现、核心服务以及数据库字段含义。

## 1. 项目目标与当前形态

当前项目是一个基于 `PHP + MySQL` 的匿名分享系统，核心能力包括：

- 文件分享
- 文本分享
- 提取码取件
- 后台管理
- 上传与取件频控
- 过期清理

当前前台为双页结构：

- `/`：默认只做提取码取件
- `/upload`：单独处理上传文件 / 上传文本

后台入口为：

- `/admin/login`
- `/admin`

## 2. 技术结构

### 2.1 目录说明

- `public/index.php`：前端控制器，所有 Web 请求入口
- `app/bootstrap.php`：环境变量加载、自动加载、session 初始化、`app_config()` 注册
- `config/config.php`：应用配置装配
- `app/Controllers/`：控制器层
- `app/Services/`：业务服务层
- `app/Helpers/`：辅助类
- `app/Views/`：页面模板
- `database/schema.sql`：数据库结构
- `scripts/install.php`：安装初始化脚本
- `scripts/cleanup.php`：清理脚本
- `storage/uploads/`：上传文件目录
- `docs/`：项目文档

### 2.2 启动流程

请求进入 [public/index.php](../public/index.php) 后：

1. `require app/bootstrap.php`
2. 读取 `REQUEST_URI` 与 `REQUEST_METHOD`
3. 根据 `$routes` 查找目标控制器方法
4. 调用对应的 Controller Action
5. Controller 再调用 Service 完成业务处理
6. 最终输出 JSON、下载响应或渲染视图

### 2.3 bootstrap 做了什么

[app/bootstrap.php](../app/bootstrap.php) 负责：

- 从 `.env` 加载环境变量到 `$_ENV` / `$_SERVER`
- 注册 `App\` 命名空间自动加载
- 读取 `config/config.php`
- 设置时区
- 初始化 session
- 注册全局函数 `app_config()`

## 3. 请求路由

路由定义位于 [public/index.php](../public/index.php)。

### 3.1 GET 路由

| 路径 | 控制器 | 说明 |
|---|---|---|
| `/` | `HomeController::index` | 首页，只负责提取码取件 |
| `/upload` | `HomeController::upload` | 上传页，处理文件/文本分享 |
| `/api/share/download` | `ApiController::download` | 文件下载接口 |
| `/api/share/detail` | `ApiController::detail` | 查询提取码详情 |
| `/admin` | `AdminController::dashboard` | 后台仪表盘 |
| `/admin/login` | `AdminController::loginPage` | 后台登录页 |
| `/admin/shares` | `AdminController::shares` | 分享记录列表 |
| `/admin/config` | `AdminController::config` | 系统配置页 |
| `/admin/password` | `AdminController::passwordPage` | 修改密码页 |

### 3.2 POST 路由

| 路径 | 控制器 | 说明 |
|---|---|---|
| `/api/share/file` | `ApiController::shareFile` | 上传文件并生成提取码 |
| `/api/share/text` | `ApiController::shareText` | 保存文本并生成提取码 |
| `/api/share/fetch` | `ApiController::fetchShare` | 凭提取码取件 |
| `/admin/login` | `AdminController::login` | 后台登录提交 |
| `/admin/logout` | `AdminController::logout` | 后台退出登录 |
| `/admin/share/delete` | `AdminController::deleteShare` | 删除分享记录 |
| `/admin/config/save` | `AdminController::saveConfig` | 保存后台配置 |
| `/admin/password/change` | `AdminController::changePassword` | 修改管理员密码 |

## 4. 控制器设计

## 4.1 HomeController

文件：[app/Controllers/HomeController.php](../app/Controllers/HomeController.php)

职责：

- 组织前台公共页面所需的数据
- 渲染首页和上传页

主要方法：

- `index()`：渲染首页 `home`
- `upload()`：渲染上传页 `upload`
- `publicViewData()`：统一准备前台页面所需配置，如：
  - 站点名称
  - 副标题
  - 是否允许游客上传
  - CSRF 输入框
  - 默认有效期
  - 是否允许自定义提取码
  - 文本长度限制
  - 上传大小快照

## 4.2 ApiController

文件：[app/Controllers/ApiController.php](../app/Controllers/ApiController.php)

职责：

- 提供前台分享与取件接口
- 做参数读取、频控校验、访客上传校验
- 调用 `ShareService` 返回 JSON 或文件下载响应

主要方法：

### `shareFile()`

处理文件上传。

流程：

1. 校验是否允许游客上传
2. 校验上传频控
3. 校验请求体是否超过 PHP `post_max_size`
4. 校验 `$_FILES['file']`
5. 读取表单输入
6. 调用 `ShareService::createFileShare()`
7. 返回 JSON

### `shareText()`

处理文本分享。

流程：

1. 校验游客上传是否开启
2. 校验上传频控
3. 读取输入参数
4. 调用 `ShareService::createTextShare()`
5. 返回 JSON

### `fetchShare()`

凭提取码取件。

流程：

1. 读取并清洗 `code`
2. 检查失败频控是否已封禁
3. 调用 `ShareService::fetchByCode()`
4. 若失败则记录失败频控
5. 若成功则清空该 IP 的失败频控
6. 返回 JSON

### `download()`

处理文件下载。

流程：

1. 校验 `id` 和 `code`
2. 调用 `ShareService::downloadFile()`
3. 返回真实文件流
4. 设置 `Content-Disposition`、`Content-Type`、`Content-Length`

### `detail()`

按提取码查询详情，不消耗下载行为。

用于：

- 前端预查询
- 第三方扩展
- 调试/联调

## 4.3 AdminController

文件：[app/Controllers/AdminController.php](../app/Controllers/AdminController.php)

职责：

- 管理后台登录态
- 后台页面渲染
- 配置保存
- 删除记录
- 修改密码

主要方法：

### `loginPage()`

- 已登录则跳转 `/admin`
- 未登录则渲染登录页

### `login()`

- 校验 CSRF
- 调用 `AuthService::login()`
- 成功后跳转 `/admin`
- 失败后写入 flash 并返回登录页

### `dashboard()`

- 登录校验
- 调用 `ShareService::summary()`
- 调用 `ShareService::listShares(1, 10)` 获取最近记录
- 渲染仪表盘

### `shares()`

- 登录校验
- 支持分页、类型筛选、关键词搜索
- 调用 `ShareService::listShares()` 渲染记录页

### `deleteShare()`

- 登录校验
- 校验 CSRF
- 调用 `ShareService::deleteShare()`
- 删除文件记录时会同步删除物理文件

### `config()`

- 读取系统配置
- 同时返回上传大小快照 `uploadLimitSnapshot()`
- 渲染配置页

### `saveConfig()`

- 登录校验
- 校验 CSRF
- 对配置项做基础归一化
- 调用 `ConfigService::save()`
- 额外提示当前运行时上传上限

### `passwordPage()` / `changePassword()`

- 渲染修改密码页
- 调用 `AuthService::changePassword()` 完成密码变更

## 5. 核心服务设计

## 5.1 ShareService

文件：[app/Services/ShareService.php](../app/Services/ShareService.php)

这是系统最核心的业务服务，负责分享创建、提取、下载、记录管理和摘要统计。

### 主要能力

#### 1) `createTextShare()`

作用：创建文本分享。

关键逻辑：

- 校验 `text_content` 非空
- 根据配置 `max_text_length` 校验长度
- 解析提取码：`resolveCode()`
- 解析有效期：`resolveExpire()`
- 标题为空时自动截取正文前 30 个字符
- 插入 `shares` 表
- 写入 `access_logs`

#### 2) `createFileShare()`

作用：创建文件分享。

关键逻辑：

- 校验 PHP 上传错误码
- 使用 `ConfigService::effectiveUploadLimitMb()` 校验实际上传上限
- 解析提取码与有效期
- 安全化原始文件名 `Security::safeFileName()`
- 生成物理存储文件名
- 保存文件到 `storage/uploads/`
- 插入 `shares` 表
- 更新磁盘用量缓存 `upload_disk_usage_bytes`
- 写入访问日志

#### 3) `fetchByCode()`

作用：按提取码取件。

逻辑分支：

- 如果是文本：
  - 调用 `consumeTextFetch()`
  - 消耗一次取件次数
  - 若次数耗尽则自动失效
- 如果是文件：
  - 调用 `assertShareAvailable()` 做只读可用性判断
  - 返回下载地址 `/api/share/download?...`

#### 4) `detailByCode()`

作用：查询提取码详情，不执行真实下载。

#### 5) `downloadFile()`

作用：文件下载前的最终校验。

关键逻辑：

- 调用 `consumeFileDownload()`
- 在事务中锁定记录
- 校验状态、过期时间、次数上限
- 增加 `current_fetch_count`
- 若是按次数过期且已用尽，则自动把 `status` 置为 `0`
- 返回物理文件绝对路径供控制器输出

#### 6) `listShares()`

作用：后台记录列表与仪表盘最近记录数据源。

支持：

- 分页
- 按 `share_type` 过滤
- 按提取码、标题、文件名搜索

#### 7) `deleteShare()`

作用：后台删除分享记录。

行为：

- 先查记录
- 若为文件记录且文件存在，则删除物理文件
- 删除数据库记录
- 对文件记录同步更新磁盘容量缓存
- 写入删除日志

#### 8) `summary()`

作用：生成后台仪表盘摘要。

返回：

- 总记录数
- 有效记录数
- 文件记录数
- 文本记录数
- 文件记录累计大小
- 上传目录磁盘占用缓存
- 磁盘用量更新时间

### ShareService 内部关键方法

#### `resolveCode()`

用于处理提取码：

- 若用户传入自定义提取码：
  - 检查是否开启自定义提取码
  - 校验格式
  - 校验唯一性
- 若未传入：
  - 根据配置的 `code_length` 自动生成随机提取码

#### `resolveExpire()`

用于处理有效期策略。

支持：

- `day`
- `hour`
- `minute`
- `count`
- `forever`

输出内容：

- `expire_style`
- `expire_value`
- `expire_at`
- `max_fetch_count`

#### `assertShareAvailable()`

校验分享是否可取：

- `status` 是否仍有效
- 时间型是否过期
- 次数型是否已耗尽

#### `consumeTextFetch()` / `consumeFileDownload()`

这两个方法都使用数据库事务 + `FOR UPDATE`：

- 防止并发下重复消耗次数
- 防止次数限制被穿透

#### `bumpDiskUsageCache()`

把上传目录大小缓存写入 `system_config`，避免每次后台打开都全盘扫描。

## 5.2 ConfigService

文件：[app/Services/ConfigService.php](../app/Services/ConfigService.php)

职责：

- 读取 / 保存 `system_config`
- 管理默认配置
- 归一化有效期配置
- 计算上传大小快照

### 重点方法

- `all()`：返回所有配置，带默认值合并
- `get($key)`：取单项配置
- `save($config)`：批量保存配置
- `allowedExpireStyles()`：返回当前允许的有效期类型
- `effectiveDefaultExpireStyle()`：保证默认值一定在允许列表中
- `maxSaveSeconds()`：返回最长保存时长
- `configuredUploadLimitMb()`：后台配置值
- `phpUploadLimitMb()`：PHP `upload_max_filesize`
- `phpPostLimitMb()`：PHP `post_max_size`
- `effectiveUploadLimitMb()`：应用配置与运行时上限的最小值
- `uploadLimitSnapshot()`：给前台和后台展示的上传上限快照

### 配置分组逻辑

保存配置时会自动归类到 `system_config.config_group`：

- `display`
- `rules`
- `upload`
- `security`
- `storage`
- `general`

## 5.3 AuthService

文件：[app/Services/AuthService.php](../app/Services/AuthService.php)

职责：

- 后台登录
- 登录态检查
- 退出登录
- 修改密码

关键点：

- 登录失败会走 `RateLimitService` 的 `login_fail` 限制
- 登录成功后：
  - `session_regenerate_id(true)`
  - 写入 `admin_id`
  - 写入 `admin_username`
  - 更新 `last_login_at` 和 `last_login_ip`
- `check()` 会基于 `SESSION_TTL` 检查 session 是否超时
- `changePassword()` 使用 `password_hash()` 更新密码

## 5.4 RateLimitService

文件：[app/Services/RateLimitService.php](../app/Services/RateLimitService.php)

职责：

- IP 级别频控
- 基于 `rate_limits` 表存储窗口与封禁状态

主要方法：

- `enforce()`：执行频控
- `hit()`：累加命中次数
- `checkBlocked()`：检查是否仍在封禁期
- `clear()`：清除某个 IP 的某种行为限制

当前主要 action：

- `upload`
- `fetch_fail`
- `login_fail`

## 5.5 CleanupService

文件：[app/Services/CleanupService.php](../app/Services/CleanupService.php)

职责：

- 清理过期记录
- 删除失效文件
- 重建上传目录大小缓存

主要方法：

- `run()`：总入口
- `cleanupExpiredShares()`：标记过期或次数耗尽记录，并尝试删除文件
- `cleanupDeletedFiles()`：处理已失效文件记录的残余文件
- `refreshUploadDiskUsage()`：重新计算上传目录大小

## 5.6 AccessLogService

文件：[app/Services/AccessLogService.php](../app/Services/AccessLogService.php)

职责：

- 写入 `access_logs`
- 记录上传、取件成功、取件失败、删除、登录等行为

## 5.7 Database

文件：[app/Services/Database.php](../app/Services/Database.php)

职责：

- 提供 PDO 单例连接
- 统一设置：
  - `ERRMODE_EXCEPTION`
  - `FETCH_ASSOC`
  - 禁用模拟预处理

## 6. 辅助类说明

## 6.1 Security

文件：[app/Helpers/Security.php](../app/Helpers/Security.php)

提供：

- `escape()`：HTML 转义
- `sanitizeCode()`：提取码清洗（转大写，只保留 `A-Z0-9_-`）
- `validateCode()`：提取码格式校验
- `safeFileName()`：文件名安全化
- `clientIp()`：优先从 `X-Forwarded-For` / `X-Real-IP` / `REMOTE_ADDR` 取客户端 IP

## 6.2 Response

文件：[app/Helpers/Response.php](../app/Helpers/Response.php)

提供：

- `json()`：输出 JSON 并结束请求
- `redirect()`：HTTP 跳转并结束请求

## 6.3 View

文件：[app/Helpers/View.php](../app/Helpers/View.php)

负责模板渲染：

- 先渲染页面模板
- 再决定是否套 layout
- 管理页使用 `admin/layout`
- 登录页等可传空 layout 直接渲染

## 7. 接口文档

## 7.1 `POST /api/share/file`

### 作用

上传文件并生成提取码。

### 请求方式

`multipart/form-data`

### 表单字段

| 字段 | 必填 | 说明 |
|---|---|---|
| `file` | 是 | 上传文件 |
| `title` | 否 | 自定义标题 |
| `code` | 否 | 自定义提取码 |
| `expire_style` | 否 | `day/hour/minute/count/forever` |
| `expire_value` | 否 | 有效期数值 |
| `_csrf` | 是 | CSRF Token |

### 成功响应示例

```json
{
  "ok": true,
  "message": "File shared successfully.",
  "data": {
    "id": 12,
    "code": "ABCD12",
    "title": "安装包.zip",
    "file_name": "安装包.zip",
    "file_size": 102400,
    "expire_style": "day",
    "expire_value": 1,
    "expire_at": "2026-03-31 12:00:00",
    "max_fetch_count": null,
    "expire_label": "到期时间 2026-03-31 12:00:00"
  }
}
```

### 失败场景

- 游客上传被禁用
- 上传频控触发
- 文件超出当前有效上传上限
- 提取码重复或格式错误
- 有效期不合法

## 7.2 `POST /api/share/text`

### 作用

保存文本并生成提取码。

### 请求字段

| 字段 | 必填 | 说明 |
|---|---|---|
| `text_content` | 是 | 文本正文 |
| `title` | 否 | 标题 |
| `code` | 否 | 自定义提取码 |
| `expire_style` | 否 | 有效期类型 |
| `expire_value` | 否 | 有效期数值 |
| `_csrf` | 是 | CSRF Token |

### 说明

- 若 `title` 为空，会自动截取正文前 30 个字符作为标题
- 文本长度受 `max_text_length` 限制

## 7.3 `POST /api/share/fetch`

### 作用

凭提取码取件。

### 请求字段

| 字段 | 必填 | 说明 |
|---|---|---|
| `code` | 是 | 提取码 |
| `_csrf` | 是 | CSRF Token |

### 返回特点

- 若记录是文本：直接返回文本内容
- 若记录是文件：返回下载地址 `download_url`

### 成功响应字段

常见字段：

- `id`
- `share_type`
- `code`
- `title`
- `file_name`
- `file_size`
- `created_at`
- `expire_style`
- `expire_value`
- `expire_at`
- `max_fetch_count`
- `current_fetch_count`
- `remaining_fetch_count`
- `expire_label`
- `text_content`（文本分享时）
- `download_url`（文件分享时）

## 7.4 `GET /api/share/download`

### 参数

| 参数 | 必填 | 说明 |
|---|---|---|
| `id` | 是 | 分享 ID |
| `code` | 是 | 提取码 |

### 行为

- 校验文件记录是否可下载
- 扣减 / 累加取件次数
- 输出真实文件流

## 7.5 `GET /api/share/detail`

### 参数

| 参数 | 必填 | 说明 |
|---|---|---|
| `code` | 是 | 提取码 |

### 作用

查询记录详情但不执行下载。

## 7.6 后台接口

### `POST /admin/login`

字段：

- `username`
- `password`
- `_csrf`

### `POST /admin/logout`

字段：

- `_csrf`

### `POST /admin/share/delete`

字段：

- `id`
- `_csrf`

### `POST /admin/config/save`

字段较多，主要包括：

- `site_name`
- `site_tagline`
- `site_notice`
- `show_admin_entry`
- `allow_guest_upload`
- `allow_custom_code`
- `allowed_expire_styles[]`
- `default_expire_style`
- `default_expire_value`
- `max_save_seconds`
- `max_upload_mb`
- `max_text_length`
- `code_length`
- `upload_window_seconds`
- `upload_max_hits`
- `upload_block_minutes`
- `fetch_fail_window_seconds`
- `fetch_fail_max_hits`
- `fetch_fail_block_minutes`
- `cleanup_interval_minutes`
- `storage_driver`
- `_csrf`

### `POST /admin/password/change`

字段：

- `old_password`
- `new_password`
- `_csrf`

## 8. 数据库设计

数据库结构见 [database/schema.sql](../database/schema.sql)。

## 8.1 `shares` 表

作用：存储文件 / 文本分享记录，是系统核心业务表。

| 字段 | 类型 | 作用 |
|---|---|---|
| `id` | `BIGINT UNSIGNED` | 主键 |
| `share_type` | `ENUM('file','text')` | 分享类型，文件或文本 |
| `code` | `VARCHAR(32)` | 提取码，唯一 |
| `title` | `VARCHAR(255)` | 记录标题 |
| `text_content` | `LONGTEXT` | 文本分享正文，仅文本类型使用 |
| `file_name` | `VARCHAR(255)` | 原始文件名，仅文件类型使用 |
| `file_path` | `VARCHAR(500)` | 存储相对路径，仅文件类型使用 |
| `file_size` | `BIGINT` | 文件大小（字节） |
| `mime_type` | `VARCHAR(100)` | 文件 MIME 类型 |
| `expire_style` | `ENUM(...)` | 有效期策略 |
| `expire_value` | `INT` | 有效期数值 |
| `expire_at` | `DATETIME` | 时间型过期时间 |
| `max_fetch_count` | `INT` | 次数型上限 |
| `current_fetch_count` | `INT` | 当前已提取次数 |
| `status` | `TINYINT` | 状态，`1` 有效，`0` 失效 |
| `created_ip` | `VARCHAR(64)` | 创建者 IP |
| `created_at` | `DATETIME` | 创建时间 |
| `updated_at` | `DATETIME` | 更新时间 |
| `deleted_at` | `DATETIME` | 失效或逻辑删除时间 |

索引说明：

- `idx_share_type`：按类型过滤
- `idx_status`：按状态过滤
- `idx_expire_at`：清理过期记录
- `idx_created_at`：按时间排序
- `idx_status_expire`：状态+有效期联合过滤
- `idx_status_type_created`：后台按状态/类型/时间查询
- `idx_status_code_created`：提取码相关检索优化

## 8.2 `admin_users` 表

作用：后台管理员账号表。

| 字段 | 类型 | 作用 |
|---|---|---|
| `id` | `BIGINT UNSIGNED` | 主键 |
| `username` | `VARCHAR(64)` | 管理员用户名，唯一 |
| `password_hash` | `VARCHAR(255)` | 密码哈希 |
| `last_login_at` | `DATETIME` | 最后登录时间 |
| `last_login_ip` | `VARCHAR(64)` | 最后登录 IP |
| `created_at` | `DATETIME` | 创建时间 |
| `updated_at` | `DATETIME` | 更新时间 |

## 8.3 `system_config` 表

作用：保存后台可变配置。

| 字段 | 类型 | 作用 |
|---|---|---|
| `id` | `BIGINT UNSIGNED` | 主键 |
| `config_key` | `VARCHAR(100)` | 配置键，唯一 |
| `config_value` | `TEXT` | 配置值 |
| `config_group` | `VARCHAR(50)` | 配置分组 |
| `updated_at` | `DATETIME` | 更新时间 |

常见配置键：

- `site_name`
- `site_tagline`
- `site_notice`
- `show_admin_entry`
- `allow_guest_upload`
- `allow_custom_code`
- `allowed_expire_styles`
- `default_expire_style`
- `default_expire_value`
- `max_save_seconds`
- `max_upload_mb`
- `max_text_length`
- `code_length`
- `upload_window_seconds`
- `upload_max_hits`
- `upload_block_minutes`
- `fetch_fail_window_seconds`
- `fetch_fail_max_hits`
- `fetch_fail_block_minutes`
- `cleanup_interval_minutes`
- `storage_driver`
- `upload_disk_usage_bytes`
- `upload_disk_usage_updated_at`

## 8.4 `access_logs` 表

作用：记录关键访问和管理行为，便于审计和问题排查。

| 字段 | 类型 | 作用 |
|---|---|---|
| `id` | `BIGINT UNSIGNED` | 主键 |
| `share_id` | `BIGINT UNSIGNED` | 关联分享记录，可为空 |
| `action_type` | `ENUM(...)` | 行为类型 |
| `ip` | `VARCHAR(64)` | 操作来源 IP |
| `user_agent` | `VARCHAR(255)` | 浏览器 UA |
| `remark` | `VARCHAR(500)` | 备注 |
| `created_at` | `DATETIME` | 记录时间 |

当前常见 `action_type`：

- `upload`
- `fetch_success`
- `fetch_fail`
- `delete`
- `login`

## 8.5 `rate_limits` 表

作用：IP 频控存储表。

| 字段 | 类型 | 作用 |
|---|---|---|
| `id` | `BIGINT UNSIGNED` | 主键 |
| `ip` | `VARCHAR(64)` | 客户端 IP |
| `action_type` | `VARCHAR(40)` | 行为类型，例如 `upload` |
| `hit_count` | `INT` | 当前窗口内命中次数 |
| `window_start` | `DATETIME` | 当前统计窗口起点 |
| `blocked_until` | `DATETIME` | 封禁截止时间 |

约束与索引：

- `uk_ip_action`：保证同一 IP + 行为类型只有一条记录
- `idx_blocked_until`：方便清理和查询封禁状态

## 9. 配置来源与优先级

## 9.1 配置来源

配置主要来自两层：

1. `.env` / `config/config.php`
2. 数据库表 `system_config`

## 9.2 优先级

运行时读取配置时：

- 先取数据库 `system_config`
- 不存在时退回 `ConfigService::$defaults`
- 某些安全边界仍会参考 `app_config()` 中的环境级配置

### 示例

提取码长度、文本长度、上传窗口这类值：

- 默认来自 `.env`
- 后台保存后会进入 `system_config`
- 实际运行时优先取数据库配置

## 10. 安装与初始化

安装脚本：[scripts/install.php](../scripts/install.php)

执行后会：

1. 创建数据库（若不存在）
2. 执行 `database/schema.sql`
3. 把默认配置写入 `system_config`
4. 初始化管理员账户：
   - 用户名：`admin`
   - 密码：`admin123456`

## 11. 清理机制

清理脚本：[scripts/cleanup.php](../scripts/cleanup.php)

最终调用 `CleanupService::run()`，做三件事：

1. 标记过期记录
2. 删除残余文件
3. 刷新上传目录实际磁盘占用

建议通过 cron 或 Windows 任务计划定时执行。

## 12. 二次开发建议

### 12.1 如果要新增新的有效期类型

需要同步修改：

- `database/schema.sql` 中 `shares.expire_style`
- `ShareService::EXPIRE_STYLES`
- `ConfigService::SUPPORTED_EXPIRE_STYLES`
- 后台配置页和前台上传页的可选项

### 12.2 如果要接入新存储后端

当前 `storage_driver` 只是配置占位，文件实际仍写入本地磁盘。

若要扩展，需要重点改造：

- `ShareService::createFileShare()`
- `ShareService::downloadFile()`
- `CleanupService`
- 文件路径字段 `file_path` 的定义方式

### 12.3 如果要增加审计能力

优先扩展：

- `access_logs` 表
- `AccessLogService`
- 后台增加日志查看页

### 12.4 如果要做前后端分离

建议先保留当前路由协议不变：

- `/api/share/file`
- `/api/share/text`
- `/api/share/fetch`
- `/api/share/detail`
- `/api/share/download`

这样可以先替换前端，不必立即重写业务层。

## 13. 维护时的重点注意事项

- 上传上限并不只由后台配置决定，实际值取决于 `应用配置 + PHP + Web Server` 三者最小值
- 文件下载和文本取件都涉及次数消耗，相关逻辑在事务里，改动时不要破坏并发安全
- `deleted_at` 当前兼有“逻辑删除/失效时间”语义，二次开发时要注意不要误判
- `system_config` 中缓存了上传目录大小，不等于实时磁盘扫描结果
- 所有后台写操作都依赖 CSRF 校验，新增表单时要带 `_csrf`

## 14. 相关文件索引

### 核心入口

- [public/index.php](../public/index.php)
- [app/bootstrap.php](../app/bootstrap.php)
- [config/config.php](../config/config.php)

### 控制器

- [app/Controllers/HomeController.php](../app/Controllers/HomeController.php)
- [app/Controllers/ApiController.php](../app/Controllers/ApiController.php)
- [app/Controllers/AdminController.php](../app/Controllers/AdminController.php)

### 服务

- [app/Services/ShareService.php](../app/Services/ShareService.php)
- [app/Services/ConfigService.php](../app/Services/ConfigService.php)
- [app/Services/AuthService.php](../app/Services/AuthService.php)
- [app/Services/RateLimitService.php](../app/Services/RateLimitService.php)
- [app/Services/CleanupService.php](../app/Services/CleanupService.php)
- [app/Services/AccessLogService.php](../app/Services/AccessLogService.php)
- [app/Services/Database.php](../app/Services/Database.php)

### 辅助类

- [app/Helpers/Security.php](../app/Helpers/Security.php)
- [app/Helpers/Response.php](../app/Helpers/Response.php)
- [app/Helpers/View.php](../app/Helpers/View.php)

### 数据与脚本

- [database/schema.sql](../database/schema.sql)
- [scripts/install.php](../scripts/install.php)
- [scripts/cleanup.php](../scripts/cleanup.php)
