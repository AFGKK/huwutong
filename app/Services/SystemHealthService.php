<?php

namespace App\Services;

use App\Models\AlertEvent;
use App\Models\SystemHealthLog;
use App\Models\SystemHealthThreshold;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class SystemHealthService
{
    /**
     * 执行完整的系统健康检查
     */
    public function performFullCheck(): array
    {
        $dbCheck = $this->checkDatabase();
        $redisCheck = $this->checkRedis();
        $cacheCheck = $this->checkCache();
        $queueCheck = $this->checkQueue();
        $memory = $this->getMemoryUsage();
        $disk = $this->getDiskUsage();
        $dbConnections = $this->getDbConnections();
        $failedJobs = $this->getFailedJobsCount();
        $circuitBreakers = $this->getCircuitBreakerStates();

        // Calculate overall score
        $scores = [];
        $scores[] = $dbCheck['healthy'] ? 100 : 0;
        $scores[] = $redisCheck['healthy'] ? 100 : 0;
        $scores[] = $cacheCheck['healthy'] ? 100 : 30;
        $scores[] = $queueCheck['healthy'] ? 100 : 30;

        // Latency penalties
        $dbLatency = $dbCheck['latency_ms'] ?? 0;
        if ($dbLatency > 2000) $scores[] = 20;
        elseif ($dbLatency > 500) $scores[] = 50;

        $redisLatency = $redisCheck['latency_ms'] ?? 0;
        if ($redisLatency > 1000) $scores[] = 20;
        elseif ($redisLatency > 200) $scores[] = 50;

        // Disk usage penalty
        $diskPercent = $disk['percent'] ?? 0;
        if ($diskPercent > 95) $scores[] = 10;
        elseif ($diskPercent > 80) $scores[] = 50;

        // Memory penalty
        $memoryMb = $memory['current_mb'] ?? 0;
        if ($memoryMb > 256) $scores[] = 20;
        elseif ($memoryMb > 128) $scores[] = 50;

        // Queue penalty
        $queueSize = $queueCheck['size'] ?? 0;
        if ($queueSize > 500) $scores[] = 10;
        elseif ($queueSize > 100) $scores[] = 50;

        $overallScore = round(array_sum($scores) / count($scores), 2);

        $allHealthy = $dbCheck['healthy'] && $redisCheck['healthy'];
        $status = $allHealthy ? 'ok' : ($dbCheck['healthy'] || $redisCheck['healthy'] ? 'degraded' : 'down');

        return [
            'status' => $status,
            'overall_score' => $overallScore,
            'checks' => [
                'database' => $dbCheck,
                'redis' => $redisCheck,
                'cache' => $cacheCheck,
                'queue' => $queueCheck,
            ],
            'resources' => [
                'memory' => $memory,
                'disk' => $disk,
                'db_connections' => $dbConnections,
                'failed_jobs' => $failedJobs,
            ],
            'circuit_breakers' => $circuitBreakers,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * 执行检查并保存快照
     */
    public function snapshot(): SystemHealthLog
    {
        $result = $this->performFullCheck();

        return SystemHealthLog::create([
            'status' => $result['status'],
            'overall_score' => $result['overall_score'],
            'db_latency_ms' => $result['checks']['database']['latency_ms'] ?? null,
            'db_healthy' => $result['checks']['database']['healthy'],
            'redis_latency_ms' => $result['checks']['redis']['latency_ms'] ?? null,
            'redis_healthy' => $result['checks']['redis']['healthy'],
            'cache_healthy' => $result['checks']['cache']['healthy'],
            'cache_driver' => $result['checks']['cache']['driver'] ?? null,
            'queue_healthy' => $result['checks']['queue']['healthy'],
            'queue_connection' => $result['checks']['queue']['connection'] ?? null,
            'queue_size' => $result['checks']['queue']['size'] ?? 0,
            'memory_mb' => $result['resources']['memory']['current_mb'] ?? 0,
            'peak_memory_mb' => $result['resources']['memory']['peak_mb'] ?? 0,
            'disk_usage_percent' => $result['resources']['disk']['percent'] ?? 0,
            'disk_free_gb' => $result['resources']['disk']['free_gb'] ?? 0,
            'db_connections' => $result['resources']['db_connections'] ?? 0,
            'failed_jobs_count' => $result['resources']['failed_jobs'] ?? 0,
            'circuit_breakers' => $result['circuit_breakers'] ?? [],
            'snapped_at' => now(),
        ]);
    }

    /**
     * 获取系统健康仪表盘
     */
    public function getDashboard(): array
    {
        $current = $this->performFullCheck();

        // Recent health history (last 24 hours)
        $history = SystemHealthLog::orderByDesc('snapped_at')
            ->limit(24 * 6) // ~6 hours at 5-min intervals
            ->get(['overall_score', 'status', 'snapped_at', 'db_healthy', 'redis_healthy',
                   'disk_usage_percent', 'memory_mb', 'queue_size']);

        // Calculate uptime for last 24h, 7d, 30d
        $uptime24h = $this->calculateUptime(now()->subDay());
        $uptime7d = $this->calculateUptime(now()->subDays(7));
        $uptime30d = $this->calculateUptime(now()->subDays(30));

        // Recent alert events
        $recentAlerts = AlertEvent::with('rule:id,name')
            ->orderByDesc('fired_at')
            ->limit(10)
            ->get();

        // Topology/status summary for each service
        $services = [
            'database' => [
                'name' => '数据库',
                'status' => $current['checks']['database']['healthy'] ? 'healthy' : 'down',
                'latency_ms' => $current['checks']['database']['latency_ms'] ?? 0,
                'detail' => config('database.default'),
            ],
            'redis' => [
                'name' => 'Redis',
                'status' => $current['checks']['redis']['healthy'] ? 'healthy' : 'down',
                'latency_ms' => $current['checks']['redis']['latency_ms'] ?? 0,
            ],
            'cache' => [
                'name' => '缓存',
                'status' => $current['checks']['cache']['healthy'] ? 'healthy' : 'down',
                'driver' => $current['checks']['cache']['driver'] ?? 'unknown',
            ],
            'queue' => [
                'name' => '队列',
                'status' => $current['checks']['queue']['healthy'] ? 'healthy' : 'degraded',
                'connection' => $current['checks']['queue']['connection'] ?? 'unknown',
                'size' => $current['checks']['queue']['size'] ?? 0,
            ],
        ];

        // Threshold configs
        $thresholds = SystemHealthThreshold::where('is_active', true)->get();

        return [
            'current' => $current,
            'services' => $services,
            'history' => $history,
            'uptime' => [
                'last_24h' => $uptime24h,
                'last_7d' => $uptime7d,
                'last_30d' => $uptime30d,
            ],
            'recent_alerts' => $recentAlerts,
            'thresholds' => $thresholds,
        ];
    }

    /**
     * 计算指定时段内的系统可用率
     */
    protected function calculateUptime(\DateTime $since): float
    {
        $snapshots = SystemHealthLog::where('snapped_at', '>=', $since)->get();
        if ($snapshots->isEmpty()) return 100;

        $okCount = $snapshots->filter(fn($s) => $s->status === 'ok')->count();
        return round(($okCount / $snapshots->count()) * 100, 2);
    }

    /**
     * 获取健康趋势数据
     */
    public function getTrend(string $period = '24h'): array
    {
        $since = match ($period) {
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            '90d' => now()->subDays(90),
            default => now()->subDay(),
        };

        $logs = SystemHealthLog::where('snapped_at', '>=', $since)
            ->orderBy('snapped_at')
            ->get();

        return [
            'labels' => $logs->pluck('snapped_at')->map(fn($d) => $d->format('Y-m-d H:i'))->toArray(),
            'scores' => $logs->pluck('overall_score')->toArray(),
            'db_latency' => $logs->pluck('db_latency_ms')->toArray(),
            'redis_latency' => $logs->pluck('redis_latency_ms')->toArray(),
            'disk_usage' => $logs->pluck('disk_usage_percent')->toArray(),
            'memory' => $logs->pluck('memory_mb')->toArray(),
            'queue_sizes' => $logs->pluck('queue_size')->toArray(),
        ];
    }

    /**
     * 检查数据库
     */
    protected function checkDatabase(): array
    {
        $start = microtime(true);
        try {
            DB::select('SELECT 1');
            $latencyMs = round((microtime(true) - $start) * 1000, 2);
            return ['healthy' => true, 'latency_ms' => $latencyMs, 'driver' => config('database.default')];
        } catch (\Throwable $e) {
            return ['healthy' => false, 'error' => $e->getMessage(), 'driver' => config('database.default')];
        }
    }

    /**
     * 检查 Redis
     */
    protected function checkRedis(): array
    {
        $start = microtime(true);
        try {
            Cache::store('redis')->set('syshealth:ping', 'pong', 5);
            $val = Cache::store('redis')->get('syshealth:ping');
            $latencyMs = round((microtime(true) - $start) * 1000, 2);
            return ['healthy' => $val === 'pong', 'latency_ms' => $latencyMs];
        } catch (\Throwable $e) {
            return ['healthy' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 检查缓存
     */
    protected function checkCache(): array
    {
        try {
            $driver = config('cache.default');
            Cache::set('syshealth:cache_check', 'ok', 1);
            $val = Cache::get('syshealth:cache_check');
            return ['healthy' => $val === 'ok', 'driver' => $driver];
        } catch (\Throwable $e) {
            return ['healthy' => false, 'driver' => config('cache.default'), 'error' => $e->getMessage()];
        }
    }

    /**
     * 检查队列
     */
    protected function checkQueue(): array
    {
        try {
            $connection = config('queue.default');
            $size = 0;
            try {
                $size = DB::table('jobs')->count();
            } catch (\Throwable) {
                // jobs table might not exist in some setups
            }
            return ['healthy' => true, 'connection' => $connection, 'size' => $size];
        } catch (\Throwable $e) {
            return ['healthy' => false, 'connection' => config('queue.default'), 'error' => $e->getMessage()];
        }
    }

    /**
     * 获取内存使用
     */
    protected function getMemoryUsage(): array
    {
        return [
            'current_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'limit_mb' => $this->getMemoryLimit(),
        ];
    }

    protected function getMemoryLimit(): float
    {
        $limit = ini_get('memory_limit');
        if ($limit === '-1') return -1;
        return (float) $limit;
    }

    /**
     * 获取磁盘使用
     */
    protected function getDiskUsage(): array
    {
        $path = storage_path();
        $free = disk_free_space($path);
        $total = disk_total_space($path);
        $used = $total - $free;
        $percent = $total > 0 ? round(($used / $total) * 100, 2) : 0;

        return [
            'percent' => $percent,
            'free_gb' => round($free / 1024 / 1024 / 1024, 2),
            'total_gb' => round($total / 1024 / 1024 / 1024, 2),
            'used_gb' => round($used / 1024 / 1024 / 1024, 2),
            'path' => $path,
        ];
    }

    /**
     * 获取数据库连接数
     */
    protected function getDbConnections(): int
    {
        try {
            $result = DB::select("SHOW STATUS LIKE 'Threads_connected'");
            return (int) ($result[0]->Value ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * 获取失败任务数
     */
    protected function getFailedJobsCount(): int
    {
        try {
            return DB::table('failed_jobs')->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * 获取熔断器状态
     */
    protected function getCircuitBreakerStates(): array
    {
        try {
            $breaker = app(\App\Services\CircuitBreakerService::class);
            return $breaker->getAllStates();
        } catch (\Throwable) {
            return [];
        }
    }
}
