<?php

namespace App\Services;

use App\Models\SlowQueryLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 慢查询监控服务 (M2-118)
 */
class SlowQueryMonitorService
{
    /**
     * 看板总览
     */
    public function dashboard(int $minutes = 60): array
    {
        $since = now()->subMinutes($minutes);

        $totalSlow = SlowQueryLog::where('occurred_at', '>=', $since)->count();
        $avgDuration = (float) SlowQueryLog::where('occurred_at', '>=', $since)->avg('duration_ms');
        $maxDuration = (float) SlowQueryLog::where('occurred_at', '>=', $since)->max('duration_ms');
        $uniqueHashes = SlowQueryLog::where('occurred_at', '>=', $since)->distinct('sql_hash')->count('sql_hash');
        $unresolved = SlowQueryLog::where('is_resolved', false)->count();

        // 按类型分布
        $typeDistribution = SlowQueryLog::where('occurred_at', '>=', $since)
            ->selectRaw('sql_type, COUNT(*) as count, ROUND(AVG(duration_ms), 2) as avg_duration')
            ->groupBy('sql_type')
            ->orderByDesc('count')
            ->get();

        // 按表名分布 TOP 10
        $tableDistribution = SlowQueryLog::where('occurred_at', '>=', $since)
            ->whereNotNull('table_name')
            ->selectRaw('table_name, COUNT(*) as count, ROUND(AVG(duration_ms), 2) as avg_duration, ROUND(MAX(duration_ms), 2) as max_duration')
            ->groupBy('table_name')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        // 趋势（按分钟统计过去 N 分钟）
        $trend = SlowQueryLog::where('occurred_at', '>=', $since)
            ->selectRaw(db_date_format('occurred_at', '%Y-%m-%d %H:%i').' as minute, COUNT(*) as count')
            ->groupBy('minute')
            ->orderBy('minute')
            ->get();

        return [
            'total_slow' => $totalSlow,
            'avg_duration_ms' => round($avgDuration, 2),
            'max_duration_ms' => round($maxDuration, 2),
            'unique_hashes' => $uniqueHashes,
            'unresolved' => $unresolved,
            'type_distribution' => $typeDistribution,
            'table_distribution' => $tableDistribution,
            'trend' => $trend,
        ];
    }

