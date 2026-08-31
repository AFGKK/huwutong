<?php

namespace App\Services;

use App\Models\LlmProvider;
use App\Contracts\LlmProviderContract;
use App\Models\LlmFallbackEvent;
use App\Models\LlmHealthCheck;
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
    protected ?LlmHealthService $healthService = null;

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

    public function __construct()
    {
        try {
            $this->healthService = app(LlmHealthService::class);
        } catch (\Throwable) {
            // 允许在容器未准备好时降级运行
        }
    }

    /**
     * 获取当前可用的 Provider（按降级链自动选择）
     *
     * @param  LlmProvider|null  $exclude  排除的 Provider（用于单次请求失败后立即切换）
     */
    public function getAvailableProvider(?LlmProvider $exclude = null): ?LlmProvider
    {
        $preferredSlug = app(LlmRoutingService::class)->defaultProviderSlug();

        // 站点默认 Provider 优先，其余按 sort_order
        $providers = LlmProvider::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->sortBy(fn (LlmProvider $p) => $p->slug === $preferredSlug ? 0 : $p->sort_order + 1)
            ->values();

        foreach ($providers as $provider) {
            if ($exclude && $provider->id === $exclude->id) {
                continue;
            }
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
        $wasOpen = $this->isCircuitOpen($provider);
        $this->closeCircuit($provider);

        // 如果之前是熔断状态，记录恢复事件
        if ($wasOpen) {
            $this->recordEvent('circuit_closed', $provider, [
                'reason' => 'Provider 恢复正常',
                'from_provider' => $provider->slug,
            ]);
            Log::info('LLMFallback: circuit closed - provider recovered', [
                'provider' => $provider->slug,
            ]);
        }

        // 如果这个 Provider 之前是降级状态，记录切换恢复
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

            // 记录降级事件
            $this->recordEvent('circuit_opened', $provider, [
                'reason' => "连续失败 {$state['failures']} 次: {$error}",
                'from_provider' => $provider->slug,
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
            $this->recordEvent('provider_switch', $failedProvider, [
                'from_provider' => $failedProvider->slug,
                'to_provider' => $next->slug,
                'reason' => "{$failedProvider->slug} 熔断，切换到备用 {$next->slug}",
            ]);
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
                    $this->recordEvent('provider_switch', $p, [
                        'from_provider' => $failedProvider->slug,
                        'to_provider' => $p->slug,
                        'reason' => "{$failedProvider->slug} 熔断，自动切换到 {$p->slug}",
                    ]);
                    Log::info('LLMFallback: auto switched provider', [
                        'from' => $failedProvider->slug,
                        'to' => $p->slug,
                    ]);
                    return;
                }
            }

            // 所有 provider 都不可用
            $this->recordEvent('all_down', $failedProvider, [
                'reason' => '所有 Provider 均不可用，包括备用',
            ]);
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
     * 记录降级事件
     */
    protected function recordEvent(string $eventType, ?LlmProvider $provider, array $context = []): void
    {
        try {
            if ($this->healthService) {
                $context['llm_provider_id'] = $provider?->id;
                $this->healthService->triggerFallbackEvent($eventType, $context);
            }
        } catch (\Throwable $e) {
            Log::warning('LLMFallback: failed to record event', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 获取熔断状态概览（增强：含健康数据）
     */
    public function getCircuitStatus(): array
    {
        $providers = LlmProvider::orderBy('sort_order')->get();
        $status = [];

        foreach ($providers as $provider) {
            $key = sprintf(self::CIRCUIT_KEY, $provider->id);
            $state = Cache::get($key, ['failures' => 0, 'status' => 'closed']);

            // 获取健康检查数据
            $latestCheck = LlmHealthCheck::where('llm_provider_id', $provider->id)
                ->orderBy('checked_at', 'desc')
                ->first();

            $recentChecks = LlmHealthCheck::where('llm_provider_id', $provider->id)
                ->where('checked_at', '>=', now()->subHours(24))
                ->get();

            $totalChecks = $recentChecks->count();
            $healthyChecks = $recentChecks->where('is_healthy', true)->count();
            $healthRate = $totalChecks > 0 ? round($healthyChecks / $totalChecks * 100, 1) : 100;
            $avgLatency = $recentChecks->where('is_healthy', true)->avg('latency_ms');
            $avgLatency = $avgLatency ? round($avgLatency, 0) : null;

            $status[] = [
                'id' => $provider->id,
                'name' => $provider->name,
                'slug' => $provider->slug,
                'driver' => $provider->driver,
                'is_active' => $provider->is_active,
                'is_fallback' => $provider->is_fallback,
                'circuit_status' => $state['status'],
                'consecutive_failures' => $state['failures'],
                'last_error' => $state['last_error'] ?? null,
                'last_failure_at' => $state['last_failure_at'] ?? null,
                'is_current' => $this->getCurrentProvider()?->id === $provider->id,
                // 健康数据
                'healthy' => $latestCheck ? $latestCheck->is_healthy : true,
                'latency_ms' => $latestCheck?->latency_ms,
                'last_check_at' => $latestCheck?->checked_at?->toIso8601String(),
                'health_rate_24h' => $healthRate,
                'avg_latency_24h' => $avgLatency,
                'total_checks_24h' => $totalChecks,
            ];
        }

        // 统计概览
        $openCircuits = collect($status)->where('circuit_status', 'open')->count();
        $unhealthy = collect($status)->where('healthy', false)->count();

        return [
            'fallback_active' => $openCircuits > 0,
            'fallback_strategy' => $openCircuits > 0 ? 'active' : 'none',
            'fallback_provider' => $this->getCurrentProvider()?->name ?? '-',
            'total_providers' => count($status),
            'open_circuits' => $openCircuits,
            'unhealthy_providers' => $unhealthy,
            'consecutive_failures' => collect($status)->sum('consecutive_failures'),
            'triggers' => $this->getRecentEvents(10),
            'provider_health' => $status,
        ];
    }

    /**
     * 获取最近降级事件
     */
    protected function getRecentEvents(int $limit = 10): array
    {
        if (!$this->healthService) {
            return [];
        }

        try {
            return $this->healthService->getRecentEvents($limit);
        } catch (\Throwable) {
            return [];
        }
    }
}
