<?php

namespace App\Services;

use App\Models\ApmRequest;

class TracingService
{
    /**
     * 获取调用链列表（基于APM请求数据构建调用链视图）
     */
    public function getTraces(int $tenantId, array $filters = [], int $perPage = 20): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = ApmRequest::where('tenant_id', $tenantId)
            ->with('user:id,name');

        if (!empty($filters['method'])) {
            $query->where('method', strtoupper($filters['method']));
        }
        if (!empty($filters['path'])) {
            $query->where('path', 'like', "%{$filters['path']}%");
        }
        if (!empty($filters['is_slow'])) {
            $query->where('is_slow', filter_var($filters['is_slow'], FILTER_VALIDATE_BOOLEAN));
        }
        if (!empty($filters['status_code'])) {
            $query->where('status_code', (int) $filters['status_code']);
        }
        if (!empty($filters['status_range'])) {
            $range = explode('-', $filters['status_range']);
            $query->whereBetween('status_code', [(int)$range[0], (int)($range[1] ?? $range[0])]);
        }
        if (!empty($filters['duration_min'])) {
            $query->where('duration_ms', '>=', (int) $filters['duration_min']);
        }
        if (!empty($filters['duration_max'])) {
            $query->where('duration_ms', '<=', (int) $filters['duration_max']);
        }
        if (!empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        $sortField = ltrim($filters['sort'] ?? '-created_at', '-');
        $sortDir = str_starts_with($filters['sort'] ?? '-created_at', '-') ? 'desc' : 'asc';

        return $query->orderBy($sortField, $sortDir)->paginate($perPage);
    }

    /**
     * 获取单个调用链详情
     */
    public function getTraceDetail(int $id): ApmRequest
    {
        return ApmRequest::with('user:id,name,email')->findOrFail($id);
    }

    /**
     * 获取调用链统计（服务级）
     */
    public function getTraceStats(int $tenantId, ?string $from = null, ?string $to = null): array
    {
        $query = ApmRequest::where('tenant_id', $tenantId);

        if ($from) {
            $query->where('created_at', '>=', $from);
        }
        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        $total = (clone $query)->count();
        $slow = (clone $query)->where('is_slow', true)->count();
        $errors = (clone $query)->where('status_code', '>=', 500)->count();
        $clientErrors = (clone $query)->whereBetween('status_code', [400, 499])->count();

        $avgDuration = (clone $query)->avg('duration_ms');
        $p95Duration = $this->percentile($query, 95);
        $p99Duration = $this->percentile($query, 99);

        // 路径分布
        $topPaths = (clone $query)
            ->selectRaw('path, COUNT(*) as cnt, AVG(duration_ms) as avg_dur')
            ->groupBy('path')
            ->orderByDesc('cnt')
            ->limit(10)
            ->get()
            ->toArray();

        // 方法分布
        $byMethod = (clone $query)
            ->selectRaw('method, COUNT(*) as cnt')
            ->groupBy('method')
            ->pluck('cnt', 'method')
            ->toArray();

        $hourExpr = db_hour('created_at');
        $byHour = (clone $query)
            ->selectRaw("{$hourExpr} as hour, COUNT(*) as cnt")
            ->groupBy('hour')
            ->orderBy('hour')
            ->pluck('cnt', 'hour')
            ->toArray();

        return [
            'total' => $total,
            'slow' => $slow,
            'errors' => $errors,
            'client_errors' => $clientErrors,
            'error_rate' => $total > 0 ? round(($errors / $total) * 100, 2) : 0,
            'avg_duration_ms' => round($avgDuration ?? 0, 1),
            'p95_duration_ms' => round($p95Duration ?? 0, 1),
            'p99_duration_ms' => round($p99Duration ?? 0, 1),
            'top_paths' => $topPaths,
            'by_method' => $byMethod,
            'by_hour' => $byHour,
        ];
    }

    /**
     * 近似计算百分位数
     */
    protected function percentile($query, int $percentile): float
    {
        $count = (clone $query)->count();
        if ($count === 0) return 0;

        $offset = max(0, (int) ceil($count * $percentile / 100) - 1);
        $record = (clone $query)
            ->orderBy('duration_ms')
            ->skip($offset)
            ->take(1)
            ->first();

        return $record ? round($record->duration_ms, 1) : 0;
    }
}
