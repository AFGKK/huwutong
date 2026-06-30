<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * 数据库读写分离服务 (M2-23)
 *
 * 管理主库/从库连接分配，支持：
 * - 按比例分配读流量到从库
 * - 从库健康检查（延迟检测）
 * - 从库熔断降级（失败后自动切回主库）
 * - 强制写操作走主库
 * - 手动切换/恢复
 */
class DatabaseReadWriteService
{
    const CACHE_PREFIX = 'db_rw:';
    const HEALTH_CACHE_KEY = self::CACHE_PREFIX . 'replica_healthy';
    const LAG_CACHE_KEY = self::CACHE_PREFIX . 'replica_lag';

    protected array $config;

    public function __construct()
    {
        $this->config = config('db-read-write.read_write');
    }

    /**
     * 判断当前是否启用读写分离
     */
    public function isEnabled(): bool
    {
        return $this->config['enabled'] ?? false;
    }

    /**
     * 判断当前请求是否应该使用从库
     *
     * @param string $operation 操作类型
     * @return bool
     */
    public function shouldUseReplica(string $operation = 'read'): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        // 写操作强制走主库
        $forceMaster = $this->config['force_master_operations'] ?? [];
        if (in_array($operation, $forceMaster)) {
            return false;
        }

        // 从库不可用则走主库
        if (!$this->isReplicaHealthy()) {
            return false;
        }

        // 按比例分配
        $percent = (int) ($this->config['read_percent'] ?? 100);
        if ($percent <= 0) {
            return false;
        }
        if ($percent >= 100) {
            return true;
        }

        return random_int(1, 100) <= $percent;
    }

    /**
     * 获取当前数据库连接
     *
     * @param string $operation
     * @return string 连接名
     */
    public function getConnection(string $operation = 'read'): string
    {
        if ($this->shouldUseReplica($operation)) {
            return $this->config['replica_connection'] ?? 'mysql_replica';
        }
        return 'mysql';
    }

    /**
     * 执行读操作（自动选择从库/主库）
     *
     * @param callable $callback
     * @return mixed
     */
    public function read(callable $callback): mixed
    {
        $connection = $this->getConnection('read');
        DB::setDefaultConnection($connection);

        try {
            $result = $callback();
            DB::setDefaultConnection('mysql');
            return $result;
        } catch (\Throwable $e) {
            DB::setDefaultConnection('mysql');
            // 从库失败则重试主库
            if ($connection !== 'mysql') {
                $this->recordReplicaFailure();
                Log::warning("Replica read failed, falling back to master", [
                    'error' => $e->getMessage(),
                ]);
                return $callback();
            }
            throw $e;
        }
    }

    /**
     * 检查从库健康状态
     */
    public function isReplicaHealthy(): bool
    {
        if (!Cache::has(self::HEALTH_CACHE_KEY)) {
            $this->checkReplicaHealth();
        }
        return (bool) Cache::get(self::HEALTH_CACHE_KEY, false);
    }

    /**
     * 执行从库健康检查
     */
    public function checkReplicaHealth(): array
    {
        $result = [
            'healthy' => false,
            'lag_seconds' => null,
            'checked_at' => now()->toIso8601String(),
        ];

        try {
            // 从库简单查询检查
            $replicaConnection = $this->config['replica_connection'] ?? 'mysql_replica';

            // 检查从库是否可达
            DB::connection($replicaConnection)->select('SELECT 1 as alive');

            // 检查主从延迟 (MySQL Seconds_Behind_Master)
            $lagResult = DB::connection($replicaConnection)->select('SHOW SLAVE STATUS');
            $lag = null;
            if (!empty($lagResult)) {
                $lag = (int) ($lagResult[0]->Seconds_Behind_Master ?? -1);
                $result['lag_seconds'] = $lag;
                $result['healthy'] = $lag >= 0 && $lag <= ($this->config['replica_max_lag_seconds'] ?? 5);

                // 检查 Slave_IO_Running 和 Slave_SQL_Running
                $ioRunning = $lagResult[0]->Slave_IO_Running ?? 'No';
                $sqlRunning = $lagResult[0]->Slave_SQL_Running ?? 'No';
                $result['io_running'] = $ioRunning;
                $result['sql_running'] = $sqlRunning;
                if ($ioRunning !== 'Yes' || $sqlRunning !== 'Yes') {
                    $result['healthy'] = false;
                }
            } else {
                // 没有从库（单机模式），检查是否能正常查询
                $result['healthy'] = true;
            }

            Cache::put(self::HEALTH_CACHE_KEY, $result['healthy'], $this->config['health_check_interval'] ?? 60);
            Cache::put(self::LAG_CACHE_KEY, $result, $this->config['health_check_interval'] ?? 60);
        } catch (\Throwable $e) {
            $result['healthy'] = false;
            $result['error'] = $e->getMessage();
            Cache::put(self::HEALTH_CACHE_KEY, false, 30); // 失败后 30 秒重试

            Log::error("Replica health check failed", ['error' => $e->getMessage()]);
        }

        return $result;
    }

    /**
     * 记录从库失败（熔断计数）
     */
    protected function recordReplicaFailure(): void
    {
        $key = self::CACHE_PREFIX . 'failure_count';
        $count = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $count, $this->config['circuit_breaker_recovery_seconds'] ?? 30);

        $threshold = $this->config['circuit_breaker_threshold'] ?? 3;
        if ($count >= $threshold) {
            Cache::put(self::HEALTH_CACHE_KEY, false, $this->config['circuit_breaker_recovery_seconds'] ?? 30);
            Log::warning("Replica circuit breaker opened after {$count} failures");
        }
    }

    /**
     * 获取读写分离状态详情
     */
    public function getStatus(): array
    {
        $health = Cache::get(self::LAG_CACHE_KEY, []);
        $failureCount = (int) Cache::get(self::CACHE_PREFIX . 'failure_count', 0);

        return [
            'enabled' => $this->isEnabled(),
            'read_percent' => $this->config['read_percent'] ?? 100,
            'replica_healthy' => $this->isReplicaHealthy(),
            'replica_connection' => $this->config['replica_connection'] ?? 'mysql_replica',
            'failure_count' => $failureCount,
            'health' => $health,
            'config' => [
                'replica_max_lag' => $this->config['replica_max_lag_seconds'] ?? 5,
                'circuit_breaker_threshold' => $this->config['circuit_breaker_threshold'] ?? 3,
                'circuit_breaker_recovery' => $this->config['circuit_breaker_recovery_seconds'] ?? 30,
            ],
        ];
    }

    /**
     * 重置熔断器
     */
    public function resetCircuitBreaker(): void
    {
        Cache::forget(self::CACHE_PREFIX . 'failure_count');
        Cache::forget(self::HEALTH_CACHE_KEY);
        Cache::forget(self::LAG_CACHE_KEY);
    }

    /**
     * 获取当前默认连接
     */
    public function getDefaultConnection(): string
    {
        return DB::getDefaultConnection();
    }
}
