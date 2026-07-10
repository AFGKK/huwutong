<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * 跨数据库（MySQL / PostgreSQL / SQLite）SQL 片段辅助
 */
class DbSql
{
    public static function driver(): string
    {
        return DB::connection()->getDriverName();
    }

    /**
     * DATE_FORMAT / strftime / TO_CHAR 兼容表达式
     */
    public static function dateFormat(string $column, string $mysqlFormat): string
    {
        $driver = self::driver();

        if ($driver === 'sqlite') {
            return "strftime('".self::mysqlFormatToSqlite($mysqlFormat)."', {$column})";
        }

        if ($driver === 'pgsql') {
            return "TO_CHAR({$column}, '".self::mysqlFormatToPgsql($mysqlFormat)."')";
        }

        return "DATE_FORMAT({$column}, '{$mysqlFormat}')";
    }

    /**
     * 提取小时（0-23）
     */
    public static function hour(string $column): string
    {
        return match (self::driver()) {
            'pgsql' => "EXTRACT(HOUR FROM {$column})::integer",
            'sqlite' => "CAST(strftime('%H', {$column}) AS INTEGER)",
            default => "HOUR({$column})",
        };
    }

    /**
     * SUBSTRING_INDEX 兼容表达式
     */
    public static function substringIndex(string $column, string $delimiter, int $count): string
    {
        $driver = self::driver();
        $escapedDelim = str_replace("'", "''", $delimiter);

        if ($driver === 'pgsql') {
            if ($count > 0) {
                if ($count === 1) {
                    return "split_part({$column}, '{$escapedDelim}', 1)";
                }

                return "array_to_string((string_to_array({$column}, '{$escapedDelim}'))[1:{$count}], '{$escapedDelim}')";
            }

            $abs = abs($count);
            if ($abs === 1) {
                return "(string_to_array({$column}, '{$escapedDelim}'))[array_length(string_to_array({$column}, '{$escapedDelim}'), 1)]";
            }

            return "array_to_string((string_to_array({$column}, '{$escapedDelim}'))[GREATEST(1, array_length(string_to_array({$column}, '{$escapedDelim}'), 1) - {$abs} + 1):], '{$escapedDelim}')";
        }

        if ($driver === 'sqlite') {
            $jsonArray = "'[\"' || replace({$column}, '{$escapedDelim}', '\",\"') || '\"]'";

            if ($count > 0) {
                if ($count === 1) {
                    return "CASE WHEN instr({$column} || '{$escapedDelim}', '{$escapedDelim}') = 0 THEN {$column} ELSE substr({$column}, 1, instr({$column} || '{$escapedDelim}', '{$escapedDelim}') - 1) END";
                }

                return "(SELECT group_concat(value, '{$escapedDelim}') FROM (SELECT value FROM json_each({$jsonArray}) LIMIT {$count}))";
            }

            $abs = abs($count);
            if ($abs === 1) {
                return "(SELECT value FROM json_each({$jsonArray}) ORDER BY key DESC LIMIT 1)";
            }

            return "(SELECT group_concat(value, '{$escapedDelim}') FROM (SELECT value FROM json_each({$jsonArray}) ORDER BY key DESC LIMIT {$abs}))";
        }

        return "SUBSTRING_INDEX({$column}, '{$escapedDelim}', {$count})";
    }

    /**
     * DATEDIFF(end, start) 兼容表达式（返回天数差）
     */
    public static function dateDiff(string $end, string $start): string
    {
        $driver = self::driver();

        if ($driver === 'sqlite') {
            return "CAST(julianday({$end}) - julianday({$start}) AS INTEGER)";
        }

        if ($driver === 'pgsql') {
            return "({$end}::date - {$start}::date)";
        }

        return "DATEDIFF({$end}, {$start})";
    }

    /**
     * 当前时间戳表达式
     */
    public static function now(): string
    {
        return self::driver() === 'pgsql' ? 'CURRENT_TIMESTAMP' : 'NOW()';
    }

    /**
     * 布尔真值比较（兼容 PG boolean / MySQL tinyint）
     */
    public static function isTrue(string $column): string
    {
        return match (self::driver()) {
            'pgsql' => "({$column}) IS TRUE",
            default => "({$column}) = 1",
        };
    }

