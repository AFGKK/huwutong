<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Exception;

/**
 * Redis 高可用服务
 *
 * 支持 Redis Sentinel / Cluster 模式下的：
 * - 健康检查与延迟监控
 * - 自动故障转移检测
 * - 哨兵状态查询
 * - 连接池管理
 * - 熔断保护
 * - 降级回退
 */
class RedisHaService
{
    /**
     * 熔断器状态
     */
    protected array $circuitBreaker = [
        'open' => false,
        'failure_count' => 0,
        'last_failure_at' => null,
        'recovery_at' => null,
    ];

    /**
     * 当前连接状态
     */
    protected array $connectionStatus = [
        'connected' => false,
        'master' => null,
        'slaves' => [],
        'last_ping_at' => null,
        'latency_ms' => 0,
    ];

    /**
     * 获取当前 Redis 运行模式
     */
    public function getMode(): string
    {
        return config('redis-ha.mode', 'sentinel');
    }

    /**
     * 检查 Redis 是否可用
     */
    public function isAvailable(): bool
    {
        if ($this->isCircuitBreakerOpen()) {
            return false;
        }

        try {
            Redis::connection()->ping();
            return true;
        } catch (Exception $e) {
            $this->recordFailure();
            return false;
        }
    }

    /**
     * 健康检查 — 完整检查
     *
     * @return array{ping: bool, latency_ms: float, memory_usage: array, connected_slaves: int, mode: string, master: ?string}
     */
    public function healthCheck(): array
    {
        $start = microtime(true);
        $ping = false;
        $latency = 0;
        $info = [];

        try {
            Redis::connection()->ping();
            $ping = true;
            $latency = (microtime(true) - $start) * 1000;

            /** @var array $infoRaw */
            $infoRaw = Redis::connection()->info();
            $info = is_array($infoRaw) ? $infoRaw : [];
        } catch (Exception $e) {
            $ping = false;
            $latency = -1;
            $this->recordFailure();
        }

        return [
            'ping' => $ping,
            'latency_ms' => round($latency, 2),
            'memory_usage' => $this->parseMemoryInfo($info),
            'connected_slaves' => (int) ($info['connected_slaves'] ?? 0),
            'mode' => $this->getMode(),
            'master' => $this->getMasterInfo($info),
            'uptime_in_seconds' => $info['uptime_in_seconds'] ?? 0,
            'total_connections_received' => $info['total_connections_received'] ?? 0,
            'total_commands_processed' => $info['total_commands_processed'] ?? 0,
            'keyspace_hit_ratio' => $this->calcHitRatio($info),
            'role' => $info['role'] ?? 'unknown',
        ];
    }

    /**
     * 查询 Sentinel 哨兵状态
     *
     * @return array{service: string, master: ?array, slaves: array, sentinels: array, failover: ?array}
     */
    public function sentinelStatus(): array
    {
        $service = config('redis-ha.sentinel.service', 'mymaster');

        if ($this->getMode() !== 'sentinel') {
            return [
                'service' => $service,
                'error' => '当前模式不是 Sentinel，请检查 REDIS_MODE 配置',
                'mode' => $this->getMode(),
            ];
        }

        try {
            // 通过 Redis Sentinel 连接查询状态
            $sentinel = Redis::connection('sentinel');

            // 查询 master
            $master = $sentinel->rawCommand('SENTINEL', 'master', $service);
            $slaves = $sentinel->rawCommand('SENTINEL', 'slaves', $service);
            $sentinels = $sentinel->rawCommand('SENTINEL', 'sentinels', $service);
            $failover = $sentinel->rawCommand('SENTINEL', 'failover', $service);

            return [
                'service' => $service,
                'master' => $this->parseSentinelMaster($master),
                'slaves' => $this->parseSentinelList($slaves),
                'sentinels' => $this->parseSentinelList($sentinels),
                'failover' => true,
            ];
        } catch (Exception $e) {
            Log::warning('Redis Sentinel 状态查询失败', [
                'service' => $service,
                'error' => $e->getMessage(),
            ]);

            return [
                'service' => $service,
                'error' => $e->getMessage(),
                'master' => null,
                'slaves' => [],
                'sentinels' => [],
                'failover' => null,
            ];
        }
    }

