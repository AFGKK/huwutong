<?php

use App\Http\ApiResponse;
use App\Models\Tenant;
use App\Support\DbSql;
use Illuminate\Http\JsonResponse;

if (! function_exists('current_tenant')) {
    function current_tenant(): ?Tenant
    {
        return app()->bound(Tenant::class) ? app(Tenant::class) : null;
    }
}

if (! function_exists('api_response')) {
    /**
     * 兼容旧版 api_response() 调用
     * 新代码应使用 ApiResponse::success() / ApiResponse::error()
     */
    function api_response($data = null, int $statusCode = 200, array $headers = []): JsonResponse
    {
        if ($statusCode >= 400) {
            $message = is_array($data) ? ($data['error'] ?? $data['message'] ?? 'Error') : 'Error';
            return ApiResponse::error('REQUEST_ERROR', $message, $statusCode, $headers);
        }
        
        return ApiResponse::success($data, null, $statusCode, $headers);
    }
}

if (! function_exists('db_date_format')) {
    function db_date_format(string $column, string $mysqlFormat): string
    {
        return DbSql::dateFormat($column, $mysqlFormat);
    }
}

if (! function_exists('db_date_diff')) {
    function db_date_diff(string $end, string $start): string
    {
        return DbSql::dateDiff($end, $start);
    }
}

if (! function_exists('db_json_merge')) {
    /** @param array<string, mixed> $data */
    function db_json_merge(string $column, array $data): string
    {
        return DbSql::jsonMerge($column, $data);
    }
}

if (! function_exists('db_timestamp_diff')) {
    function db_timestamp_diff(string $unit, string $start, string $end): string
    {
        return DbSql::timestampDiff($unit, $start, $end);
    }
}

if (! function_exists('db_json_extract')) {
    function db_json_extract(string $column, string $key): string
    {
        return DbSql::jsonExtract($column, $key);
    }
}

if (! function_exists('db_hour')) {
    function db_hour(string $column): string
    {
        return DbSql::hour($column);
    }
}

if (! function_exists('db_substring_index')) {
    function db_substring_index(string $column, string $delimiter, int $count): string
    {
        return DbSql::substringIndex($column, $delimiter, $count);
    }
}

if (! function_exists('db_is_true')) {
    function db_is_true(string $column): string
    {
        return DbSql::isTrue($column);
    }
}

if (! function_exists('db_is_false')) {
    function db_is_false(string $column): string
    {
        return DbSql::isFalse($column);
    }
}

if (! function_exists('db_month_start')) {
    function db_month_start(string $column): string
    {
        return DbSql::monthStart($column);
    }
}

if (! function_exists('db_last_day_of_month')) {
    function db_last_day_of_month(string $column): string
    {
        return DbSql::lastDayOfMonth($column);
    }
}

if (! function_exists('db_date_add_days_col')) {
    function db_date_add_days_col(string $dateColumn, string $daysColumn): string
    {
        return DbSql::dateAddDaysFromColumn($dateColumn, $daysColumn);
    }
}

if (! function_exists('db_json_key_exists')) {
    function db_json_key_exists(string $column, string $key): string
    {
        return DbSql::jsonKeyExists($column, $key);
    }
}

if (! function_exists('db_json_string_equals')) {
    function db_json_string_equals(string $column, string $key, string $value): string
    {
        return DbSql::jsonStringEquals($column, $key, $value);
    }
}

if (! function_exists('db_full_text_match')) {
    function db_full_text_match(string $column): string
    {
        return DbSql::fullTextMatch($column);
    }
}

if (! function_exists('db_full_text_bind')) {
    function db_full_text_bind(string $term): string
    {
        return DbSql::fullTextBindValue($term);
    }
}