    /**
     * 布尔假值比较
     */
    public static function isFalse(string $column): string
    {
        return match (self::driver()) {
            'pgsql' => "({$column}) IS NOT TRUE",
            default => "({$column}) = 0",
        };
    }

    /**
     * 月初日期（YYYY-MM-01）
     */
    public static function monthStart(string $column): string
    {
        return self::dateFormat($column, '%Y-%m-01');
    }

    /**
     * 月末日期
     */
    public static function lastDayOfMonth(string $column): string
    {
        return match (self::driver()) {
            'pgsql' => "(date_trunc('month', {$column}) + interval '1 month - 1 day')::date",
            'sqlite' => "DATE({$column}, 'start of month', '+1 month', '-1 day')",
            default => "LAST_DAY({$column})",
        };
    }

    /**
     * 日期加 N 天（天数来自另一列）
     */
    public static function dateAddDaysFromColumn(string $dateColumn, string $daysColumn): string
    {
        if (self::driver() === 'pgsql') {
            return "({$dateColumn} + ({$daysColumn} || ' days')::interval)";
        }

        return "DATE_ADD({$dateColumn}, INTERVAL {$daysColumn} DAY)";
    }

    /**
     * JSON 字段键是否存在
     */
    public static function jsonKeyExists(string $column, string $key): string
    {
        $escapedKey = str_replace("'", "''", $key);

        if (self::driver() === 'pgsql') {
            return "{$column}->>'{$escapedKey}' IS NOT NULL";
        }

        if (self::driver() === 'sqlite') {
            return "json_extract({$column}, '$.{$escapedKey}') IS NOT NULL";
        }

        return "JSON_EXTRACT({$column}, '$.\"{$escapedKey}\"') IS NOT NULL";
    }

    /**
     * JSON 字符串字段等值比较
     */
    public static function jsonStringEquals(string $column, string $key, string $value): string
    {
        $escapedValue = str_replace("'", "''", $value);

        if (self::driver() === 'pgsql') {
            return "{$column}->>'{$key}' = '{$escapedValue}'";
        }

        if (self::driver() === 'sqlite') {
            return "json_extract({$column}, '$.{$key}') = '{$escapedValue}'";
        }

        return "JSON_UNQUOTE(JSON_EXTRACT({$column}, '$.{$key}')) = '{$escapedValue}'";
    }

    /**
     * 全文搜索 WHERE 子句（绑定参数占位符 ?）
     */
    public static function fullTextMatch(string $column): string
    {
        return match (self::driver()) {
            'pgsql' => "to_tsvector('simple', {$column}) @@ to_tsquery('simple', ?)",
            'sqlite' => "{$column} LIKE '%' || replace(?, '*', '') || '%'",
            default => "MATCH({$column}) AGAINST(? IN BOOLEAN MODE)",
        };
    }

    /**
     * 全文搜索绑定值（按驱动转换通配符）
     */
    public static function fullTextBindValue(string $term): string
    {
        if (self::driver() === 'pgsql') {
            $term = rtrim($term, '*');

            return $term.':*';
        }

        return $term;
    }

    /**
     * 设置当前事务/会话的查询超时（秒）
     */
    public static function applyQueryTimeout(int $seconds): void
    {
        if (self::driver() === 'pgsql') {
            DB::statement('SET LOCAL statement_timeout = '.($seconds * 1000));
        }
    }

    /**
     * 当前时间加 N 天
     */
    public static function addDaysToNow(int $days): string
    {
        if (self::driver() === 'pgsql') {
            return "NOW() + INTERVAL '{$days} days'";
        }

        return "DATE_ADD(NOW(), INTERVAL {$days} DAY)";
    }

