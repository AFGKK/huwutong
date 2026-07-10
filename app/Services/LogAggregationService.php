<?php

namespace App\Services;

use App\Models\LogAggregationEntry;
use App\Models\LogAggregationIndex;
use App\Models\LogSavedSearch;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LogAggregationService
{
    /**
     * 仪表盘统计
     */
    public function dashboard(): array
    {
        $totalEntries = LogAggregationEntry::count();
        $totalIndices = LogAggregationIndex::count();
        $errorCount = LogAggregationEntry::whereIn('level', ['error', 'critical'])->count();
        $avgDuration = LogAggregationEntry::whereNotNull('duration_ms')->avg('duration_ms');

        // 等级分布
        $levelDistribution = LogAggregationEntry::selectRaw('level, COUNT(*) as count')
            ->groupBy('level')
            ->orderByDesc('count')
            ->get()
            ->toArray();

        // 来源分布
        $sourceDistribution = LogAggregationIndex::selectRaw('source, SUM(count) as total')
            ->groupBy('source')
            ->orderByDesc('total')
            ->get()
            ->toArray();

        // 最近24小时趋势
        $trend = LogAggregationEntry::selectRaw(
            db_date_format('logged_at', '%Y-%m-%d %H:00').' as hour, COUNT(*) as count'
        )
            ->where('logged_at', '>=', now()->subHours(24))
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->toArray();

        return [
            'total_entries' => $totalEntries,
            'total_indices' => $totalIndices,
            'error_count' => $errorCount,
            'avg_duration_ms' => round($avgDuration ?: 0, 2),
            'level_distribution' => $levelDistribution,
            'source_distribution' => $sourceDistribution,
            'trend_24h' => $trend,
            'last_updated' => now()->toDateTimeString(),
        ];
    }

    /**
     * 搜索日志
     */
    public function search(array $filters): LengthAwarePaginator
    {
        $query = LogAggregationEntry::query();

        // 关键词搜索
        if (!empty($filters['q'])) {
            $search = $filters['q'];
            $query->where(function ($q) use ($search) {
                $q->where('message', 'like', "%{$search}%")
                  ->orWhere('trace_id', 'like', "%{$search}%")
                  ->orWhere('request_path', 'like', "%{$search}%")
                  ->orWhere('ip', 'like', "%{$search}%");
            });
        }

        // 筛选条件
        if (!empty($filters['level'])) {
            $query->where('level', $filters['level']);
        }
        if (!empty($filters['source'])) {
            $query->whereHas('index', fn($q) => $q->where('source', $filters['source']));
        }
        if (!empty($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }
        if (!empty($filters['tenant_id'])) {
            $query->where('tenant_id', (int) $filters['tenant_id']);
        }
        if (!empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }
        if (!empty($filters['trace_id'])) {
            $query->where('trace_id', $filters['trace_id']);
        }
        if (!empty($filters['method'])) {
            $query->where('request_method', strtoupper($filters['method']));
        }
        if (!empty($filters['path'])) {
            $query->where('request_path', 'like', "%{$filters['path']}%");
        }
        if (isset($filters['status_code'])) {
            $query->where('response_status', (int) $filters['status_code']);
        }
        if (!empty($filters['duration_min'])) {
            $query->where('duration_ms', '>=', (float) $filters['duration_min']);
        }

        // 时间范围
        $timeFrom = $filters['time_from'] ?? now()->subHours(24);
        $timeTo = $filters['time_to'] ?? now();
        $query->whereBetween('logged_at', [$timeFrom, $timeTo]);

        // 排序
        $sort = $filters['sort'] ?? '-logged_at';
        if (str_starts_with($sort, '-')) {
            $query->orderByDesc(substr($sort, 1));
        } else {
            $query->orderBy($sort);
        }

        $perPage = min((int) ($filters['per_page'] ?? 50), 200);
        return $query->paginate($perPage);
    }

    /**
     * 获取单条日志详情
     */
    public function getEntry(int $id): LogAggregationEntry
    {
        return LogAggregationEntry::with('index')->findOrFail($id);
    }

    /**
     * 获取日志级别统计
     */
    public function getLevelStats(array $filters = []): array
    {
        $query = LogAggregationEntry::selectRaw(
            'level, COUNT(*) as count, AVG(duration_ms) as avg_duration'
        );

        if (!empty($filters['time_from'])) {
            $query->where('logged_at', '>=', $filters['time_from']);
        }
        if (!empty($filters['time_to'])) {
            $query->where('logged_at', '<=', $filters['time_to']);
        }

        return $query->groupBy('level')
            ->orderByDesc('count')
            ->get()
            ->toArray();
    }

    /**
     * 获取慢查询 Top
     */
    public function getSlowQueries(int $limit = 20): array
    {
        return LogAggregationEntry::where('duration_ms', '>=', config('log-aggregation.collection.slow_query_threshold_ms', 200))
            ->whereNotNull('request_path')
            ->orderByDesc('duration_ms')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * 获取请求路径统计
     */
    public function getPathStats(array $filters = []): array
    {
        $query = LogAggregationEntry::selectRaw(
            'request_path, COUNT(*) as hits, AVG(duration_ms) as avg_duration,
             SUM(CASE WHEN level IN ("error","critical") THEN 1 ELSE 0 END) as errors'
        )->whereNotNull('request_path');

        if (!empty($filters['time_from'])) {
            $query->where('logged_at', '>=', $filters['time_from']);
        }
        if (!empty($filters['time_to'])) {
            $query->where('logged_at', '<=', $filters['time_to']);
        }

        return $query->groupBy('request_path')
            ->orderByDesc('hits')
            ->limit(50)
            ->get()
            ->toArray();
    }

    /**
     * 记录日志条目
     */
    public function ingest(array $entries): int
    {
        $count = 0;
        DB::transaction(function () use ($entries, &$count) {
            foreach ($entries as $entry) {
                LogAggregationEntry::create($entry);
                $count++;
            }
        });
        return $count;
    }

    /**
     * 清理过期日志
     */
    public function prune(): int
    {
        $deleted = 0;
        $retention = config('log-aggregation.retention.detailed', 7);
        $cutoff = now()->subDays($retention);

        $deleted += LogAggregationEntry::where('logged_at', '<', $cutoff)->delete();
        $deleted += LogAggregationIndex::where('log_date', '<', $cutoff)->delete();

        return $deleted;
    }

    // ─── 保存搜索 ───

    public function saveSearch(array $data): LogSavedSearch
    {
        return LogSavedSearch::create($data);
    }

    public function listSavedSearches(): array
    {
        return LogSavedSearch::with('creator:id,name')
            ->where(function ($q) {
                $q->where('is_shared', true)
                  ->orWhere('created_by', auth()->id());
            })
            ->orderByDesc('created_at')
            ->get()
            ->toArray();
    }

    public function deleteSavedSearch(int $id): void
    {
        $search = LogSavedSearch::findOrFail($id);
        $search->delete();
    }
}
