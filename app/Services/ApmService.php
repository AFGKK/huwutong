<?php

namespace App\Services;

use App\Models\ApmRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * 应用性能监控服务
 *
 * 功能：
 * - 记录每次请求的耗时、DB 查询、缓存、外部调用等指标
 * - 标记慢请求（默认 > 1000ms）
 * - 支持请求耗时分布统计、慢请求 Top N
 *
 * 使用方式：
 *   中间件自动采集，无需手动调用。
 *   如有需要可手动记录：
 *     app(ApmService::class)->record($request, $response, $metrics);
 */
class ApmService
{
    /**
     * 慢请求阈值（毫秒）
     */
    const SLOW_THRESHOLD_MS = 1000;

    /**
     * 采样率：1/100，仅在非慢请求时生效
     * 慢请求全部记录
     */
    const SAMPLE_RATE = 100;

    /**
     * 记录请求性能数据
     */
    public function record(Request $request, Response $response, array $metrics = []): ?ApmRequest
    {
        $duration = $metrics['duration_ms'] ?? $this->computeDuration();
        $isSlow = $duration >= self::SLOW_THRESHOLD_MS;

        // 非慢请求按采样率记录
        if (! $isSlow && random_int(1, self::SAMPLE_RATE) !== 1) {
            return null;
        }

        $slowReason = null;
        if ($isSlow) {
            $reasons = [];
            if ($duration >= self::SLOW_THRESHOLD_MS) $reasons[] = "总耗时{$duration}ms";
            if (($metrics['db_duration_ms'] ?? 0) > 500) $reasons[] = "DB耗时{$metrics['db_duration_ms']}ms";
            if (($metrics['external_duration_ms'] ?? 0) > 500) $reasons[] = "外部调用耗时{$metrics['external_duration_ms']}ms";
            $slowReason = implode(', ', $reasons);

            // 日志告警
            Log::warning("慢请求检测: {$request->method()} {$request->path()} - {$slowReason}", [
                'duration_ms' => $duration,
                'route' => $request->route()?->getName(),
                'user_id' => $request->user()?->id,
            ]);
        }

        $apmRequest = ApmRequest::create([
            'method' => $request->method(),
            'path' => $this->sanitizePath($request->path()),
            'route_name' => $request->route()?->getName(),
            'status_code' => $response->getStatusCode(),
            'duration_ms' => round($duration, 2),
            'db_duration_ms' => round($metrics['db_duration_ms'] ?? 0, 2),
            'db_queries' => $metrics['db_queries'] ?? 0,
            'cache_duration_ms' => round($metrics['cache_duration_ms'] ?? 0, 2),
            'cache_hits' => $metrics['cache_hits'] ?? 0,
            'external_duration_ms' => round($metrics['external_duration_ms'] ?? 0, 2),
            'external_calls' => $metrics['external_calls'] ?? 0,
            'memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'is_slow' => $isSlow,
            'slow_reason' => $slowReason,
            'ip' => $request->ip(),
            'user_id' => $request->user()?->id,
            'tenant_id' => $request->user()?->tenant_id ?? $request->header('X-Tenant-Id'),
            'created_at' => now(),
        ]);

        return $apmRequest;
    }

    /**
     * 获取最近慢请求 Top N
     */
    public function getSlowRequests(int $limit = 20): array
    {
        return ApmRequest::where('is_slow', true)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * 获取请求耗时分布统计
     */
    public function getDurationDistribution(\Carbon\Carbon $since): array
    {
        $buckets = [
            '0-50ms' => [0, 50],
            '50-100ms' => [50, 100],
            '100-200ms' => [100, 200],
            '200-500ms' => [200, 500],
            '500-1000ms' => [500, 1000],
            '1000-3000ms' => [1000, 3000],
            '3000ms+' => [3000, PHP_FLOAT_MAX],
        ];

        $result = [];
        foreach ($buckets as $label => [$min, $max]) {
            $count = ApmRequest::where('created_at', '>=', $since)
                ->where('duration_ms', '>=', $min)
                ->when($max < PHP_FLOAT_MAX, fn ($q) => $q->where('duration_ms', '<', $max))
                ->count();
            $result[] = ['label' => $label, 'count' => $count];
        }

        return $result;
    }

    /**
     * 获取慢请求原因分布
     */
    public function getSlowReasonDistribution(\Carbon\Carbon $since): array
    {
        return ApmRequest::where('is_slow', true)
            ->where('created_at', '>=', $since)
            ->whereNotNull('slow_reason')
            ->select('slow_reason', DB::raw('count(*) as count'))
            ->groupBy('slow_reason')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->toArray();
    }

    /**
     * 获取最慢的路由 Top N
     */
    public function getSlowestRoutes(int $limit = 10): array
    {
        return ApmRequest::select('method', 'path', 'route_name',
            DB::raw('AVG(duration_ms) as avg_duration_ms'),
            DB::raw('MAX(duration_ms) as max_duration_ms'),
            DB::raw('COUNT(*) as request_count'),
            DB::raw('SUM(CASE WHEN is_slow THEN 1 ELSE 0 END) as slow_count'),
        )
            ->where('created_at', '>=', now()->subDay())
            ->groupBy('method', 'path', 'route_name')
            ->orderByDesc('avg_duration_ms')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * 获取总览统计
     */
    public function getStats(\Carbon\Carbon $since): array
    {
        return [
            'total_requests' => ApmRequest::where('created_at', '>=', $since)->count(),
            'slow_requests' => ApmRequest::where('created_at', '>=', $since)->where('is_slow', true)->count(),
            'avg_duration_ms' => round(ApmRequest::where('created_at', '>=', $since)->avg('duration_ms') ?? 0, 2),
            'avg_db_queries' => round(ApmRequest::where('created_at', '>=', $since)->avg('db_queries') ?? 0, 1),
            'avg_memory_mb' => round(ApmRequest::where('created_at', '>=', $since)->avg('memory_mb') ?? 0, 2),
        ];
    }

    /**
     * 清理超过保留期的旧数据
     */
    public function prune(int $retentionDays = 7): int
    {
        return ApmRequest::where('created_at', '<', now()->subDays($retentionDays))->delete();
    }

    /**
     * 计算请求耗时
     */
    protected function computeDuration(): float
    {
        if (defined('LARAVEL_START')) {
            return (microtime(true) - LARAVEL_START) * 1000;
        }
        return 0;
    }

    /**
     * 清理路径中的动态 ID
     */
    protected function sanitizePath(string $path): string
    {
        // 将 /api/licenses/123 → /api/licenses/{id}
        return preg_replace('#/\d+(/|$)#', '/{id}$1', $path);
    }
}
