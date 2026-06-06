<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 服务熔断降级服务
 *
 * 支持：
 * - Redis 不可用 → 降级到 DB
 * - DB 不可用 → 返回熔断响应
 * - 全部不可用 → 紧急熔断
 *
 * 使用 半开/闭合/断开 三态熔断模式 + 自动恢复
 */
class CircuitBreakerService
{
    const CACHE_PREFIX = 'circuit_breaker:';

    /**
     * 状态常量
     */
    const STATE_CLOSED = 'closed';       // 闭合—正常运作
    const STATE_OPEN = 'open';           // 断开—熔断中
    const STATE_HALF_OPEN = 'half_open'; // 半开—试探恢复

    /**
     * 默认熔断阈值：连续失败次数
     */
    const DEFAULT_FAILURE_THRESHOLD = 5;

    /**
     * 默认恢复等待时间（秒）
     */
    const DEFAULT_RESET_TIMEOUT = 30;

    /**
     * 半开状态允许的试探请求数
     */
    const HALF_OPEN_MAX_REQUESTS = 3;

    /**
     * 检查服务是否可用
     *
     * @param string $service 服务名称（redis / db / all）
     * @return bool
     */
    public function isAvailable(string $service = 'all'): bool
    {
        if ($service === 'all') {
            return $this->isRedisAvailable() && $this->isDatabaseAvailable();
        }

        return match ($service) {
            'redis' => $this->isRedisAvailable(),
            'db', 'database' => $this->isDatabaseAvailable(),
            default => $this->checkCustomService($service),
        };
    }

