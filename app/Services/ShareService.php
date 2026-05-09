<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Security;
use RuntimeException;

/**
 * 分享核心服务。
 *
 * 统一处理文本/文件分享的创建、凭提取码取件、文件下载、过期判断、删除和统计。
 * 这里是项目里业务约束最集中的地方：提取码唯一性、有效期规则、按次数消费、
 * 文件落盘和事务性扣减都在这一层收口，控制器只做调用和响应转换。
 */
final class ShareService
{
    private const EXPIRE_STYLES = ['day', 'hour', 'minute', 'count', 'forever'];

    private ConfigService $configService;
    private AccessLogService $logService;

    public function __construct()
    {
        $this->configService = new ConfigService();
        $this->logService = new AccessLogService();
    }

    public function createTextShare(array $payload, string $ip, string $ua = ''): array
    {
        $text = trim((string) ($payload['text_content'] ?? ''));
        if ($text === '') {
            return ['ok' => false, 'message' => 'Text content is required.'];
        }

        $maxLength = (int) $this->configService->get('max_text_length', (string) app_config('limits.max_text_length'));
        if ($this->textLength($text) > $maxLength) {
            return ['ok' => false, 'message' => 'Text is too long. Max length: ' . $maxLength];
        }

        $codeResult = $this->resolveCode((string) ($payload['code'] ?? ''));
        if (!$codeResult['ok']) {
            return $codeResult;
        }

        $expire = $this->resolveExpire((string) ($payload['expire_style'] ?? ''), (string) ($payload['expire_value'] ?? ''));
        if (!$expire['ok']) {
            return $expire;
        }

        $title = trim((string) ($payload['title'] ?? ''));
        if ($title === '') {
            $title = $this->textSubstr($text, 0, 30) . ($this->textLength($text) > 30 ? '...' : '');
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare('INSERT INTO shares
            (share_type, code, title, text_content, expire_style, expire_value, expire_at, max_fetch_count, current_fetch_count, status, created_ip, created_at, updated_at)
            VALUES
            ("text", :code, :title, :text_content, :expire_style, :expire_value, :expire_at, :max_fetch_count, 0, 1, :created_ip, NOW(), NOW())');

        $stmt->execute([
            'code' => $codeResult['code'],
            'title' => $title,
            'text_content' => $text,
            'expire_style' => $expire['expire_style'],
            'expire_value' => $expire['expire_value'],
            'expire_at' => $expire['expire_at'],
            'max_fetch_count' => $expire['max_fetch_count'],
            'created_ip' => $ip,
        ]);

        $id = (int) $pdo->lastInsertId();
        $this->logService->log($id, 'upload', $ip, $ua, 'text share created');

        return [
            'ok' => true,
            'message' => 'Text shared successfully.',
            'data' => [
                'id' => $id,
                'code' => $codeResult['code'],
                'title' => $title,
                'expire_style' => $expire['expire_style'],
                'expire_value' => $expire['expire_value'],
                'expire_at' => $expire['expire_at'],
                'max_fetch_count' => $expire['max_fetch_count'],
                'expire_label' => $this->expireLabel([
                    'expire_style' => $expire['expire_style'],
                    'expire_at' => $expire['expire_at'],
                    'max_fetch_count' => $expire['max_fetch_count'],
                    'current_fetch_count' => 0,
                ]),
            ],
        ];
    }

    /**
     * 创建文件分享。
     *
     * 这里会同时校验 PHP 上传错误码、应用侧有效上传上限、提取码规则和有效期规则，
     * 然后先把文件写入上传目录，再写 shares 表记录，保证数据库里只保存已成功落盘的文件。
     */
    public function createFileShare(array $file, array $payload, string $ip, string $ua = ''): array
    {
        $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadError !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'message' => $this->uploadErrorMessage($uploadError)];
        }

        // 实际可上传大小取“后台配置”和 PHP 运行时限制中的更小值，防止后台显示值大于真实可用值。
        $effectiveUploadMb = $this->configService->effectiveUploadLimitMb();
        if ($effectiveUploadMb > 0 && ((int) ($file['size'] ?? 0)) > ($effectiveUploadMb * 1024 * 1024)) {
            return ['ok' => false, 'message' => 'File exceeds current upload limit (' . $effectiveUploadMb . 'MB).'];
        }

        $codeResult = $this->resolveCode((string) ($payload['code'] ?? ''));
        if (!$codeResult['ok']) {
            return $codeResult;
        }

        $expire = $this->resolveExpire((string) ($payload['expire_style'] ?? ''), (string) ($payload['expire_value'] ?? ''));
        if (!$expire['ok']) {
            return $expire;
        }

        $originalName = (string) ($file['name'] ?? 'file.bin');
        $safeName = Security::safeFileName($originalName);
        $storedName = date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '_' . $safeName;

        $uploadDir = (string) app_config('storage.upload_dir');
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            throw new RuntimeException('Cannot create upload directory.');
        }

