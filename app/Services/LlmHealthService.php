<?php

namespace App\Services;

use App\Models\LlmFallbackEvent;
use App\Models\LlmHealthCheck;
use App\Models\LlmProvider;
use App\Services\Llm\ClaudeAdapter;
use App\Services\Llm\DeepSeekAdapter;
use App\Services\Llm\OpenAiAdapter;
use Illuminate\Support\Facades\Log;

/**
 * LLM 健康检查服务
 *
 * 定时检查所有已配置的 LLM Provider 健康状态，
 * 记录延迟、可用性，并在检测到异常时触发降级事件。
 */
class LlmHealthService
{
    /**
     * 延迟阈值（毫秒），超过此值视为 degraded
     */
    protected const LATENCY_WARN_THRESHOLD_MS = 5000;

    /**
     * 延迟阈值（毫秒），超过此值视为 unhealthy
     */
    protected const LATENCY_FAIL_THRESHOLD_MS = 15000;

    /**
     * 对单个 Provider 执行健康检查
     */
    public function checkProvider(LlmProvider $provider): array
    {
        $adapter = $this->createAdapter($provider);

        if (!$adapter) {
            $this->recordCheck($provider, false, 0, '不支持的驱动: ' . $provider->driver);
            return ['is_healthy' => false, 'latency_ms' => 0, 'error' => '不支持的驱动'];
        }

        try {
            $result = $adapter->testConnection();

            $isHealthy = $result['success'] ?? false;
            $latencyMs = $result['latency_ms'] ?? 0;

            // 即使成功，但延迟过高也标记为不健康
            if ($isHealthy && $latencyMs > self::LATENCY_FAIL_THRESHOLD_MS) {
                $isHealthy = false;
                $error = "延迟过高: {$latencyMs}ms（阈值: " . self::LATENCY_FAIL_THRESHOLD_MS . "ms）";
            } elseif ($isHealthy && $latencyMs > self::LATENCY_WARN_THRESHOLD_MS) {
                $error = "延迟偏高: {$latencyMs}ms";
            } else {
                $error = $isHealthy ? null : ($result['message'] ?? '健康检查失败');
            }

            $this->recordCheck($provider, $isHealthy, $latencyMs, $error);

            return [
                'is_healthy' => $isHealthy,
                'latency_ms' => $latencyMs,
                'error' => $error,
            ];
        } catch (\Throwable $e) {
            $this->recordCheck($provider, false, 0, $e->getMessage());

            Log::error('LLM HealthCheck: provider check failed', [
                'provider' => $provider->slug,
                'error' => $e->getMessage(),
            ]);

            return [
                'is_healthy' => false,
                'latency_ms' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 对所有活跃 Provider 执行健康检查
     */
    public function checkAll(): array
    {
        $providers = LlmProvider::where('is_active', true)->get();
        $results = [];

        foreach ($providers as $provider) {
            $results[$provider->slug] = $this->checkProvider($provider);
        }

        // 如有 provider 失败，触发 fallback 事件
        $failedProviders = collect($results)->filter(fn($r) => !$r['is_healthy']);
        if ($failedProviders->isNotEmpty()) {
            $this->triggerFallbackEvent('health_fail', [
                'failed_providers' => $failedProviders->toArray(),
            ]);
        }

        // 全部不可用时触发告警
        $allDown = collect($results)->every(fn($r) => !$r['is_healthy']);
        if ($allDown && $providers->count() > 0) {
            $this->triggerFallbackEvent('all_down', [
                'total_providers' => $providers->count(),
                'details' => $results,
            ]);

            Log::critical('LLM HealthCheck: ALL providers are down!', ['results' => $results]);
        }

        return $results;
    }

    /**
     * 获取最近的健康检查状态（按 provider 分组）
     */
    public function getLatestHealthStatus(): array
    {
        $providers = LlmProvider::orderBy('sort_order')->get();
        $status = [];

        foreach ($providers as $provider) {
            $latestCheck = LlmHealthCheck::where('llm_provider_id', $provider->id)
                ->orderBy('checked_at', 'desc')
                ->first();

            // 最近 24 小时的健康率
            $recentChecks = LlmHealthCheck::where('llm_provider_id', $provider->id)
                ->where('checked_at', '>=', now()->subHours(24))
                ->get();

            $totalChecks = $recentChecks->count();
            $healthyChecks = $recentChecks->where('is_healthy', true)->count();
            $healthRate = $totalChecks > 0 ? round($healthyChecks / $totalChecks * 100, 1) : 100;

            // 平均延迟
            $avgLatency = $recentChecks->where('is_healthy', true)->avg('latency_ms');
            $avgLatency = $avgLatency ? round($avgLatency, 0) : null;

            $status[] = [
                'id' => $provider->id,
                'name' => $provider->name,
                'slug' => $provider->slug,
                'is_active' => $provider->is_active,
                'is_fallback' => $provider->is_fallback,
                'healthy' => $latestCheck ? $latestCheck->is_healthy : true,
                'latency_ms' => $latestCheck?->latency_ms,
                'last_check_at' => $latestCheck?->checked_at?->toIso8601String(),
                'last_error' => $latestCheck?->error_message,
                'health_rate_24h' => $healthRate,
                'avg_latency_24h' => $avgLatency,
                'total_checks_24h' => $totalChecks,
            ];
        }

        return $status;
    }

    /**
     * 获取最近 24 小时的降级/恢复事件
     */
    public function getRecentEvents(int $limit = 20): array
    {
        return LlmFallbackEvent::with('provider')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * 触发降级事件
     */
    public function triggerFallbackEvent(string $eventType, array $context = []): LlmFallbackEvent
    {
        $event = LlmFallbackEvent::create([
            'llm_provider_id' => $context['llm_provider_id'] ?? null,
            'event_type' => $eventType,
            'from_provider' => $context['from_provider'] ?? null,
            'to_provider' => $context['to_provider'] ?? null,
            'reason' => $context['reason'] ?? null,
            'context' => $context,
        ]);

        Log::info('LLM Fallback event recorded', [
            'type' => $eventType,
            'event_id' => $event->id,
        ]);

        return $event;
    }

    /**
     * 记录健康检查结果
     */
    protected function recordCheck(LlmProvider $provider, bool $isHealthy, int $latencyMs, ?string $error): void
    {
        LlmHealthCheck::create([
            'llm_provider_id' => $provider->id,
            'is_healthy' => $isHealthy,
            'latency_ms' => $latencyMs,
            'error_message' => $error,
            'checked_at' => now(),
        ]);
    }

    /**
     * 创建适配器实例
     */
    protected function createAdapter(LlmProvider $provider): ?\App\Contracts\LlmProviderContract
    {
        $adapterClass = match ($provider->driver) {
            'deepseek' => DeepSeekAdapter::class,
            'openai' => OpenAiAdapter::class,
            'claude', 'anthropic' => ClaudeAdapter::class,
            default => null,
        };

        if (!$adapterClass) {
            return null;
        }

        $adapter = app($adapterClass);
        $adapter->initialize($provider);

        return $adapter;
    }
}