    /**
     * 检查 Redis 是否可用
     */
    public function isRedisAvailable(): bool
    {
        try {
            Cache::store('redis')->set('health:ping', 'pong', 5);
            $val = Cache::store('redis')->get('health:ping');
            return $val === 'pong';
        } catch (\Throwable $e) {
            Log::warning('Redis 不可用', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * 检查数据库是否可用
     */
    public function isDatabaseAvailable(): bool
    {
        try {
            DB::select('SELECT 1');
            return true;
        } catch (\Throwable $e) {
            Log::warning('数据库不可用', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * 检查自定义服务的熔断状态
     */
    public function checkCustomService(string $service): bool
    {
        $state = $this->getState($service);

        return match ($state) {
            self::STATE_CLOSED => true,
            self::STATE_OPEN => false,
            self::STATE_HALF_OPEN => $this->allowHalfOpenRequest($service),
            default => true,
        };
    }

    /**
     * 记录成功（重置熔断计数器）
     */
    public function recordSuccess(string $service): void
    {
        $state = $this->getState($service);

        if ($state === self::STATE_HALF_OPEN) {
            // 半开状态下的成功—恢复为闭合
            $this->setState($service, self::STATE_CLOSED);
            $this->resetFailureCount($service);
            $this->resetHalfOpenCount($service);
            Log::info("服务 {$service} 已恢复", ['state' => self::STATE_CLOSED]);
        } else {
            // 闭合状态记录成功—重置失败计数
            $this->resetFailureCount($service);
        }
    }

    /**
     * 记录失败（递增失败计数，达到阈值触发熔断）
     */
    public function recordFailure(string $service): void
    {
        $state = $this->getState($service);

        if ($state === self::STATE_HALF_OPEN) {
            // 半开状态下的失败—立即回到断开
            $this->setState($service, self::STATE_OPEN);
            $this->resetHalfOpenCount($service);
            Log::warning("服务 {$service} 半开探测失败，恢复熔断", ['state' => self::STATE_OPEN]);
            return;
        }

        $count = $this->incrementFailureCount($service);
        $threshold = $this->getFailureThreshold($service);

        if ($count >= $threshold) {
            $this->setState($service, self::STATE_OPEN);
            Log::error("服务 {$service} 已熔断", [
                'failures' => $count,
                'threshold' => $threshold,
                'state' => self::STATE_OPEN,
                'reset_timeout' => self::DEFAULT_RESET_TIMEOUT,
            ]);
        }
    }

    /**
     * 尝试进入半开状态（熔断恢复探测）
     */
    public function attemptReset(string $service): bool
    {
        $state = $this->getState($service);

        if ($state !== self::STATE_OPEN) {
            return true;
        }

        // 检查是否过了恢复时间
        $openedAt = $this->getStateChangedAt($service);
        if ($openedAt && (time() - $openedAt) >= self::DEFAULT_RESET_TIMEOUT) {
            $this->setState($service, self::STATE_HALF_OPEN);
            $this->resetHalfOpenCount($service);
            Log::info("服务 {$service} 尝试半开恢复", ['state' => self::STATE_HALF_OPEN]);
            return true;
        }

        return false;
    }

    /**
     * 获取当前熔断状态
     */
    public function getState(string $service): string
    {
        return Cache::get(self::CACHE_PREFIX . "state:{$service}", self::STATE_CLOSED);
    }

    /**
     * 设置熔断状态
     */
    protected function setState(string $service, string $state): void
    {
        Cache::put(self::CACHE_PREFIX . "state:{$service}", $state, 300);
        Cache::put(self::CACHE_PREFIX . "state_changed:{$service}", time(), 300);
    }

    /**
     * 获取状态变更时间
     */
    protected function getStateChangedAt(string $service): ?int
    {
        $val = Cache::get(self::CACHE_PREFIX . "state_changed:{$service}");
        return $val !== null ? (int) $val : null;
    }

    /**
     * 获取失败计数
     */
    public function getFailureCount(string $service): int
    {
        return (int) Cache::get(self::CACHE_PREFIX . "failures:{$service}", 0);
    }

    /**
     * 递增失败计数
     */
    protected function incrementFailureCount(string $service): int
    {
        $count = Cache::increment(self::CACHE_PREFIX . "failures:{$service}", 1)
            ?? $this->getFailureCount($service) + 1;
        // 设置 TTL 防止永久累积
        Cache::put(self::CACHE_PREFIX . "failures:{$service}", $count, 300);
        return $count;
    }

    /**
     * 重置失败计数
     */
    protected function resetFailureCount(string $service): void
    {
        Cache::forget(self::CACHE_PREFIX . "failures:{$service}");
    }

    /**
     * 获取失败阈值
     */
    protected function getFailureThreshold(string $service): int
    {
        // 不同服务的阈值可配置
        return match ($service) {
            'redis' => 3,
            'db', 'database' => 5,
            default => self::DEFAULT_FAILURE_THRESHOLD,
        };
    }

    /**
     * 半开状态是否允许请求通过
     */
    protected function allowHalfOpenRequest(string $service): bool
    {
        $count = (int) Cache::get(self::CACHE_PREFIX . "half_open_count:{$service}", 0);
        return $count < self::HALF_OPEN_MAX_REQUESTS;
    }

    /**
     * 记录半开请求
     */
    public function recordHalfOpenRequest(string $service): int
    {
        $count = Cache::increment(self::CACHE_PREFIX . "half_open_count:{$service}", 1) ?? 1;
        Cache::put(self::CACHE_PREFIX . "half_open_count:{$service}", $count, 60);
        return $count;
    }

    /**
     * 重置半开计数
     */
    protected function resetHalfOpenCount(string $service): void
    {
        Cache::forget(self::CACHE_PREFIX . "half_open_count:{$service}");
    }

    /**
     * 一键重置所有熔断状态
     */
    public function resetAll(): int
    {
        $count = 10; // 常见服务数量近似值
        foreach (['redis', 'db', 'license', 'webhook', 'sso', 'feature_flag'] as $svc) {
            $this->setState($svc, self::STATE_CLOSED);
            $this->resetFailureCount($svc);
            $this->resetHalfOpenCount($svc);
        }
        return $count;
    }

    /**
     * 获取所有服务的熔断状态（用于健康检查和监控）
     */
    public function getAllStates(): array
    {
        $services = ['redis', 'db', 'license', 'webhook', 'sso', 'feature_flag'];
        $result = [];

        foreach ($services as $svc) {
            $state = $this->getState($svc);
            $result[$svc] = [
                'state' => $state,
                'failures' => $this->getFailureCount($svc),
                'available' => $state === self::STATE_CLOSED,
            ];
        }

        return $result;
    }
}
