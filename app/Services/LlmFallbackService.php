<?php

namespace App\Services;

use App\Models\LlmProvider;
use App\Contracts\LlmProviderContract;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 大模型降级策略服务
 *
 * 降级链：Primary → Backup → Local Fallback
 * - 自动切换：当主 provider 连续 N 次失败时自动切换到备用
 * - 恢复检测：定期探测主 provider 是否恢复
 * - 熔断：连续失败触发熔断，避免雪崩
 * - 告警：降级/恢复时触发通知
 */
class LlmFallbackService
{
    /**
     * 熔断状态缓存键
     */
    protected const CIRCUIT_KEY = 'llm_circuit_%s';

    /**
     * 熔断配置
     */
    protected const FAILURE_THRESHOLD = 3;          // 连续 N 次失败触发熔断
    protected const HALF_OPEN_TIMEOUT = 30;          // 半开状态等待时间（秒）
    protected const RESET_TIMEOUT = 120;             // 完全熔断后自动重置时间（秒）

    /**
     * 获取当前可用的 Provider（按降级链自动选择）
     */
    public function getAvailableProvider(): ?LlmProvider
    {
        // 按优先级排序获取所有活跃 Provider
        $providers = LlmProvider::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        foreach ($providers as $provider) {
            if ($this->isCircuitOpen($provider)) {
                Log::info('LLMFallback: provider circuit open, skipping', [
                    'provider' => $provider->slug,
                ]);
                continue;
            }
            return $provider;
        }

        // 所有 Provider 都熔断，返回第一个尝试（熔断半开后允许重试）
        $first = $providers->first();
        if ($first && $this->isHalfOpenTimeoutExpired($first)) {
            $this->closeCircuit($first);
            return $first;
        }

        return null;
    }

    /**
     * 记录成功调用（关闭熔断器）
     */
    public function recordSuccess(LlmProvider $provider): void
    {
        $this->closeCircuit($provider);

        // 如果这个 Provider 之前是降级状态，记录恢复
        if ($this->getCurrentProvider() !== null && $this->getCurrentProvider()->id !== $provider->id) {
            Log::info('LLMFallback: primary provider recovered', [
                'provider' => $provider->slug,
            ]);
            $this->setCurrentProvider($provider);
        }
    }

    /**
     * 记录失败调用
     */
    public function recordFailure(LlmProvider $provider, string $error): void
    {
        $key = sprintf(self::CIRCUIT_KEY, $provider->id);
        $state = Cache::get($key, ['failures' => 0, 'status' => 'closed']);

        $state['failures']++;
        $state['last_error'] = $error;
        $state['last_failure_at'] = now()->toIso8601String();

        // 检查是否达到熔断阈值
        if ($state['failures'] >= self::FAILURE_THRESHOLD && $state['status'] === 'closed') {
            $state['status'] = 'open';
            $state['opened_at'] = now()->toIso8601String();
            $state['failures'] = 0;

            Log::warning('LLMFallback: circuit opened', [
                'provider' => $provider->slug,
                'error' => $error,
            ]);

            // 尝试切换到下一个可用 Provider
            $this->switchToNext($provider);
        }

        Cache::put($key, $state, now()->addMinutes(30));
    }

    /**
     * 检查 Provider 的熔断器是否打开
     */
    public function isCircuitOpen(LlmProvider $provider): bool
    {
        $key = sprintf(self::CIRCUIT_KEY, $provider->id);
        $state = Cache::get($key, ['status' => 'closed']);

        if ($state['status'] !== 'open') {
            return false;
        }

        // 检查超时，超时则进入半开状态
        $openedAt = $state['opened_at'] ?? now()->toIso8601String();
        $elapsed = now()->diffInSeconds(new \DateTime($openedAt));

        if ($elapsed >= self::RESET_TIMEOUT) {
            $state['status'] = 'half-open';
            Cache::put($key, $state, now()->addMinutes(30));
            return false; // 半开状态下允许尝试
        }

        return true;
    }

    /**
     * 检查半开超时是否已过期
     */
    protected function isHalfOpenTimeoutExpired(LlmProvider $provider): bool
    {
        $key = sprintf(self::CIRCUIT_KEY, $provider->id);
        $state = Cache::get($key, ['status' => 'closed']);

        if ($state['status'] !== 'half-open') {
            return true;
        }

        $openedAt = $state['opened_at'] ?? now()->toIso8601String();
        return now()->diffInSeconds(new \DateTime($openedAt)) >= self::HALF_OPEN_TIMEOUT;
    }

    /**
     * 关闭熔断器
     */
    protected function closeCircuit(LlmProvider $provider): void
    {
        $key = sprintf(self::CIRCUIT_KEY, $provider->id);
        Cache::forget($key);
    }

    /**
     * 切换到下一个备用 Provider
     */
    protected function switchToNext(LlmProvider $failedProvider): void
    {
        $next = LlmProvider::where('is_active', true)
            ->where('id', '!=', $failedProvider->id)
            ->where('is_fallback', true)
            ->orderBy('sort_order')
            ->first();

        if ($next) {
            $this->setCurrentProvider($next);
            Log::info('LLMFallback: switched to backup', [
                'from' => $failedProvider->slug,
                'to' => $next->slug,
            ]);
        } else {
            // 没有备用 Provider，找到第一个非熔断的活跃 Provider
            $providers = LlmProvider::where('is_active', true)
                ->where('id', '!=', $failedProvider->id)
                ->orderBy('sort_order')
                ->get();

            foreach ($providers as $p) {
                if (!$this->isCircuitOpen($p)) {
                    $this->setCurrentProvider($p);
                    Log::info('LLMFallback: auto switched provider', [
                        'from' => $failedProvider->slug,
                        'to' => $p->slug,
                    ]);
                    return;
                }
            }

            Log::error('LLMFallback: no available provider for switch');
        }
    }

    /**
     * 获取当前使用的 Provider
     */
    public function getCurrentProvider(): ?LlmProvider
    {
        $providerId = Cache::get('llm_current_provider_id');
        if ($providerId) {
            return LlmProvider::find($providerId);
        }
        return LlmProvider::getActive();
    }

    /**
     * 设置当前使用的 Provider
     */
    public function setCurrentProvider(LlmProvider $provider): void
    {
        Cache::put('llm_current_provider_id', $provider->id, now()->addDay());
    }

    /**
     * 重置所有熔断器
     */
    public function resetAllCircuits(): void
    {
        $providers = LlmProvider::all();
        foreach ($providers as $provider) {
            $this->closeCircuit($provider);
        }
        Cache::forget('llm_current_provider_id');
        Log::info('LLMFallback: all circuits reset');
    }

    /**
     * 获取熔断状态概览
     */
    public function getCircuitStatus(): array
    {
        $providers = LlmProvider::orderBy('sort_order')->get();
        $status = [];

        foreach ($providers as $provider) {
            $key = sprintf(self::CIRCUIT_KEY, $provider->id);
            $state = Cache::get($key, ['failures' => 0, 'status' => 'closed']);

            $status[] = [
                'id' => $provider->id,
                'name' => $provider->name,
                'slug' => $provider->slug,
                'is_active' => $provider->is_active,
                'is_fallback' => $provider->is_fallback,
                'circuit_status' => $state['status'],
                'consecutive_failures' => $state['failures'],
                'last_error' => $state['last_error'] ?? null,
                'last_failure_at' => $state['last_failure_at'] ?? null,
                'is_current' => $this->getCurrentProvider()?->id === $provider->id,
            ];
        }

        return $status;
    }
}