        $absolutePath = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $storedName;
        if (!move_uploaded_file((string) $file['tmp_name'], $absolutePath)) {
            return ['ok' => false, 'message' => 'Failed to store uploaded file.'];
        }

        $mimeType = (string) ($file['type'] ?? 'application/octet-stream');
        $title = trim((string) ($payload['title'] ?? ''));
        if ($title === '') {
            $title = $originalName;
        }

        $relativePath = 'storage/uploads/' . $storedName;

        $pdo = Database::pdo();
        $stmt = $pdo->prepare('INSERT INTO shares
            (share_type, code, title, file_name, file_path, file_size, mime_type, expire_style, expire_value, expire_at, max_fetch_count, current_fetch_count, status, created_ip, created_at, updated_at)
            VALUES
            ("file", :code, :title, :file_name, :file_path, :file_size, :mime_type, :expire_style, :expire_value, :expire_at, :max_fetch_count, 0, 1, :created_ip, NOW(), NOW())');

        $stmt->execute([
            'code' => $codeResult['code'],
            'title' => $title,
            'file_name' => $originalName,
            'file_path' => $relativePath,
            'file_size' => (int) $file['size'],
            'mime_type' => $mimeType,
            'expire_style' => $expire['expire_style'],
            'expire_value' => $expire['expire_value'],
            'expire_at' => $expire['expire_at'],
            'max_fetch_count' => $expire['max_fetch_count'],
            'created_ip' => $ip,
        ]);

        $id = (int) $pdo->lastInsertId();
        $this->bumpDiskUsageCache((int) $file['size'], $pdo);
        $this->logService->log($id, 'upload', $ip, $ua, 'file share created');

        return [
            'ok' => true,
            'message' => 'File shared successfully.',
            'data' => [
                'id' => $id,
                'code' => $codeResult['code'],
                'title' => $title,
                'file_name' => $originalName,
                'file_size' => (int) $file['size'],
                'expire_style' => $expire['expire_style'],
                'expire_value' => $expire['expire_value'],
                'expire_at' => $expire['expire_at'],
                'max_fetch_count' => $expire['max_fetch_count'],
                'expire_label' => $this->expireLabel([
                    'expire_style' => $expire['expire_style'],
                    'expire_at' => $expire['expire_at'],
                    'max_fetch_count' => $expire['max_fetch_count'],
                    'current_fetch_count' => 0,
                ]),
            ],
        ];
    }

