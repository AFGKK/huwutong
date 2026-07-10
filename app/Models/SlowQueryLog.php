<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\SlowQueryLog
 *
 * @property int $id
 * @property string $sql_hash SQL MD5 哈希
 * @property string $sql_text SQL 原文
 * @property string $sql_type SELECT/INSERT/UPDATE/DELETE
 * @property string|null $database_name
 * @property string|null $table_name
 * @property float $duration_ms
 * @property int $rows_examined
 * @property int $rows_sent
 * @property int $lock_time_ms
 * @property string|null $stack_trace
 * @property string|null $route_name
 * @property string|null $request_path
 * @property string|null $request_method
 * @property string|null $explain_result
 * @property string|null $suggestion
 * @property bool $is_resolved
 * @property string|null $resolved_at
 * @property int|null $resolved_by
 * @property string $occurred_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @mixin IdeHelperSlowQueryLog
 */
class SlowQueryLog extends Model
{
    protected $table = 'slow_query_logs';

    protected $fillable = [
        'sql_hash',
        'sql_text',
        'sql_type',
        'database_name',
        'table_name',
        'duration_ms',
        'rows_examined',
        'rows_sent',
        'lock_time_ms',
        'stack_trace',
        'route_name',
        'request_path',
        'request_method',
        'explain_result',
        'suggestion',
        'is_resolved',
        'resolved_at',
        'resolved_by',
        'occurred_at',
    ];

    protected $casts = [
        'duration_ms' => 'float',
        'rows_examined' => 'integer',
        'rows_sent' => 'integer',
        'lock_time_ms' => 'integer',
        'is_resolved' => 'boolean',
        'occurred_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * 获取 EXPLAIN 结果数组
     */
    public function getExplainArray(): ?array
    {
        if (!$this->explain_result) return null;
        return json_decode($this->explain_result, true);
    }

    /**
     * 按 SQL 哈希聚合统计
     */
    public function scopeGroupedByHash($query, int $minutes = 60)
    {
        return $query->selectRaw('
                sql_hash,
                MAX(sql_text) as sql_text,
                MAX(sql_type) as sql_type,
                MAX(table_name) as table_name,
                COUNT(*) as occurrence_count,
                ROUND(AVG(duration_ms), 2) as avg_duration_ms,
                ROUND(MAX(duration_ms), 2) as max_duration_ms,
                ROUND(MIN(duration_ms), 2) as min_duration_ms,
                ROUND(AVG(rows_examined)) as avg_rows_examined,
                ROUND(AVG(rows_sent)) as avg_rows_sent,
                MAX(route_name) as route_name,
                MAX(suggestion) as suggestion,
                MAX(is_resolved) as is_resolved
            ')
            ->where('occurred_at', '>=', now()->subMinutes($minutes))
            ->groupBy('sql_hash');
    }
}