    /**
     * JSON 字段合并（MySQL JSON_SET / PostgreSQL jsonb ||）
     *
     * @param  array<string, mixed>  $data
     */
    public static function jsonMerge(string $column, array $data): string
    {
        if (self::driver() === 'pgsql') {
            $json = str_replace("'", "''", json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            return "COALESCE({$column}, '{}')::jsonb || '{$json}'::jsonb";
        }

        $parts = ["COALESCE({$column}, '{}')"];
        foreach ($data as $key => $value) {
            $path = '$.'.$key;
            if (is_bool($value)) {
                $parts[] = "'{$path}', ".($value ? 'true' : 'false');
            } elseif (is_int($value) || is_float($value)) {
                $parts[] = "'{$path}', {$value}";
            } else {
                $escaped = str_replace("'", "''", (string) $value);
                $parts[] = "'{$path}', '{$escaped}'";
            }
        }

        return 'JSON_SET('.implode(', ', $parts).')';
    }

    /**
     * TIMESTAMPDIFF 兼容表达式（用于 selectRaw）
     */
    public static function timestampDiff(string $unit, string $start, string $end): string
    {
        $unit = strtoupper($unit);

        if (self::driver() === 'pgsql') {
            $seconds = "EXTRACT(EPOCH FROM ({$end} - {$start}))";

            return match ($unit) {
                'MINUTE' => "({$seconds} / 60)",
                'HOUR' => "({$seconds} / 3600)",
                'DAY' => "({$seconds} / 86400)",
                default => $seconds,
            };
        }

        return "TIMESTAMPDIFF({$unit}, {$start}, {$end})";
    }

    /**
     * JSON 字段读取表达式
     */
    public static function jsonExtract(string $column, string $key): string
    {
        if (self::driver() === 'pgsql') {
            return "{$column}->>'{$key}'";
        }

        if (self::driver() === 'sqlite') {
            return "json_extract({$column}, '$.{$key}')";
        }

        return "JSON_UNQUOTE(JSON_EXTRACT({$column}, '$.{$key}'))";
    }

    /**
     * 字符串拼接（避免 PG 将双引号解析为标识符）
     */
    public static function concat(string ...$parts): string
    {
        if (self::driver() === 'pgsql') {
            return implode(' || ', $parts);
        }

        return 'CONCAT('.implode(', ', $parts).')';
    }

    /**
     * 获取数据库表名列表
     *
     * @return array<int, string>
     */
    public static function listTableNames(?string $connectionName = null): array
    {
        $connection = DB::connection($connectionName);
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $rows = $connection->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");

            return array_map(fn ($row) => $row->name, $rows);
        }

        if ($driver === 'pgsql') {
            $schema = $connection->getConfig('search_path') ?? 'public';
            $rows = $connection->select(
                'SELECT tablename AS name FROM pg_catalog.pg_tables WHERE schemaname = ? ORDER BY tablename',
                [$schema]
            );

            return array_map(fn ($row) => $row->name, $rows);
        }

        $database = $connection->getDatabaseName();
        $tables = $connection->select('SHOW TABLES');
        $key = "Tables_in_{$database}";

        return array_map(fn ($t) => $t->$key ?? current((array) $t), $tables);
    }

    /**
     * 估算单表存储占用（MB）
     */
    public static function estimateTableSizeMb(string $tableName, ?string $connectionName = null): float
    {
        $connection = DB::connection($connectionName);
        $driver = $connection->getDriverName();

        if ($driver === 'pgsql') {
            $schema = $connection->getConfig('search_path') ?? 'public';
            $qualified = str_contains($tableName, '.') ? $tableName : "{$schema}.{$tableName}";
            $result = $connection->selectOne(
                'SELECT ROUND(pg_total_relation_size(?::regclass) / 1024.0 / 1024.0, 2) as size_mb',
                [$qualified]
            );
        } elseif ($driver === 'sqlite') {
            return 0.0;
        } else {
            $result = $connection->selectOne(
                'SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb
                 FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = ?',
                [$tableName]
            );
        }

        return (float) ($result->size_mb ?? 0);
    }

    /**
     * 递增计数 upsert 的 SQL 语句（按唯一键冲突时 count + 1）
     *
     * @param  array<int, string>  $conflictColumns
     */
    public static function upsertIncrement(
        string $table,
        array $insertColumns,
        array $conflictColumns,
        string $countColumn = 'count',
        array $extraUpdate = []
    ): string {
        $driver = self::driver();
        $cols = implode(', ', $insertColumns);
        $placeholders = implode(', ', array_fill(0, count($insertColumns), '?'));

        if ($driver === 'pgsql') {
            $conflict = implode(', ', $conflictColumns);
            $updates = ["{$countColumn} = {$table}.{$countColumn} + 1", 'updated_at = CURRENT_TIMESTAMP'];
            foreach ($extraUpdate as $col => $expr) {
                $updates[] = "{$col} = {$expr}";
            }

            return "INSERT INTO {$table} ({$cols}) VALUES ({$placeholders}) "
                ."ON CONFLICT ({$conflict}) DO UPDATE SET ".implode(', ', $updates);
        }

        return "INSERT INTO {$table} ({$cols}) VALUES ({$placeholders}) "
            ."ON DUPLICATE KEY UPDATE {$countColumn} = {$countColumn} + 1, updated_at = ".self::now();
    }