    /**
     * 凭提取码取件。
     *
     * 文本分享会在读取成功时即时消费次数，因为正文内容直接在接口里返回；
     * 文件分享则只返回下载地址，真正的次数消费延后到 downloadFile() 中完成，
     * 避免用户只是查询到文件存在就提前扣掉一次下载次数。
     */
    public function fetchByCode(string $code, string $ip, string $ua = ''): array
    {
        $share = $this->getByCode($code);
        if (!$share) {
            $this->logService->log(null, 'fetch_fail', $ip, $ua, 'code not found');
            return ['ok' => false, 'message' => 'Code not found.'];
        }

        if ($share['share_type'] === 'text') {
            $consumed = $this->consumeTextFetch((int) $share['id']);
            if (!$consumed['ok']) {
                $this->logService->log((int) $share['id'], 'fetch_fail', $ip, $ua, $consumed['message']);
                return $consumed;
            }

            $share = $consumed['share'];
        } else {
            $availability = $this->assertShareAvailable($share, false);
            if (!$availability['ok']) {
                $this->logService->log((int) $share['id'], 'fetch_fail', $ip, $ua, $availability['message']);
                return $availability;
            }
        }

        $payload = [
            'id' => (int) $share['id'],
            'share_type' => $share['share_type'],
            'code' => $share['code'],
            'title' => $share['title'],
            'file_name' => $share['file_name'],
            'file_size' => (int) ($share['file_size'] ?? 0),
            'created_at' => $share['created_at'],
            'expire_style' => $share['expire_style'],
            'expire_value' => $share['expire_value'],
            'expire_at' => $share['expire_at'],
            'max_fetch_count' => $share['max_fetch_count'],
            'current_fetch_count' => (int) $share['current_fetch_count'],
            'remaining_fetch_count' => $share['max_fetch_count'] !== null
                ? max(0, (int) $share['max_fetch_count'] - (int) $share['current_fetch_count'])
                : null,
            'expire_label' => $this->expireLabel($share),
        ];

        if ($share['share_type'] === 'text') {
            $payload['text_content'] = $share['text_content'];
        } else {
            $payload['download_url'] = '/api/share/download?id=' . (int) $share['id'] . '&code=' . urlencode((string) $share['code']);
        }

        $this->logService->log((int) $share['id'], 'fetch_success', $ip, $ua, 'code fetch success');

        return [
            'ok' => true,
            'message' => 'Fetch success.',
            'data' => $payload,
        ];
    }

    public function detailByCode(string $code): array
    {
        $share = $this->getByCode($code);
        if (!$share) {
            return ['ok' => false, 'message' => 'Code not found.'];
        }

        $availability = $this->assertShareAvailable($share, true);
        if (!$availability['ok']) {
            return $availability;
        }

        return [
            'ok' => true,
            'message' => 'Detail success.',
            'data' => [
                'id' => (int) $share['id'],
                'share_type' => $share['share_type'],
                'code' => $share['code'],
                'title' => $share['title'],
                'file_name' => $share['file_name'],
                'file_size' => (int) ($share['file_size'] ?? 0),
                'created_at' => $share['created_at'],
                'expire_style' => $share['expire_style'],
                'expire_value' => $share['expire_value'],
                'expire_at' => $share['expire_at'],
                'max_fetch_count' => $share['max_fetch_count'],
                'current_fetch_count' => (int) $share['current_fetch_count'],
                'remaining_fetch_count' => $share['max_fetch_count'] !== null
                    ? max(0, (int) $share['max_fetch_count'] - (int) $share['current_fetch_count'])
                    : null,
                'expire_label' => $this->expireLabel($share),
            ],
        ];
    }

    /**
     * 为文件下载做最终校验并返回物理文件信息。
     *
     * 文件分享的按次数消费在这里完成，而不是在 fetchByCode() 中完成，
     * 因为真正发生“取走文件”动作的是下载请求本身。
     */
    public function downloadFile(int $id, string $code, string $ip, string $ua = ''): array
    {
        $consumed = $this->consumeFileDownload($id, $code);
        if (!$consumed['ok']) {
            $this->logService->log($id > 0 ? $id : null, 'fetch_fail', $ip, $ua, 'download denied: ' . $consumed['message']);
            return $consumed;
        }

        $share = $consumed['share'];
        $absolutePath = dirname(__DIR__, 2) . '/' . ltrim((string) $share['file_path'], '/');
        if (!is_file($absolutePath)) {
            return ['ok' => false, 'message' => 'Physical file is missing.'];
        }

        $this->logService->log((int) $share['id'], 'fetch_success', $ip, $ua, 'file download success');

        return [
            'ok' => true,
            'message' => 'Download ready.',
            'data' => [
                'absolute_path' => $absolutePath,
                'file_name' => (string) $share['file_name'],
                'mime_type' => (string) ($share['mime_type'] ?: 'application/octet-stream'),
                'file_size' => (int) $share['file_size'],
            ],
        ];
    }