    /**
     * 执行降级检查并触发告警
     *
     * @return array{healthy: bool, issues: array, mode: string, failover_available: bool}
     */
    public function checkStatus(): array
    {
        $issues = [];
        $health = $this->healthCheck();

        if (!$health['ping']) {
            $issues[] = [
                'severity' => 'critical',
                'message' => 'Redis 无法连接',
                'component' => 'connectivity',
            ];
        }

        $latencyWarn = config('redis-ha.monitoring.latency_warn_threshold', 50);
        $latencyCritical = config('redis-ha.monitoring.latency_critical_threshold', 200);

        if ($health['latency_ms'] > $latencyCritical) {
            $issues[] = [
                'severity' => 'critical',
                'message' => "Redis 延迟过高: {$health['latency_ms']}ms",
                'component' => 'latency',
            ];
        } elseif ($health['latency_ms'] > $latencyWarn) {
            $issues[] = [
                'severity' => 'warning',
                'message' => "Redis 延迟较高: {$health['latency_ms']}ms",
                'component' => 'latency',
            ];
        }

        if (isset($health['memory_usage']['percent'])) {
            $memPercent = $health['memory_usage']['percent'];
            $memCritical = config('redis-ha.monitoring.memory_critical_percent', 95);
            $memWarn = config('redis-ha.monitoring.memory_warn_percent', 80);

            if ($memPercent >= $memCritical) {
                $issues[] = [
                    'severity' => 'critical',
                    'message' => "Redis 内存使用已达 {$memPercent}%（严重）",
                    'component' => 'memory',
                ];
            } elseif ($memPercent >= $memWarn) {
                $issues[] = [
                    'severity' => 'warning',
                    'message' => "Redis 内存使用已达 {$memPercent}%（警告）",
                    'component' => 'memory',
                ];
            }
        }

        if ($health['role'] === 'slave') {
            $issues[] = [
                'severity' => 'warning',
                'message' => '当前连接为从库，主库可能故障',
                'component' => 'replication',
            ];
        }

        $slaveWarn = config('redis-ha.monitoring.connected_slaves_warn', 1);
        if ($health['connected_slaves'] < $slaveWarn && $health['ping']) {
            $issues[] = [
                'severity' => 'warning',
                'message' => "从库数量不足: {$health['connected_slaves']}（需要 ≥ {$slaveWarn}）",
                'component' => 'replication',
            ];
        }

        $maxSeverity = 'ok';
        foreach ($issues as $issue) {
            if ($issue['severity'] === 'critical') {
                $maxSeverity = 'critical';
                break;
            }
            if ($issue['severity'] === 'warning') {
                $maxSeverity = 'warning';
            }
        }

        return [
            'healthy' => $health['ping'] && $maxSeverity !== 'critical',
            'issues' => $issues,
            'health' => $health,
            'mode' => $this->getMode(),
            'failover_available' => $this->isFailoverAvailable(),
            'overall_status' => $maxSeverity,
        ];
    }

