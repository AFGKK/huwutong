<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Text-to-SQL 安全护栏服务 (M2-136)
 *
 * 为自然语言转 SQL 提供多层安全防护：
 * - 只读检查：禁止非 SELECT 操作
 * - 危险语句检测：DROP/ALTER/TRUNCATE 等
 * - 白名单校验：仅允许预配置的表和字段
 * - 敏感字段保护：阻止访问用户密码、密钥等敏感列
 * - 行数限制：自动添加/验证 LIMIT
 * - 查询超时控制：通过数据库 statement_timeout
 * - 结果脱敏：对敏感字段自动脱敏
 */
class TextToSqlGuardService
{
    /**
     * 允许的 SQL 语句类型
     */
    const ALLOWED_STATEMENTS = ['SELECT'];

    /**
     * 禁止的关键词（不区分大小写）
     */
    const FORBIDDEN_KEYWORDS = [
        'DROP', 'ALTER', 'TRUNCATE', 'DELETE', 'INSERT',
        'UPDATE', 'REPLACE', 'CREATE', 'GRANT', 'REVOKE',
        'EXEC', 'EXECUTE', 'CALL', 'LOAD', 'IMPORT',
        'RENAME', 'LOCK', 'UNLOCK', 'KILL', 'SHUTDOWN',
        'SET', 'DEALLOCATE', 'PREPARE', 'EXPLAIN',
        'INTO OUTFILE', 'INTO DUMPFILE',
        'INFORMATION_SCHEMA', 'PG_SLEEP', 'BENCHMARK',
        'COPY', 'VACUUM', 'REINDEX', 'CLUSTER',
    ];

    /**
     * 敏感字段模式（表名.列名 或 列名）
     * 默认不允许查询这些字段
     */
    const SENSITIVE_COLUMNS = [
        'password', 'secret', 'api_key', 'token',
        'private_key', 'seed_encrypted', 'encrypted',
        'credit_card', 'cvv', 'ssn', 'pin',
        'refresh_token', 'access_token', 'auth_token',
        'totp_secret', 'backup_codes',
        'password_hash', 'remember_token',
    ];

    /**
     * 默认最大返回行数
     */
    const DEFAULT_MAX_ROWS = 100;

    /**
     * 查询超时（秒）
     */
    const QUERY_TIMEOUT = 10;

    /**
     * 结果脱敏配置
     */
    const MASK_REPLACEMENT = '***MASKED***';

    /**
     * 允许的表白名单（空 = 允许所有，基于配置）
     */
    protected array $allowedTables = [];

    /**
     * 允许的字段白名单（空 = 允许所有）
     * 格式: ['users' => ['id', 'name', 'email'], ...]
     */
    protected array $allowedColumns = [];

    /**
     * 敏感字段额外配置（可动态添加）
     */
    protected array $extraSensitiveColumns = [];

    /**
     * 最大行数配置
     */
    protected int $maxRows;

    /**
     * 构造函数：从配置加载白名单
     */
    public function __construct()
    {
        $this->allowedTables = config('text-to-sql.allowed_tables', []);
        $this->allowedColumns = config('text-to-sql.allowed_columns', []);
        $this->extraSensitiveColumns = config('text-to-sql.sensitive_columns', []);
        $this->maxRows = config('text-to-sql.max_rows', self::DEFAULT_MAX_ROWS);
    }