    public function listShares(int $page = 1, int $pageSize = 20, ?string $type = null, ?string $keyword = null): array
    {
        $offset = max(0, ($page - 1) * $pageSize);
        $where = ['1=1'];
        $params = [];

        if ($type === 'file' || $type === 'text') {
            $where[] = 'share_type = :type';
            $params['type'] = $type;
        }

        $keyword = $keyword !== null ? trim($keyword) : null;
        if ($keyword !== null && $keyword !== '') {
            if ($this->looksLikeCodeKeyword($keyword)) {
                $where[] = '(code = :kw_exact OR code LIKE :kw_prefix OR title LIKE :kw_text OR file_name LIKE :kw_text)';
                $params['kw_exact'] = $keyword;
                $params['kw_prefix'] = $keyword . '%';
                $params['kw_text'] = '%' . $keyword . '%';
            } else {
                $where[] = '(title LIKE :kw_text OR file_name LIKE :kw_text)';
                $params['kw_text'] = '%' . $keyword . '%';
            }
        }

        $sqlWhere = implode(' AND ', $where);

        $pdo = Database::pdo();
        $countStmt = $pdo->prepare('SELECT COUNT(*) AS c FROM shares WHERE ' . $sqlWhere);
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetch()['c'] ?? 0);

        $listStmt = $pdo->prepare(
            'SELECT
                id,
                share_type,
                code,
                title,
                file_name,
                file_size,
                expire_style,
                expire_at,
                max_fetch_count,
                current_fetch_count,
                status,
                created_at
             FROM shares
             WHERE ' . $sqlWhere . ' ORDER BY id DESC LIMIT :offset, :size'
        );
        foreach ($params as $k => $v) {
            $listStmt->bindValue(':' . $k, $v);
        }
        $listStmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $listStmt->bindValue(':size', $pageSize, \PDO::PARAM_INT);
        $listStmt->execute();