    public static function mysqlFormatToPgsql(string $mysqlFormat): string
    {
        return str_replace(
            ['%Y', '%m', '%d', '%H', '%i', '%s'],
            ['YYYY', 'MM', 'DD', 'HH24', 'MI', 'SS'],
            $mysqlFormat
        );
    }

    public static function mysqlFormatToSqlite(string $mysqlFormat): string
    {
        return $mysqlFormat;
    }

    /**
     * AI 运营分析模板 SQL（按驱动返回）
     *
     * @return array<string, string>
     */
    public static function aiOpsTemplateSql(int $days = 30, int $limit = 10): array
    {
        $driver = self::driver();
        $dateCol = fn (string $col) => $driver === 'pgsql' ? "{$col}::date" : "DATE({$col})";
        $monthExpr = self::dateFormat('created_at', '%Y-%m');
        $dateExpr = $dateCol('created_at');

        if ($driver === 'pgsql') {
            $sinceDays = "created_at >= CURRENT_DATE - INTERVAL '{$days} days'";
            $since12Mo = "created_at >= CURRENT_DATE - INTERVAL '12 months'";
            $expiring = "expires_at BETWEEN NOW() AND NOW() + INTERVAL '7 days'";
            $devicesSince = "last_seen_at >= CURRENT_DATE - INTERVAL '{$days} days'";
        } else {
            $sinceDays = "created_at >= DATE_SUB(CURDATE(), INTERVAL {$days} DAY)";
            $since12Mo = "created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)";
            $expiring = "expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)";
            $devicesSince = "last_seen_at >= DATE_SUB(CURDATE(), INTERVAL {$days} DAY)";
        }

        $todayFilter = $driver === 'pgsql'
            ? "{$dateCol('created_at')} = CURRENT_DATE"
            : "DATE(created_at) = CURDATE()";

        return [
            'activation_trend' => "SELECT {$dateExpr} as date, COUNT(*) as count FROM license_activations WHERE {$sinceDays} GROUP BY {$dateExpr} ORDER BY date",
            'activation_by_product' => "SELECT p.name as product, COUNT(*) as total FROM license_activations la JOIN licenses l ON la.license_id = l.id JOIN products p ON l.product_id = p.id GROUP BY p.id, p.name ORDER BY total DESC LIMIT {$limit}",
            'license_status_dist' => "SELECT status, COUNT(*) as total FROM licenses GROUP BY status ORDER BY total DESC",
            'expiring_soon' => "SELECT l.license_key, p.name as product, l.expires_at, c.name as customer FROM licenses l JOIN products p ON l.product_id = p.id LEFT JOIN customers c ON l.customer_id = c.id WHERE l.status = 'active' AND {$expiring} ORDER BY l.expires_at LIMIT {$limit}",
            'top_customers' => "SELECT c.name, c.email, COUNT(l.id) as license_count FROM customers c LEFT JOIN licenses l ON l.customer_id = c.id GROUP BY c.id, c.name, c.email ORDER BY license_count DESC LIMIT {$limit}",
            'customer_growth' => "SELECT {$monthExpr} as month, COUNT(*) as new_customers FROM customers WHERE {$since12Mo} GROUP BY {$monthExpr} ORDER BY month",
            'device_by_platform' => 'SELECT platform, COUNT(*) as total FROM devices GROUP BY platform ORDER BY total DESC',
            'active_devices' => "SELECT {$dateCol('last_seen_at')} as date, COUNT(*) as active_devices FROM devices WHERE {$devicesSince} GROUP BY {$dateCol('last_seen_at')} ORDER BY date",
            'subscription_by_plan' => "SELECT plan, COUNT(*) as total FROM subscriptions WHERE status = 'active' GROUP BY plan ORDER BY total DESC",
            'today_activations' => "SELECT COUNT(*) as total FROM license_activations WHERE {$todayFilter}",
        ];
    }
}
