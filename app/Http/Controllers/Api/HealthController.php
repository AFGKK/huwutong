<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\SystemHealthThreshold;
use App\Services\CircuitBreakerService;
use App\Services\SystemHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;

/**
 * 健康检查控制器
 *
 * 分级端点：
 * - /health/live    → Liveness Probe（K8s 存活探针）：进程是否活着
 * - /health/ready   → Readiness Probe（K8s 就绪探针）：DB/Redis 是否就绪
 * - /health/status  → 详细健康状态（含所有服务、缓存、队列等指标）
 *
 * 系统健康管理端点（原 SystemHealthController）：
 * - GET  /admin/system-health/dashboard
 * - GET  /admin/system-health/check
 * - GET  /admin/system-health/trend
 * - POST /admin/system-health/snapshot
 * - GET  /admin/system-health/thresholds
 * - PUT  /admin/system-health/thresholds/{id}
 * - GET  /admin/system-health/failed-jobs
 */
class HealthController extends Controller
{
    public function __construct(
        protected SystemHealthService $healthService,
    ) {}

    // ── 基础健康检查（原 HealthController 方法） ──

    /**
     * 存活检查
     *
     * 仅验证应用是否在运行，不检查依赖服务。
     * 适合 Kubernetes livenessProbe。
     */
    public function live(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
            'service' => 'hwt-api',
        ]);
    }

    /**
     * 就绪检查
     *
     * 检查 DB 和 Redis 是否就绪。
     * 适合 Kubernetes readinessProbe。
     */
    public function ready(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
        ];

        $allHealthy = collect($checks)->every(fn($c) => $c['healthy']);

        $statusCode = $allHealthy ? 200 : 503;

        return response()->json([
            'status' => $allHealthy ? 'ok' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ], $statusCode);
    }

    /**
     * 详细健康状态
     *
     * 返回所有子服务的健康状态 + 熔断信息 + 缓存统计。
     */
    public function status(): JsonResponse
    {
        $breaker = app(CircuitBreakerService::class);

        $status = [
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
            'service' => 'hwt-api',
            'version' => config('app.version', '1.0.0'),
            'environment' => config('app.env'),
            'uptime' => $this->getUptime(),
            'checks' => [
                'database' => $this->checkDatabase(),
                'redis' => $this->checkRedis(),
                'cache' => $this->checkCache(),
                'queue' => $this->checkQueue(),
            ],
            'circuit_breakers' => $breaker->getAllStates(),
            'php' => [
                'version' => PHP_VERSION,
                'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            ],
        ];

        // 如果任何关键检查失败，返回 503
        $criticalHealthy = $status['checks']['database']['healthy']
            && $status['checks']['redis']['healthy'];

        return response()->json(
            $status,
            $criticalHealthy ? 200 : 503,
        );
    }

    // ── 系统健康管理（原 SystemHealthController 方法） ──

    /**
     * 系统健康仪表盘
     * GET /api/admin/system-health/dashboard
     */
    public function dashboard(): JsonResponse
    {
        return ApiResponse::success($this->healthService->getDashboard());
    }

    /**
     * 实时健康检查
     * GET /api/admin/system-health/check
     */
    public function check(): JsonResponse
    {
        return ApiResponse::success($this->healthService->performFullCheck());
    }

    /**
     * 健康趋势数据
     * GET /api/admin/system-health/trend?period=24h|7d|30d|90d
     */
    public function trend(Request $request): JsonResponse
    {
        $period = $request->input('period', '24h');
        if (!in_array($period, ['24h', '7d', '30d', '90d'])) {
            $period = '24h';
        }
        return ApiResponse::success($this->healthService->getTrend($period));
    }

    /**
     * 手动创建健康快照
     * POST /api/admin/system-health/snapshot
     */
    public function snapshot(): JsonResponse
    {
        $log = $this->healthService->snapshot();
        return ApiResponse::created($log, __('app.api.system_health_api.snapshot_recorded'));
    }

    /**
     * 获取阈值配置
     * GET /api/admin/system-health/thresholds
     */
    public function thresholds(): JsonResponse
    {
        return ApiResponse::success(
            SystemHealthThreshold::where('is_active', true)->get()
        );
    }

    /**
     * 更新阈值配置
     * PUT /api/admin/system-health/thresholds/{id}
     */
    public function updateThreshold(Request $request, int $id): JsonResponse
    {
        $threshold = SystemHealthThreshold::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'warning_threshold' => 'nullable|numeric',
            'critical_threshold' => 'nullable|numeric',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.api.system_health_api.param_error'), $validator->errors()->toArray());
        }

        $threshold->update($validator->validated());
        return ApiResponse::success($threshold->fresh(), __('app.api.system_health_api.threshold_updated'));
    }

    /**
     * 获取失败任务列表
     * GET /api/admin/system-health/failed-jobs
     */
    public function failedJobs(): JsonResponse
    {
        try {
            $jobs = \Illuminate\Support\Facades\DB::table('failed_jobs')
                ->orderByDesc('failed_at')
                ->limit(50)
                ->get();
            return ApiResponse::success($jobs);
        } catch (\Throwable $e) {
            return ApiResponse::success([]);
        }
    }

    // ── Protected helper methods ──

    /**
     * 数据库检查
     */
    protected function checkDatabase(): array
    {
        $start = microtime(true);
        try {
            DB::select('SELECT 1');
            $latencyMs = round((microtime(true) - $start) * 1000, 2);
            return [
                'healthy' => true,
                'latency_ms' => $latencyMs,
                'driver' => config('database.default'),
            ];
        } catch (\Throwable $e) {
            return [
                'healthy' => false,
                'error' => $e->getMessage(),
                'driver' => config('database.default'),
            ];
        }
    }

    /**
     * Redis 检查
     */
    protected function checkRedis(): array
    {
        if (app()->environment('testing') && config('cache.default') === 'array') {
            return [
                'healthy' => true,
                'latency_ms' => 0,
                'driver' => 'array',
            ];
        }

        $start = microtime(true);
        try {
            Cache::store('redis')->set('health:ping', 'pong', 5);
            $val = Cache::store('redis')->get('health:ping');
            $latencyMs = round((microtime(true) - $start) * 1000, 2);
            return [
                'healthy' => $val === 'pong',
                'latency_ms' => $latencyMs,
            ];
        } catch (\Throwable $e) {
            return [
                'healthy' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 缓存检查
     */
    protected function checkCache(): array
    {
        try {
            $driver = config('cache.default');
            Cache::set('health:cache_check', 'ok', 1);
            $val = Cache::get('health:cache_check');
            return [
                'healthy' => $val === 'ok',
                'driver' => $driver,
            ];
        } catch (\Throwable $e) {
            return [
                'healthy' => false,
                'driver' => config('cache.default'),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 队列检查
     */
    protected function checkQueue(): array
    {
        try {
            $connection = config('queue.default');
            return [
                'healthy' => true,
                'connection' => $connection,
            ];
        } catch (\Throwable $e) {
            return [
                'healthy' => false,
                'connection' => config('queue.default'),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 获取应用运行时长
     */
    protected function getUptime(): string
    {
        $startedAt = Cache::get('app:started_at', now()->timestamp);
        $seconds = time() - $startedAt;

        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        $parts = [];
        if ($days > 0) {
            $parts[] = "{$days}d";
        }
        if ($hours > 0) {
            $parts[] = "{$hours}h";
        }
        $parts[] = "{$minutes}m";

        return implode(' ', $parts);
    }
}
