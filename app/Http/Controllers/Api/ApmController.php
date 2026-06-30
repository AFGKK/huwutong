<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ApmRequest;
use App\Services\ApmService;
use App\Services\OpenTelemetryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class ApmController extends Controller
{
    public function __construct(
        protected ApmService $apmService,
        protected OpenTelemetryService $otelService,
    ) {}

    /**
     * APM 总览统计
     */
    public function overview(Request $request): \Illuminate\Http\JsonResponse
    {
        $period = $this->resolvePeriod($request);
        $since = now()->subHours($period);

        return ApiResponse::success([
            'stats' => $this->apmService->getStats($since),
            'distribution' => $this->apmService->getDurationDistribution($since),
            'slow_reasons' => $this->apmService->getSlowReasonDistribution($since),
        ]);
    }

    /**
     * 慢请求列表
     */
    public function slowRequests(): \Illuminate\Http\JsonResponse
    {
        $requests = $this->apmService->getSlowRequests(20);

        return ApiResponse::success($requests);
    }

    /**
     * 最慢路由 Top N
     */
    public function slowestRoutes(Request $request): \Illuminate\Http\JsonResponse
    {
        $limit = min((int) $request->input('limit', 10), 50);
        $routes = $this->apmService->getSlowestRoutes($limit);

        return ApiResponse::success($routes);
    }

    /**
     * 获取单个 APM 记录详情
     */
    public function show(int $id): \Illuminate\Http\JsonResponse
    {
        $record = \App\Models\ApmRequest::find($id);

        if (! $record) {
            return ApiResponse::notFound('APM 记录不存在');
        }

        return ApiResponse::success($record);
    }

    /**
     * 手动触发 APM 数据清理
     */
    public function prune(): \Illuminate\Http\JsonResponse
    {
        $deleted = $this->apmService->prune();

        return ApiResponse::success(['deleted' => $deleted], "已清理 {$deleted} 条过期记录");
    }

    /**
     * OpenTelemetry 集成状态
     */
    public function otelStatus(): \Illuminate\Http\JsonResponse
    {
        return ApiResponse::success(
            $this->otelService->getHealth()
        );
    }

    /**
     * 获取 APM 配置（采样率、保留期等）
     */
    public function config(): \Illuminate\Http\JsonResponse
    {
        return ApiResponse::success([
            'slow_threshold_ms' => config('apm.slow_threshold_ms', 1000),
            'sample_rate' => config('apm.sample_rate', 100),
            'retention_days' => config('apm.retention_days', 7),
            'db_slow_threshold_ms' => config('apm.db_slow_threshold_ms', 100),
            'otel' => $this->otelService->getHealth(),
        ]);
    }

    /**
     * 监控总览仪表盘 API
     *
     * 聚合 APM 性能指标 + Telescope 异常/慢查询 + 服务健康状态 + 系统资源
     */
    public function dashboard(Request $request): \Illuminate\Http\JsonResponse
    {
        $period = $this->resolvePeriod($request);
        $since = now()->subHours($period);
        $cacheKey = "apm:dashboard:{$period}";

        // 缓存 60 秒以减少频繁查询
        $data = Cache::remember($cacheKey, 60, function () use ($since) {
            $apmStats = $this->apmService->getStats($since);
            $distribution = $this->apmService->getDurationDistribution($since);

            // 近 24 小时请求趋势（按小时聚合）
            $hourlyTrend = ApmRequest::where('created_at', '>=', now()->subDay())
                ->selectRaw("DATE_FORMAT(created_at, '%Y-%m-%d %H:00') as hour")
                ->selectRaw('COUNT(*) as total')
                ->selectRaw('AVG(duration_ms) as avg_duration')
                ->selectRaw("SUM(CASE WHEN is_slow THEN 1 ELSE 0 END) as slow_count")
                ->groupBy('hour')
                ->orderBy('hour')
                ->get()
                ->toArray();

            // 慢请求 Top 10
            $slowRequests = $this->apmService->getSlowRequests(10);

            // 最慢路由 Top 10
            $slowestRoutes = $this->apmService->getSlowestRoutes(10);

            // Telescope 聚合指标（从 telescope_entries 表查询）
            $telescope = [];
            try {
                $telescope = $this->getTelescopeMetrics($since);
            } catch (\Throwable $e) {
                $telescope = ['error' => $e->getMessage()];
            }

            // OpenTelemetry 状态
            $otelHealth = $this->otelService->getHealth();

            // 服务健康状态
            $serviceHealth = $this->getServiceHealth();

            return [
                'apm' => [
                    'stats' => $apmStats,
                    'distribution' => $distribution,
                    'hourly_trend' => $hourlyTrend,
                    'slow_requests' => $slowRequests,
                    'slowest_routes' => $slowestRoutes,
                ],
                'telescope' => $telescope,
                'otel' => $otelHealth,
                'service_health' => $serviceHealth,
                'generated_at' => now()->toIso8601String(),
            ];
        });

        return ApiResponse::success($data);
    }

    /**
     * 从 Telescope 表获取聚合指标
     */
    protected function getTelescopeMetrics(\Carbon\Carbon $since): array
    {
        if (! $this->telescopeTableExists()) {
            return ['available' => false, 'message' => 'Telescope 表未就绪'];
        }

        $exceptions = \Illuminate\Support\Facades\DB::table('telescope_entries')
            ->where('type', 'exception')
            ->where('created_at', '>=', $since)
            ->count();

        $slowQueries = \Illuminate\Support\Facades\DB::table('telescope_entries')
            ->where('type', 'query')
            ->where('created_at', '>=', $since)
            ->count();

        $failedJobs = \Illuminate\Support\Facades\DB::table('telescope_entries')
            ->where('type', 'failed_job')
            ->where('created_at', '>=', $since)
            ->count();

        $recentExceptions = \Illuminate\Support\Facades\DB::table('telescope_entries')
            ->where('type', 'exception')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get(['id', 'content', 'created_at'])
            ->map(function ($entry) {
                $content = json_decode($entry->content, true);
                return [
                    'id' => $entry->id,
                    'message' => $content['exception']['message'] ?? 'Unknown',
                    'class' => $content['exception']['class'] ?? '',
                    'file' => $content['exception']['file'] ?? '',
                    'line' => $content['exception']['line'] ?? 0,
                    'created_at' => $entry->created_at,
                ];
            })
            ->toArray();

        return [
            'available' => true,
            'stats' => [
                'exceptions' => $exceptions,
                'failed_jobs' => $failedJobs,
                'queries' => $slowQueries,
            ],
            'recent_exceptions' => $recentExceptions,
        ];
    }

    /**
     * 检查 Telescope 表是否存在
     */
    protected function telescopeTableExists(): bool
    {
        try {
            \Illuminate\Support\Facades\DB::select('SELECT 1 FROM telescope_entries LIMIT 1');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * 获取服务健康状态
     */
    protected function getServiceHealth(): array
    {
        $health = [
            'database' => false,
            'cache' => false,
            'redis' => false,
            'queue' => false,
            'storage' => false,
        ];

        // 数据库
        try {
            \Illuminate\Support\Facades\DB::select('SELECT 1');
            $health['database'] = true;
        } catch (\Throwable) {}

        // 缓存
        try {
            Cache::store(config('cache.default'))->set('health:ping', true, 10);
            $health['cache'] = Cache::store(config('cache.default'))->get('health:ping') === true;
        } catch (\Throwable) {}

        // Redis
        try {
            if (config('cache.default') === 'redis' || config('queue.default') === 'redis') {
                Cache::store('redis')->set('health:ping', true, 10);
                $health['redis'] = Cache::store('redis')->get('health:ping') === true;
            } else {
                $health['redis'] = true; // Not used
            }
        } catch (\Throwable) {}

        // 队列
        try {
            $health['queue'] = in_array(config('queue.default'), ['sync', 'database', 'redis']);
        } catch (\Throwable) {}

        // 存储
        try {
            $disk = config('filesystems.default', 'local');
            $testPath = 'health-' . uniqid() . '.tmp';
            \Illuminate\Support\Facades\Storage::disk($disk)->put($testPath, '1');
            \Illuminate\Support\Facades\Storage::disk($disk)->delete($testPath);
            $health['storage'] = true;
        } catch (\Throwable) {}

        // 整体健康度
        $health['overall'] = $health['database'] && $health['cache'] && $health['storage'];

        return $health;
    }

    /**
     * 解析统计周期（小时）
     */
    protected function resolvePeriod(Request $request): int
    {
        $periods = [1, 6, 12, 24, 72, 168];
        $period = (int) $request->input('period', 24);

        if (! in_array($period, $periods)) {
            $period = 24;
        }

        return $period;
    }
}