    /**
     * 手动触发故障转移 (Sentinel 模式)
     */
    public function triggerFailover(): array
    {
        if ($this->getMode() !== 'sentinel') {
            return ['success' => false, 'error' => '仅 Sentinel 模式支持手动故障转移'];
        }

        $service = config('redis-ha.sentinel.service', 'mymaster');

        try {
            $sentinel = Redis::connection('sentinel');
            $sentinel->rawCommand('SENTINEL', 'failover', $service);

            Log::info('Redis Sentinel 故障转移已触发', ['service' => $service]);

            return [
                'success' => true,
                'message' => "Sentinel 故障转移已触发，服务: {$service}",
                'service' => $service,
            ];
        } catch (Exception $e) {
            Log::error('Redis Sentinel 故障转移失败', [
                'service' => $service,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'service' => $service,
            ];
        }
    }

    /**
     * 清除所有缓存（谨慎使用）
     */
    public function flushCache(): array
    {
        try {
            Redis::connection()->flushdb();

            return [
                'success' => true,
                'message' => 'Redis 缓存已清除',
                'database' => config('database.redis.default.database', 0),
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 获取 Redis 监控统计
     */
    public function getStats(): array
    {
        $health = $this->healthCheck();

        $sentinel = [];
        if ($this->getMode() === 'sentinel') {
            $sentinel = $this->sentinelStatus();
        }

        $stats = [
            'mode' => $this->getMode(),
            'ping' => $health['ping'],
            'latency_ms' => $health['latency_ms'],
            'memory_usage' => $health['memory_usage'],
            'uptime' => $health['uptime_in_seconds'],
            'connections' => $health['total_connections_received'],
            'commands' => $health['total_commands_processed'],
            'hit_ratio' => $health['keyspace_hit_ratio'],
            'role' => $health['role'],
            'connected_slaves' => $health['connected_slaves'],
            'master' => $health['master'],
            'sentinel' => $sentinel,
            'circuit_breaker' => $this->circuitBreaker,
        ];

        return $stats;
    }

    /**
     * 检查 Sentinel 是否正常
     */
    public function isSentinelHealthy(): bool
    {
        try {
            $sentinel = Redis::connection('sentinel');
            $sentinel->ping();
            return true;
        } catch (Exception) {
            return false;
        }
    }

    /**
     * 检查故障转移是否可用
     */
    public function isFailoverAvailable(): bool
    {
        if ($this->getMode() === 'sentinel') {
            return $this->isSentinelHealthy();
        }

        if ($this->getMode() === 'cluster') {
            return true; // Cluster 自动处理故障转移
        }

        return false;
    }

    // ─── 熔断器 ──────────────────────────────────────

    /**
     * 熔断器是否打开
     */
    public function isCircuitBreakerOpen(): bool
    {
        if (!$this->circuitBreaker['open']) {
            return false;
        }

        $recoveryAt = $this->circuitBreaker['recovery_at'];
        if ($recoveryAt && now()->timestamp >= $recoveryAt) {
            $this->resetCircuitBreaker();
            return false;
        }

        return true;
    }

    /**
     * 记录连接失败
     */
    public function recordFailure(): void
    {
        $threshold = config('redis-ha.failover.circuit_breaker_threshold', 10);
        $recoveryDelay = config('redis-ha.failover.circuit_breaker_recovery', 30);

        $this->circuitBreaker['failure_count']++;
        $this->circuitBreaker['last_failure_at'] = now()->toDateTimeString();

        if ($this->circuitBreaker['failure_count'] >= $threshold) {
            $this->circuitBreaker['open'] = true;
            $this->circuitBreaker['recovery_at'] = now()->addSeconds($recoveryDelay)->timestamp;

            Log::alert('Redis 熔断器已打开', [
                'failure_count' => $this->circuitBreaker['failure_count'],
                'recovery_at' => $this->circuitBreaker['recovery_at'],
            ]);
        }
    }

    /**
     * 重置熔断器
     */
    public function resetCircuitBreaker(): void
    {
        $this->circuitBreaker = [
            'open' => false,
            'failure_count' => 0,
            'last_failure_at' => null,
            'recovery_at' => null,
        ];

        Log::info('Redis 熔断器已重置');
    }

    // ─── 私有辅助方法 ────────────────────────────────

    /**
     * 解析 Redis INFO 内存信息
     */
    protected function parseMemoryInfo(array $info): array
    {
        $usedMemory = (int) ($info['used_memory'] ?? 0);
        $maxMemory = (int) ($info['maxmemory'] ?? 0);
        $usedMemoryHuman = $info['used_memory_human'] ?? '0B';
        $maxMemoryHuman = $info['maxmemory_human'] ?? '0B';

        return [
            'used_bytes' => $usedMemory,
            'max_bytes' => $maxMemory,
            'used' => $usedMemoryHuman,
            'max' => $maxMemoryHuman,
            'percent' => $maxMemory > 0 ? round(($usedMemory / $maxMemory) * 100, 2) : 0,
            'peak' => $info['used_memory_peak_human'] ?? '0B',
            'fragmentation' => $info['mem_fragmentation_ratio'] ?? 0,
        ];
    }

    /**
     * 获取主库信息
     */
    protected function getMasterInfo(array $info): ?array
    {
        if (!isset($info['master_host'])) {
            return null;
        }

        return [
            'host' => $info['master_host'],
            'port' => $info['master_port'] ?? 6379,
            'link_status' => $info['master_link_status'] ?? 'unknown',
        ];
    }

    /**
     * 计算 KEY 命中率
     */
    protected function calcHitRatio(array $info): float
    {
        $hits = (int) ($info['keyspace_hits'] ?? 0);
        $misses = (int) ($info['keyspace_misses'] ?? 0);
        $total = $hits + $misses;

        return $total > 0 ? round(($hits / $total) * 100, 2) : 0;
    }

    /**
     * 解析 Sentinel master 返回值
     */
    protected function parseSentinelMaster(array|bool $raw): ?array
    {
        if (!$raw || !is_array($raw)) {
            return null;
        }

        $result = [];
        for ($i = 0; $i < count($raw); $i += 2) {
            if (isset($raw[$i], $raw[$i + 1])) {
                $result[$raw[$i]] = $raw[$i + 1];
            }
        }

        return [
            'name' => $result['name'] ?? 'unknown',
            'host' => $result['ip'] ?? 'unknown',
            'port' => $result['port'] ?? 6379,
            'status' => $result['status'] ?? 'unknown',
            'role' => $result['role-reported'] ?? 'unknown',
        ];
    }

    /**
     * 解析 Sentinel 列表返回值
     */
    protected function parseSentinelList(array|bool $raw): array
    {
        if (!$raw || !is_array($raw)) {
            return [];
        }

        $items = [];
        foreach ($raw as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $parsed = [];
            for ($i = 0; $i < count($entry); $i += 2) {
                if (isset($entry[$i], $entry[$i + 1])) {
                    $parsed[$entry[$i]] = $entry[$i + 1];
                }
            }
            $items[] = [
                'host' => $parsed['ip'] ?? 'unknown',
                'port' => $parsed['port'] ?? 26379,
                'status' => $parsed['status'] ?? 'unknown',
                'last_hello' => $parsed['last-hello'] ?? 0,
            ];
        }

        return $items;
    }
}