        return [
            'list' => $listStmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
        ];
    }

    public function deleteShare(int $id, string $ip, string $ua = ''): bool
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT * FROM shares WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $share = $stmt->fetch();
        if (!$share) {
            return false;
        }

        if ($share['share_type'] === 'file' && !empty($share['file_path'])) {
            $absolute = dirname(__DIR__, 2) . '/' . ltrim((string) $share['file_path'], '/');
            if (is_file($absolute)) {
                @unlink($absolute);
            }
        }

        $delete = $pdo->prepare('DELETE FROM shares WHERE id = :id');
        $delete->execute(['id' => $id]);
        if ($delete->rowCount() < 1) {
            return false;
        }

        if ($share['share_type'] === 'file') {
            $this->bumpDiskUsageCache(-((int) ($share['file_size'] ?? 0)), $pdo);
        }

        $this->logService->log($id, 'delete', $ip, $ua, 'share deleted by admin');

        return true;
    }

    public function summary(): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->query(
            'SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN share_type = "file" THEN 1 ELSE 0 END) AS files,
                SUM(CASE WHEN share_type = "text" THEN 1 ELSE 0 END) AS texts,
                SUM(CASE WHEN share_type = "file" THEN IFNULL(file_size, 0) ELSE 0 END) AS file_bytes_total
             FROM shares'
        );
        $row = $stmt->fetch() ?: [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'active' => (int) ($row['active'] ?? 0),
            'files' => (int) ($row['files'] ?? 0),
            'texts' => (int) ($row['texts'] ?? 0),
            'file_bytes_total' => (int) ($row['file_bytes_total'] ?? 0),
            'upload_disk_usage_bytes' => (int) $this->configService->get('upload_disk_usage_bytes', '0'),
            'upload_disk_usage_updated_at' => $this->configService->get('upload_disk_usage_updated_at', ''),
        ];
    }

    public function codeExists(string $code): bool
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT id FROM shares WHERE code = :code LIMIT 1');
        $stmt->execute(['code' => $code]);
        return (bool) $stmt->fetch();
    }

    private function resolveCode(string $candidate): array
    {
        $candidate = Security::sanitizeCode($candidate);
        $allowCustomCode = $this->configService->get('allow_custom_code', '1') === '1';

        if ($candidate !== '' && !$allowCustomCode) {
            return ['ok' => false, 'message' => 'Custom code is disabled by administrator.'];
        }

        if ($candidate !== '') {
            if (!Security::validateCode(
                $candidate,
                (int) app_config('security.code_min_len', 4),
                (int) app_config('security.code_max_len', 32)
            )) {
                return ['ok' => false, 'message' => 'Invalid code format.'];
            }

            if ($this->codeExists($candidate)) {
                return ['ok' => false, 'message' => 'Code already exists.'];
            }

            return ['ok' => true, 'code' => $candidate];
        }

        $length = max(4, (int) $this->configService->get('code_length', (string) app_config('security.default_code_len', 6)));
        for ($i = 0; $i < 10; $i++) {
            $generated = $this->generateCode($length);
            if (!$this->codeExists($generated)) {
                return ['ok' => true, 'code' => $generated];
            }
        }

        return ['ok' => false, 'message' => 'Unable to generate unique code.'];
    }

    /**
     * 把前端传入的有效期参数转换成可落库结构。
     *
     * `count` 只记录最大可取次数，不生成 expire_at；
     * 时间型有效期则统一换算为绝对过期时间；
     * `forever` 还要额外受 max_save_seconds 配置约束。
     */
    private function resolveExpire(string $style, string $value): array
    {
        if ($style === '') {
            $style = $this->configService->effectiveDefaultExpireStyle();
            $value = $this->configService->get('default_expire_value', '1');
        }

        if (!in_array($style, self::EXPIRE_STYLES, true)) {
            return ['ok' => false, 'message' => 'Invalid expire style.'];
        }

        if (!in_array($style, $this->configService->allowedExpireStyles(), true)) {
            return ['ok' => false, 'message' => 'This expire style is disabled by administrator.'];
        }

        $intValue = (int) $value;
        $maxSaveSeconds = $this->configService->maxSaveSeconds();

        if ($style === 'forever') {
            if ($maxSaveSeconds > 0) {
                return ['ok' => false, 'message' => 'Forever shares are disabled by administrator.'];
            }

            return [
                'ok' => true,
                'expire_style' => $style,
                'expire_value' => null,
                'expire_at' => null,
                'max_fetch_count' => null,
            ];
        }

        if ($intValue <= 0) {
            return ['ok' => false, 'message' => 'Expire value must be greater than 0.'];
        }

        if ($style === 'count') {
            return [
                'ok' => true,
                'expire_style' => $style,
                'expire_value' => $intValue,
                'expire_at' => null,
                'max_fetch_count' => $intValue,
            ];
        }

        $seconds = match ($style) {
            'day' => $intValue * 86400,
            'hour' => $intValue * 3600,
            'minute' => $intValue * 60,
            default => 0,
        };

        if ($maxSaveSeconds > 0 && $seconds > $maxSaveSeconds) {
            return ['ok' => false, 'message' => 'Expire duration exceeds administrator limit.'];
        }

        return [
            'ok' => true,
            'expire_style' => $style,
            'expire_value' => $intValue,
            'expire_at' => date('Y-m-d H:i:s', time() + $seconds),
            'max_fetch_count' => null,
        ];
    }

    private function getByCode(string $code): ?array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT * FROM shares WHERE code = :code LIMIT 1');
        $stmt->execute(['code' => $code]);
        $share = $stmt->fetch();

        return $share ?: null;
    }

    private function assertShareAvailable(array $share, bool $markExpired): array
    {
        if ((int) $share['status'] !== 1) {
            return ['ok' => false, 'message' => 'Share is inactive.'];
        }

        if ($share['expire_style'] === 'count' && $share['max_fetch_count'] !== null && (int) $share['current_fetch_count'] >= (int) $share['max_fetch_count']) {
            if ($markExpired) {
                $this->setExpired((int) $share['id']);
            }

            return ['ok' => false, 'message' => 'Fetch count exhausted.'];
        }

        if ($share['expire_style'] !== 'count' && $share['expire_style'] !== 'forever' && !empty($share['expire_at']) && strtotime((string) $share['expire_at']) <= time()) {
            if ($markExpired) {
                $this->setExpired((int) $share['id']);
            }

            return ['ok' => false, 'message' => 'Share has expired.'];
        }

        return ['ok' => true, 'message' => ''];
    }

    /**
     * 在事务内消费文本分享的一次取件。
     *
     * 这里必须先 FOR UPDATE 锁定记录，再校验可用性并更新 current_fetch_count，
     * 否则高并发下可能出现同一条文本分享被重复超额读取的问题。
     */
    private function consumeTextFetch(int $shareId): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare('SELECT * FROM shares WHERE id = :id LIMIT 1 FOR UPDATE');
            $stmt->execute(['id' => $shareId]);
            $share = $stmt->fetch();
            if (!$share || $share['share_type'] !== 'text') {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'Code not found.'];
            }

            $availability = $this->assertShareAvailable($share, false);
            if (!$availability['ok']) {
                if ($availability['message'] !== 'Share is inactive.') {
                    $this->expireShareInTransaction($pdo, $share);
                }
                $pdo->commit();
                return ['ok' => false, 'message' => $availability['message']];
            }

            $updated = $this->consumeFetchInTransaction($pdo, $share);
            $pdo->commit();

            return ['ok' => true, 'share' => $updated];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    /**
     * 在事务内消费文件下载次数。
     *
     * 文件下载和文本读取一样，都需要在数据库锁内完成“校验是否还能取 + 次数扣减”，
     * 避免多个并发下载请求同时越过次数上限。
     */
    private function consumeFileDownload(int $id, string $code): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare('SELECT * FROM shares WHERE id = :id AND code = :code LIMIT 1 FOR UPDATE');
            $stmt->execute(['id' => $id, 'code' => $code]);
            $share = $stmt->fetch();
            if (!$share || $share['share_type'] !== 'file') {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'File not found.'];
            }

            $availability = $this->assertShareAvailable($share, false);
            if (!$availability['ok']) {
                if ($availability['message'] !== 'Share is inactive.') {
                    $this->expireShareInTransaction($pdo, $share);
                }
                $pdo->commit();
                return ['ok' => false, 'message' => $availability['message']];
            }

            $updated = $this->consumeFetchInTransaction($pdo, $share);
            $pdo->commit();

            return ['ok' => true, 'share' => $updated];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    /**
     * 复用的事务内消费逻辑。
     *
     * 一次成功取件后统一递增 current_fetch_count；
     * 如果按次数分享恰好被消费到上限，则在同一个事务里直接把状态改成失效。
     */
    private function consumeFetchInTransaction(\PDO $pdo, array $share): array
    {
        $newCount = (int) $share['current_fetch_count'] + 1;
        $deactivate = $share['expire_style'] === 'count'
            && $share['max_fetch_count'] !== null
            && $newCount >= (int) $share['max_fetch_count'];

        $stmt = $pdo->prepare('UPDATE shares
            SET current_fetch_count = :current_fetch_count,
                status = :status,
                deleted_at = :deleted_at,
                updated_at = NOW()
            WHERE id = :id');
        $stmt->execute([
            'current_fetch_count' => $newCount,
            'status' => $deactivate ? 0 : 1,
            'deleted_at' => $deactivate ? date('Y-m-d H:i:s') : null,
            'id' => (int) $share['id'],
        ]);

        $share['current_fetch_count'] = $newCount;
        if ($deactivate) {
            $share['status'] = 0;
            $share['deleted_at'] = date('Y-m-d H:i:s');
        }
        $share['updated_at'] = date('Y-m-d H:i:s');

        return $share;
    }

    private function expireShareInTransaction(\PDO $pdo, array $share): void
    {
        if ((int) $share['status'] !== 1) {
            return;
        }

        $stmt = $pdo->prepare('UPDATE shares SET status = 0, updated_at = NOW(), deleted_at = NOW() WHERE id = :id AND status = 1');
        $stmt->execute(['id' => (int) $share['id']]);
    }

    private function setExpired(int $shareId): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('UPDATE shares SET status = 0, updated_at = NOW(), deleted_at = NOW() WHERE id = :id AND status = 1');
        $stmt->execute(['id' => $shareId]);
    }

    private function looksLikeCodeKeyword(string $keyword): bool
    {
        $length = strlen($keyword);

        return $length >= 4
            && $length <= 32
            && (bool) preg_match('/^[A-Za-z0-9_-]+$/', $keyword);
    }

    private function bumpDiskUsageCache(int $deltaBytes, ?\PDO $pdo = null): int
    {
        $current = (int) $this->configService->get('upload_disk_usage_bytes', '0');
        $updated = max(0, $current + $deltaBytes);
        $db = $pdo ?? Database::pdo();
        $stmt = $db->prepare('INSERT INTO system_config (config_key, config_value, config_group, updated_at)
            VALUES (:key, :value, :group_name, NOW())
            ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), config_group = VALUES(config_group), updated_at = NOW()');

        $stmt->execute([
            'key' => 'upload_disk_usage_bytes',
            'value' => (string) $updated,
            'group_name' => 'storage',
        ]);
        $stmt->execute([
            'key' => 'upload_disk_usage_updated_at',
            'value' => date('Y-m-d H:i:s'),
            'group_name' => 'storage',
        ]);

        return $updated;
    }

    private function generateCode(int $length): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $max = strlen($chars) - 1;
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[random_int(0, $max)];
        }

        return $code;
    }

    private function textLength(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value);
        }

        return strlen($value);
    }

    private function textSubstr(string $value, int $start, int $length): string
    {
        if (function_exists('mb_substr')) {
            return (string) mb_substr($value, $start, $length);
        }

        return substr($value, $start, $length);
    }

    private function expireLabel(array $share): string
    {
        return match ((string) ($share['expire_style'] ?? '')) {
            'forever' => '永久有效',
            'count' => '剩余 ' . max(0, (int) ($share['max_fetch_count'] ?? 0) - (int) ($share['current_fetch_count'] ?? 0)) . ' 次',
            'day' => '到期时间 ' . (string) ($share['expire_at'] ?: '-'),
            'hour' => '到期时间 ' . (string) ($share['expire_at'] ?: '-'),
            'minute' => '到期时间 ' . (string) ($share['expire_at'] ?: '-'),
            default => '-',
        };
    }

    private function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Uploaded file exceeds server upload limit.',
            UPLOAD_ERR_PARTIAL => 'File upload was interrupted. Please retry.',
            UPLOAD_ERR_NO_FILE => 'Please select a file before uploading.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server upload temp directory is missing.',
            UPLOAD_ERR_CANT_WRITE => 'Server failed to write the uploaded file.',
            UPLOAD_ERR_EXTENSION => 'File upload was blocked by a PHP extension.',
            default => 'File upload failed.',
        };
    }
}