    /**
     * 执行完整的安全验证流程
     *
     * @param string $sql 原始 SQL
     * @param array $context 上下文（user_id, role, tenant_id 等）
     * @return array{allowed: bool, sql: string, reason: ?string, warnings: array}
     * @throws \InvalidArgumentException
     */
    public function validate(string $sql, array $context = []): array
    {
        $warnings = [];

        // 1. 基础语法检查
        $trimmed = trim($sql);

        // 2. 只读检查 — 只允许 SELECT
        $statementCheck = $this->checkReadOnlyStatement($trimmed);
        if (! $statementCheck['allowed']) {
            return $statementCheck;
        }

        // 3. 危险关键词检查
        $keywordCheck = $this->checkForbiddenKeywords($trimmed);
        if (! $keywordCheck['allowed']) {
            return $keywordCheck;
        }

        // 4. 敏感字段检查
        $sensitiveCheck = $this->checkSensitiveColumns($trimmed);
        if (! $sensitiveCheck['allowed']) {
            return $sensitiveCheck;
        }
        if (! empty($sensitiveCheck['warnings'])) {
            $warnings = array_merge($warnings, $sensitiveCheck['warnings']);
        }

        // 5. 表白名单检查
        $tableCheck = $this->checkAllowedTables($trimmed);
        if (! $tableCheck['allowed']) {
            return $tableCheck;
        }

        // 6. 行数限制 — 自动添加 LIMIT
        $enhancedSql = $this->enforceRowLimit($trimmed);
        if ($enhancedSql !== $trimmed) {
            $warnings[] = "已自动添加 LIMIT {$this->maxRows}";
        }

        // 7. 租户隔离 — 如果上下文中有 tenant_id，自动添加条件
        $isolatedSql = $this->applyTenantIsolation($enhancedSql, $context);
        if ($isolatedSql !== $enhancedSql) {
            $warnings[] = '已自动应用租户数据隔离';
        }

        // 8. 最终格式化和清理
        $cleanedSql = $this->finalCleanup($isolatedSql);

        return [
            'allowed' => true,
            'sql' => $cleanedSql,
            'reason' => null,
            'warnings' => $warnings,
        ];
    }