    /**
     * Top 慢查询列表（按哈希聚合）
     */
    public function topSlowQueries(int $minutes = 60, string $sortBy = 'avg_duration_ms', string $sortDir = 'desc'): array
    {
        $since = now()->subMinutes($minutes);

        $allowedSort = ['avg_duration_ms', 'max_duration_ms', 'occurrence_count', 'avg_rows_examined'];
        if (!in_array($sortBy, $allowedSort)) $sortBy = 'avg_duration_ms';
        $sortDir = $sortDir === 'asc' ? 'asc' : 'desc';

        return SlowQueryLog::where('occurred_at', '>=', $since)
            ->selectRaw('
                sql_hash,
                MAX(sql_text) as sql_text,
                MAX(sql_type) as sql_type,
                MAX(table_name) as table_name,
                COUNT(*) as occurrence_count,
                ROUND(AVG(duration_ms), 2) as avg_duration_ms,
                ROUND(MAX(duration_ms), 2) as max_duration_ms,
                ROUND(AVG(rows_examined)) as avg_rows_examined,
                ROUND(SUM(rows_examined)) as total_rows_examined,
                MAX(route_name) as route_name,
                MAX(suggestion) as suggestion,
                MAX(is_resolved) as is_resolved
            ')
            ->groupBy('sql_hash')
            ->orderBy($sortBy, $sortDir)
            ->limit(50)
            ->get()
            ->toArray();
    }

    /**
     * 慢查询明细列表
     */
    public function slowQueryList(Request $request): array
    {
        $query = SlowQueryLog::query()->with('resolver:id,name');

        // 筛选
        if ($request->filled('sql_type')) {
            $query->where('sql_type', $request->sql_type);
        }
        if ($request->filled('table_name')) {
            $query->where('table_name', $request->table_name);
        }
        if ($request->filled('sql_hash')) {
            $query->where('sql_hash', $request->sql_hash);
        }
        if ($request->has('is_resolved')) {
            $query->where('is_resolved', filter_var($request->is_resolved, FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->filled('min_duration')) {
            $query->where('duration_ms', '>=', (float) $request->min_duration);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('sql_text', 'like', "%{$search}%")
                  ->orWhere('table_name', 'like', "%{$search}%")
                  ->orWhere('route_name', 'like', "%{$search}%");
            });
        }

        $sortField = in_array($request->sort_field, ['duration_ms', 'occurred_at', 'rows_examined'])
            ? $request->sort_field : 'occurred_at';
        $sortDir = $request->sort_dir === 'asc' ? 'asc' : 'desc';

        $perPage = min((int) $request->input('per_page', 25), 100);
        $page = (int) $request->input('page', 1);

        $total = $query->count();
        $items = $query->orderBy($sortField, $sortDir)
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /**
     * 获取慢查询详情（含 EXPLAIN 结果）
     */
    public function showDetail(int $id): ?SlowQueryLog
    {
        return SlowQueryLog::with('resolver:id,name')->findOrFail($id);
    }

    /**
     * 执行 EXPLAIN 并生成优化建议
     */
    public function explainAndSuggest(int $id): array
    {
        $log = SlowQueryLog::findOrFail($id);

        $sql = $log->sql_text;
        $suggestion = null;
        $explainResult = null;

        try {
            // 只对 SELECT 执行 EXPLAIN
            if (strtoupper($log->sql_type) === 'SELECT') {
                $explainRaw = DB::select("EXPLAIN FORMAT=JSON {$sql}");
                $explainResult = json_encode($explainRaw, JSON_UNESCAPED_UNICODE);

                // 生成建议
                $suggestion = $this->generateSuggestion($explainRaw, $log);
            } else {
                $suggestion = '非 SELECT 查询，无需 EXPLAIN。建议检查索引和查询逻辑。';
            }

            $log->update([
                'explain_result' => $explainResult,
                'suggestion' => $suggestion,
            ]);
        } catch (\Throwable $e) {
            $suggestion = 'EXPLAIN 执行失败: ' . $e->getMessage();
            Log::warning("EXPLAIN failed for slow query #{$id}: " . $e->getMessage());
        }

        return [
            'explain_result' => $explainResult ? json_decode($explainResult, true) : null,
            'suggestion' => $suggestion,
        ];
    }

    /**
     * 标记为已处理
     */
    public function markResolved(int $id, int $userId): SlowQueryLog
    {
        $log = SlowQueryLog::findOrFail($id);
        $log->update([
            'is_resolved' => true,
            'resolved_at' => now(),
            'resolved_by' => $userId,
        ]);
        return $log->fresh('resolver:id,name');
    }

    /**
     * 批量标记已处理
     */
    public function batchResolve(array $ids): int
    {
        return SlowQueryLog::whereIn('id', $ids)->update([
            'is_resolved' => true,
            'resolved_at' => now(),
        ]);
    }

    /**
     * 清理过期数据
     */
    public function prune(): int
    {
        $cutoff = now()->subDays(config('slow-query.retention_days', 30));
        $batchSize = config('slow-query.prune_batch_size', 1000);

        $deleted = 0;
        do {
            $count = SlowQueryLog::where('occurred_at', '<', $cutoff)
                ->limit($batchSize)
                ->delete();
            $deleted += $count;
        } while ($count > 0);

        return $deleted;
    }

    /**
     * 按 API 路径维度下钻
     */
    public function byRoute(int $minutes = 60): array
    {
        $since = now()->subMinutes($minutes);

        return SlowQueryLog::where('occurred_at', '>=', $since)
            ->whereNotNull('route_name')
            ->selectRaw('
                route_name,
                MAX(request_path) as request_path,
                COUNT(*) as occurrence_count,
                ROUND(AVG(duration_ms), 2) as avg_duration_ms,
                ROUND(MAX(duration_ms), 2) as max_duration_ms,
                COUNT(DISTINCT sql_hash) as unique_queries
            ')
            ->groupBy('route_name')
            ->orderByDesc('occurrence_count')
            ->limit(20)
            ->get()
            ->toArray();
    }

    /**
     * 检查是否需要触发告警
     */
    public function checkAlert(): ?array
    {
        if (!config('slow-query.alert.enabled', true)) return null;

        $threshold = config('slow-query.alert.threshold_per_5min', 10);
        $since = now()->subMinutes(5);

        $count = SlowQueryLog::where('occurred_at', '>=', $since)->count();

        if ($count >= $threshold) {
            return [
                'count' => $count,
                'threshold' => $threshold,
                'message' => "最近5分钟内检测到 {$count} 条慢查询（阈值: {$threshold}），建议及时处理。",
                'time' => now()->toDateTimeString(),
            ];
        }

        return null;
    }

    /**
     * 从 EXPLAIN 结果生成优化建议
     */
    protected function generateSuggestion(array $explainRaw, SlowQueryLog $log): string
    {
        $tips = [];

        foreach ($explainRaw as $row) {
            $row = (array) $row;

            $type = $row['type'] ?? '';

            if (in_array($type, ['ALL'])) {
                $tips[] = "🔴 全表扫描 (type=ALL)：表 `{$row['table']}` 缺少合适的索引，建议添加索引覆盖查询条件。";
            }
            if (in_array($type, ['INDEX'])) {
                $tips[] = "🟡 索引全扫描 (type=INDEX)：表 `{$row['table']}` 扫描了整个索引树，考虑缩小索引范围。";
            }
            if (($row['rows'] ?? 0) > 10000) {
                $tips[] = "📊 扫描行数过多 ({$row['rows']})：表 `{$row['table']}` 扫描了 {$row['rows']} 行，建议优化查询条件或添加复合索引。";
            }
            if (!empty($row['Extra']) && str_contains($row['Extra'], 'Using temporary')) {
                $tips[] = "🧩 使用了临时表 (Using temporary)：建议优化 ORDER BY 和 GROUP BY 的索引。";
            }
            if (!empty($row['Extra']) && str_contains($row['Extra'], 'Using filesort')) {
                $tips[] = "📋 使用了文件排序 (Using filesort)：建议为排序字段创建索引。";
            }
            if (!empty($row['Extra']) && str_contains($row['Extra'], 'Using where; Using index')) {
                $tips[] = "✅ 覆盖索引 (Using where; Using index)：当前查询使用了覆盖索引，性能良好。";
            }
            if (($row['key'] ?? '') === null || $row['key'] === '') {
                $tips[] = "⚠️ 未使用索引 (key=NULL)：表 `{$row['table']}` 没有使用任何索引。";
            }
        }

        if (empty($tips)) {
            $tips[] = "✅ 当前查询的 EXPLAIN 结果未见明显异常。建议关注查询频率和扫描行数。";
        }

        return implode("\n", $tips);
    }
}
