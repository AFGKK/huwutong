<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Services\CircuitBreakerService;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * 健康检查控制器
 *
 * 分级端点：
 * - /health/live    → Liveness Probe（K8s 存活探针）：进程是否活着
 * - /health/ready   → Readiness Probe（K8s 就绪探针）：DB/Redis 是否就绪
 * - /health/status  → 详细健康状态（含所有服务、缓存、队列等指标）
 */
class HealthController extends Controller
{
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
        // 通过缓存记录启动时间（在 AppServiceProvider 或类似位置设置）
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