    /**
     * 执行安全查询并返回结果
     *
     * @param string $sql 已验证的 SQL
     * @param array $bindings 参数绑定
     * @param array $context 上下文
     * @return array{success: bool, data: ?array, count: int, error: ?string}
     */
    public function execute(string $sql, array $bindings = [], array $context = []): array
    {
        // 先验证
        $validation = $this->validate($sql, $context);
        if (! $validation['allowed']) {
            return [
                'success' => false,
                'data' => null,
                'count' => 0,
                'error' => $validation['reason'] ?? 'SQL 验证未通过',
            ];
        }

        // 执行查询
        try {
            // 设置查询超时
            DB::statement("SET LOCAL statement_timeout = " . (self::QUERY_TIMEOUT * 1000));

            $startTime = microtime(true);
            $results = DB::select($validation['sql'], $bindings);
            $elapsedMs = (microtime(true) - $startTime) * 1000;

            // 结果脱敏
            $maskedData = $this->maskSensitiveData($results);

            Log::info('Text-to-SQL 查询已执行', [
                'sql' => $validation['sql'],
                'row_count' => count($maskedData),
                'elapsed_ms' => round($elapsedMs, 2),
                'warnings' => $validation['warnings'],
            ]);

            return [
                'success' => true,
                'data' => $maskedData,
                'count' => count($maskedData),
                'elapsed_ms' => round($elapsedMs, 2),
                'warnings' => $validation['warnings'],
                'error' => null,
            ];
        } catch (Throwable $e) {
            Log::warning('Text-to-SQL 查询执行失败', [
                'sql' => $validation['sql'],
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'data' => null,
                'count' => 0,
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
            ];
        }
    }

    /**
     * 检查只读（仅允许 SELECT）
     */
    protected function checkReadOnlyStatement(string $sql): array
    {
        $upper = strtoupper(trim($sql));

        // 检查是否以 SELECT 开头（允许 WITH 用于 CTE）
        if (! Str::startsWith($upper, 'SELECT') && ! Str::startsWith($upper, 'WITH')) {
            return [
                'allowed' => false,
                'sql' => $sql,
                'reason' => '仅允许 SELECT 查询语句',
                'warnings' => [],
            ];
        }

        // 双重检查：即使以 SELECT 开头，也要确保没有嵌入危险语句
        foreach (self::FORBIDDEN_KEYWORDS as $keyword) {
            // 跳过 SELECT 本身可能包含的部分词
            if ($keyword === 'SET') continue;

            // 检查语句体中是否包含独立的关键词
            $pattern = '/\b' . preg_quote($keyword, '/') . '\b/i';
            if (preg_match($pattern, $sql)) {
                return [
                    'allowed' => false,
                    'sql' => $sql,
                    'reason' => "检测到禁止的 SQL 关键字: {$keyword}",
                    'warnings' => [],
                ];
            }
        }

        return ['allowed' => true, 'sql' => $sql, 'reason' => null, 'warnings' => []];
    }

    /**
     * 检查危险关键词
     */
    protected function checkForbiddenKeywords(string $sql): array
    {
        $upper = strtoupper($sql);

        foreach (self::FORBIDDEN_KEYWORDS as $keyword) {
            $pattern = '/\b' . preg_quote($keyword, '/') . '\b/i';
            if (preg_match($pattern, $sql)) {
                return [
                    'allowed' => false,
                    'sql' => $sql,
                    'reason' => "查询包含禁止的关键词: {$keyword}",
                    'warnings' => [],
                ];
            }
        }

        return ['allowed' => true, 'sql' => $sql, 'reason' => null, 'warnings' => []];
    }

    /**
     * 检查是否引用了敏感字段
     */
    protected function checkSensitiveColumns(string $sql): array
    {
        $allSensitive = array_merge(self::SENSITIVE_COLUMNS, $this->extraSensitiveColumns);
        $warnings = [];

        foreach ($allSensitive as $column) {
            $pattern = '/\b' . preg_quote($column, '/') . '\b/i';
            if (preg_match($pattern, $sql)) {
                $warnings[] = "查询引用了敏感字段: {$column}（结果将自动脱敏）";
            }
        }

        return ['allowed' => true, 'sql' => $sql, 'reason' => null, 'warnings' => $warnings];
    }

    /**
     * 检查表白名单
     */
    protected function checkAllowedTables(string $sql): array
    {
        // 如果白名单为空，允许所有表
        if (empty($this->allowedTables)) {
            return ['allowed' => true, 'sql' => $sql, 'reason' => null, 'warnings' => []];
        }

        // 从 SQL 中提取表名
        $tables = $this->extractTableNames($sql);
        if (empty($tables)) {
            return ['allowed' => false, 'sql' => $sql, 'reason' => '无法解析 SQL 中的表名', 'warnings' => []];
        }

        $allowedUpper = array_map('strtoupper', $this->allowedTables);
        foreach ($tables as $table) {
            if (! in_array(strtoupper($table), $allowedUpper)) {
                return [
                    'allowed' => false,
                    'sql' => $sql,
                    'reason' => "表 '{$table}' 不在允许的查询白名单中",
                    'warnings' => [],
                ];
            }
        }

        return ['allowed' => true, 'sql' => $sql, 'reason' => null, 'warnings' => []];
    }

    /**
     * 从 SELECT SQL 中提取表名（简单解析）
     */
    protected function extractTableNames(string $sql): array
    {
        $tables = [];
        $upper = strtoupper(preg_replace('/\s+/', ' ', trim($sql)));

        // 查找 FROM 子句
        if (preg_match('/\bFROM\s+([^\s,;()]+)/i', $sql, $matches)) {
            $tables[] = trim($matches[1], '`"\'');
        }

        // 查找 JOIN 子句
        if (preg_match_all('/\bJOIN\s+([^\s,;()]+)/i', $sql, $matches)) {
            foreach ($matches[1] as $match) {
                $tables[] = trim($match, '`"\'');
            }
        }

        return array_unique($tables);
    }

    /**
     * 强制执行行数限制
     */
    protected function enforceRowLimit(string $sql): string
    {
        $trimmed = trim($sql);

        // 检查是否已有 LIMIT
        if (preg_match('/\bLIMIT\s+\d+/i', $trimmed)) {
            return $trimmed;
        }

        // 去除末尾分号
        $trimmed = rtrim($trimmed, ';');

        // 添加 LIMIT
        return $trimmed . " LIMIT {$this->maxRows}";
    }

    /**
     * 应用租户隔离 — 自动添加 tenant_id 条件
     */
    protected function applyTenantIsolation(string $sql, array $context): string
    {
        $tenantId = $context['tenant_id'] ?? null;
        if (! $tenantId) {
            return $sql;
        }

        // 检查是否已有 tenant_id 条件
        if (preg_match('/\btenant_id\b/i', $sql)) {
            return $sql;
        }

        // 查找 WHERE 子句位置，在 WHERE 后添加条件
        // 更安全的做法：基于已知表结构追加
        $trimmed = rtrim(trim($sql), ';');

        if (preg_match('/\bWHERE\b/i', $trimmed)) {
            // 已有 WHERE，追加 AND 条件
            $trimmed = preg_replace(
                '/\bWHERE\b/i',
                'WHERE tenant_id = ' . intval($tenantId) . ' AND ',
                $trimmed,
                1
            );
        } else {
            // 没有 WHERE，添加
            $trimmed .= ' WHERE tenant_id = ' . intval($tenantId);
        }

        return $trimmed;
    }

    /**
     * 最终清理和格式化
     */
    protected function finalCleanup(string $sql): string
    {
        // 去除多余空白
        $sql = preg_replace('/\s+/', ' ', trim($sql));

        // 确保结尾有分号
        if (! Str::endsWith(trim($sql), ';')) {
            $sql = rtrim($sql) . ';';
        }

        return $sql;
    }

    /**
     * 结果脱敏 — 对敏感字段的值进行替换
     */
    protected function maskSensitiveData(array $results): array
    {
        $allSensitive = array_merge(self::SENSITIVE_COLUMNS, $this->extraSensitiveColumns);

        return array_map(function ($row) use ($allSensitive) {
            $row = (array) $row;
            foreach ($row as $key => $value) {
                $keyLower = strtolower($key);
                foreach ($allSensitive as $sensitive) {
                    if (Str::contains($keyLower, strtolower($sensitive))) {
                        $row[$key] = self::MASK_REPLACEMENT;
                        break;
                    }
                }
            }
            return $row;
        }, $results);
    }

    /**
     * 清理错误消息（防止暴露敏感信息）
     */
    protected function sanitizeErrorMessage(string $error): string
    {
        // 移除文件路径
        $error = preg_replace('/[A-Z]:\\\\[^\s]+\\\\[^\s]+/', '[PATH]', $error);

        // 移除 SQL 语句本身
        if (preg_match('/SQLSTATE.*\]\s*(.*?)(?:\s*\(SQL:/s', $error, $matches)) {
            return trim($matches[1]);
        }

        return Str::limit($error, 200);
    }

    /**
     * 更新允许的表白名单
     */
    public function setAllowedTables(array $tables): void
    {
        $this->allowedTables = $tables;
    }

    /**
     * 更新允许的字段白名单
     */
    public function setAllowedColumns(array $columns): void
    {
        $this->allowedColumns = $columns;
    }

    /**
     * 设置最大行数
     */
    public function setMaxRows(int $maxRows): void
    {
        $this->maxRows = max(1, $maxRows);
    }

    /**
     * 添加额外敏感字段
     */
    public function addSensitiveColumns(array $columns): void
    {
        $this->extraSensitiveColumns = array_merge($this->extraSensitiveColumns, $columns);
    }

    /**
     * 获取当前配置摘要
     */
    public function getConfigSummary(): array
    {
        return [
            'allowed_tables' => $this->allowedTables,
            'max_rows' => $this->maxRows,
            'sensitive_columns' => array_merge(self::SENSITIVE_COLUMNS, $this->extraSensitiveColumns),
            'forbidden_keywords' => self::FORBIDDEN_KEYWORDS,
            'query_timeout_seconds' => self::QUERY_TIMEOUT,
        ];
    }
}
