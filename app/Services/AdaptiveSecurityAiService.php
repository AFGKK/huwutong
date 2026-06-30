<?php

namespace App\Services;

use App\Models\ApiDocEndpoint;
use App\Models\License;
use App\Models\Device;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * AI 自适应安全阈值服务 (M2-45)
 *
 * 根据实时威胁情报、攻击频率、系统负载等动态调整：
 * - 限流 QPS 阈值
 * - 设备信任分阈值
 * - 熔断器参数
 * - 黑名单自动判定阈值
 */
class AdaptiveSecurityAiService
{
    public function __construct(protected LlmService $llm) {}

    /**
     * 获取当前推荐的安全配置
     */
    public function getRecommendedConfig(int $tenantId, array $options = []): array
    {
        $cacheKey = "adaptive_security:{$tenantId}";
        $ttl = $options['cache_ttl'] ?? 300; // 5分钟缓存

        return Cache::remember($cacheKey, $ttl, function () use ($tenantId, $options) {
            $context = $this->collectSecurityContext($tenantId);
            return $this->analyzeWithLlm($context, $options);
        });
    }

    /**
     * 收集安全上下文
     */
    protected function collectSecurityContext(int $tenantId): array
    {
        // 近24小时激活统计
        $recentActivations = License::byTenant($tenantId)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        $failedActivations = License::byTenant($tenantId)
            ->where('status', 'failed')
            ->where('updated_at', '>=', now()->subDay())
            ->count();

        // 设备统计
        $totalDevices = Device::byTenant($tenantId)->count();
        $blacklistedDevices = Device::byTenant($tenantId)->where('is_blacklisted', true)->count();
        $virtualDevices = Device::byTenant($tenantId)->where('is_virtual', true)->count();

        // 平均信任分
        $avgTrustScore = Device::byTenant($tenantId)->avg('trust_score') ?? 70;

        // 攻击指标（从异常检测表获取）
        $recentAnomalies = 0;
        try {
            $recentAnomalies = \App\Models\SecurityEvent::byTenant($tenantId)
                ->where('created_at', '>=', now()->subHour())
                ->where('severity', '>=', 'high')
                ->count();
        } catch (\Throwable $e) { /* ignore if table not exists */ }

        return [
            'tenant_id' => $tenantId,
            'period_hours' => 24,
            'recent_activations' => $recentActivations,
            'failed_activations' => $failedActivations,
            'failure_rate' => $recentActivations > 0
                ? round($failedActivations / $recentActivations * 100, 2)
                : 0,
            'total_devices' => $totalDevices,
            'blacklist_rate' => $totalDevices > 0
                ? round($blacklistedDevices / $totalDevices * 100, 2)
                : 0,
            'virtual_device_rate' => $totalDevices > 0
                ? round($virtualDevices / $totalDevices * 100, 2)
                : 0,
            'avg_trust_score' => round($avgTrustScore, 1),
            'recent_high_severity_events' => $recentAnomalies,
            'current_config' => [
                'rate_limit_per_minute' => (int) config('security.rate_limit_per_minute', 60),
                'trust_score_threshold' => (int) config('security.trust_score_threshold', 50),
                'circuit_breaker_failure_threshold' => (int) config('security.circuit_breaker_failure_threshold', 5),
                'circuit_breaker_recovery_time' => (int) config('security.circuit_breaker_recovery_time', 30),
            ],
        ];
    }

    /**
     * LLM 分析
     */
    protected function analyzeWithLlm(array $context, array $options): array
    {
        $prompt = json_encode([
            'task' => '根据安全上下文，推荐自适应安全配置参数',
            'context' => $context,
            'requested_output' => [
                'recommended_config' => [
                    'rate_limit_per_minute' => '建议的每分钟限流QPS数',
                    'trust_score_threshold' => '建议的设备信任分阈值(0-100)',
                    'circuit_breaker_failure_threshold' => '熔断器失败次数阈值',
                    'circuit_breaker_recovery_time' => '熔断恢复时间(秒)',
                    'auto_blacklist_score' => '自动加入黑名单的阈值(0-100)',
                ],
                'changes' => '与当前配置的差异说明',
                'risk_level' => '当前安全风险等级: low/medium/high/critical',
                'reasoning' => '推荐理由',
                'alerts' => '需要关注的告警列表',
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        try {
            $result = $this->llm->chat([
                ['role' => 'system', 'content' => '你是安全运维专家，根据实时威胁情报动态调整安全阈值。返回JSON。'],
                ['role' => 'user', 'content' => $prompt],
            ], [
                'temperature' => 0.2,
                'response_format' => ['type' => 'json_object'],
            ], 'adaptive-security');

            $content = $result['content'] ?? '{}';
            $parsed = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $parsed;
            }
        } catch (\Throwable $e) {
            Log::warning('AdaptiveSecurityAi: LLM failed', ['error' => $e->getMessage()]);
        }

        return $this->getFallbackConfig($context);
    }

    /**
     * 兜底配置
     */
    protected function getFallbackConfig(array $context): array
    {
        $failureRate = $context['failure_rate'] ?? 0;
        $anomalies = $context['recent_high_severity_events'] ?? 0;

        $riskLevel = 'low';
        if ($failureRate > 20 || $anomalies > 10) $riskLevel = 'critical';
        elseif ($failureRate > 10 || $anomalies > 5) $riskLevel = 'high';
        elseif ($failureRate > 5 || $anomalies > 2) $riskLevel = 'medium';

        $rateLimit = match(true) {
            $riskLevel === 'critical' => 20,
            $riskLevel === 'high' => 40,
            $riskLevel === 'medium' => 60,
            default => 100,
        };

        return [
            'recommended_config' => [
                'rate_limit_per_minute' => $rateLimit,
                'trust_score_threshold' => $riskLevel === 'critical' ? 80 : ($riskLevel === 'high' ? 70 : 50),
                'circuit_breaker_failure_threshold' => $riskLevel === 'critical' ? 3 : 5,
                'circuit_breaker_recovery_time' => $riskLevel === 'critical' ? 60 : 30,
                'auto_blacklist_score' => $riskLevel === 'critical' ? 40 : 60,
            ],
            'changes' => "基于当前风险等级({$riskLevel})自动调整",
            'risk_level' => $riskLevel,
            'reasoning' => "失败率{$failureRate}%, 近期严重事件{$anomalies}次",
            'alerts' => $riskLevel !== 'low' ? ['建议关注安全事件，考虑启用MFA增强验证'] : [],
        ];
    }

    /**
     * 清除缓存
     */
    public function clearCache(int $tenantId): void
    {
        Cache::forget("adaptive_security:{$tenantId}");
    }

    /**
     * 获取配置建议快照历史
     */
    public function getHistory(int $tenantId, int $limit = 20): array
    {
        try {
            return \App\Models\SecurityConfigSnapshot::byTenant($tenantId)
                ->where('type', 'adaptive')
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get()
                ->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
