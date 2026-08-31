<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Redis 缓存预热服务 (M2-23)
 *
 * 在系统低峰期预加载热点数据到 Redis，减少数据库突发读取压力。
 * 支持多数据源、分批加载、TTL 控制。
 */
class CacheWarmupService
{
    const PROGRESS_CACHE_KEY = 'cache_warmup:progress';
    const STATUS_CACHE_KEY = 'cache_warmup:status';
    const LAST_RUN_KEY = 'cache_warmup:last_run';

    protected array $config;

    public function __construct()
    {
        $this->config = config('db-read-write.cache_warmup');
    }

    /**
     * 执行全量预热
     *
     * @param string|null $source 指定数据源（null=全部）
     * @return array
     */
    public function warmup(?string $source = null): array
    {
        if (!$this->config['enabled']) {
            return ['success' => false, 'message' => __('app.common.cache_warmup_not_enabled')];
        }

        $startTime = microtime(true);
        $results = [];
        $totalLoaded = 0;
        $sources = $this->config['sources'] ?? [];

        if ($source && isset($sources[$source])) {
            $sources = [$source => $sources[$source]];
        }

        $this->setStatus('running');

        foreach ($sources as $name => $sourceConfig) {
            try {
                $loaded = $this->warmupSource($name, $sourceConfig);
                $results[$name] = ['success' => true, 'loaded' => $loaded];
                $totalLoaded += $loaded;
            } catch (\Throwable $e) {
                $results[$name] = ['success' => false, 'error' => $e->getMessage()];
                Log::error("Cache warmup failed for {$name}", ['error' => $e->getMessage()]);
            }
        }

        $elapsed = round(microtime(true) - $startTime, 2);
        $this->setStatus('completed');
        Cache::put(self::LAST_RUN_KEY, [
            'completed_at' => now()->toIso8601String(),
            'elapsed_seconds' => $elapsed,
            'total_loaded' => $totalLoaded,
            'results' => $results,
        ], 86400 * 7);

        return [
            'success' => true,
            'total_loaded' => $totalLoaded,
            'elapsed_seconds' => $elapsed,
            'results' => $results,
        ];
    }

    /**
     * 预热单个数据源
     */
    protected function warmupSource(string $name, array $config): int
    {
        $query = DB::select($config['query']);
        $keyPrefix = $config['key_prefix'] ?? "{$name}:";
        $ttl = $config['ttl'] ?? 3600;
        $batchSize = $config['batch_size'] ?? 100;
        $loaded = 0;
        $batch = [];

        foreach ($query as $row) {
            $row = (array) $row;
            $id = $row['id'] ?? $row['key'] ?? uniqid();
            $cacheKey = $keyPrefix . $id;

            $batch[$cacheKey] = json_encode($row);
            $loaded++;

            if (count($batch) >= $batchSize) {
                $this->batchSet($batch, $ttl);
                $batch = [];
            }
        }

        // 剩余批次
        if (!empty($batch)) {
            $this->batchSet($batch, $ttl);
        }

        return $loaded;
    }

    /**
     * 批量写入 Redis
     */
    protected function batchSet(array $items, int $ttl): void
    {
        // 使用 pipeline 批量写入
        Redis::pipeline(function ($pipe) use ($items, $ttl) {
            foreach ($items as $key => $value) {
                $pipe->setex($key, $ttl, $value);
            }
        });
    }

    /**
     * 获取预热状态
     */
    public function getStatus(): array
    {
        $status = Cache::get(self::STATUS_CACHE_KEY, 'idle');
        $lastRun = Cache::get(self::LAST_RUN_KEY);
        $progress = Cache::get(self::PROGRESS_CACHE_KEY, []);

        return [
            'status' => $status,
            'enabled' => $this->config['enabled'] ?? false,
            'is_running' => $status === 'running',
            'last_run' => $lastRun,
            'progress' => $progress,
            'sources' => array_keys($this->config['sources'] ?? []),
            'schedule' => $this->config['schedule'] ?? '0 3 * * *',
        ];
    }

    /**
     * 设置运行状态
     */
    protected function setStatus(string $status): void
    {
        Cache::put(self::STATUS_CACHE_KEY, $status, 300);
    }

    /**
     * 获取已缓存的数据量统计
     */
    public function getStats(): array
    {
        $stats = [];
        foreach ($this->config['sources'] ?? [] as $name => $sourceConfig) {
            $keyPrefix = $sourceConfig['key_prefix'] ?? "{$name}:";
            try {
                $keys = Redis::keys($keyPrefix . '*');
                $stats[$name] = count($keys);
            } catch (\Throwable $e) {
                $stats[$name] = -1;
            }
        }
        return $stats;
    }
}
