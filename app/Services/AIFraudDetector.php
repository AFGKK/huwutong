<?php

namespace App\Services;

use App\Models\License;
use App\Models\LicenseActivation;
use App\Models\Device;
use App\Models\AuditAnomaly;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * AI 风控授权服务 (M3-01)
 *
 * 异常激活识别 + 行为模型训练（基于规则+统计的轻量风控，
 * 可扩展为 ML 模型驱动）
 */
class AIFraudDetector
{
    const RISK_LEVELS = ['low', 'medium', 'high', 'critical'];

    // 风险权重
    const WEIGHTS = [
        'geo_velocity' => 30,
        'device_fingerprint' => 25,
        'activation_frequency' => 20,
        'time_anomaly' => 15,
        'ip_reputation' => 10,
    ];

    /**
     * 对 License 激活请求进行风控评估
     */
    public function evaluateActivation(License $license, array $context): array
    {
        $riskScore = 0;
        $factors = [];
        $signals = [];

        // 1. 地理位置速度检测
        $geoResult = $this->checkGeoVelocity($license, $context);
        $riskScore += $geoResult['score'];
        $factors['geo_velocity'] = $geoResult;
        $signals[] = $geoResult['signal'];

        // 2. 设备指纹异常检测
        $deviceResult = $this->checkDeviceAnomaly($license, $context);
        $riskScore += $deviceResult['score'];
        $factors['device_fingerprint'] = $deviceResult;
        $signals[] = $deviceResult['signal'];

        // 3. 激活频率检测
        $freqResult = $this->checkActivationFrequency($license);
        $riskScore += $freqResult['score'];
        $factors['activation_frequency'] = $freqResult;
        $signals[] = $freqResult['signal'];

        // 4. 时间异常检测
        $timeResult = $this->checkTimeAnomaly($context);
        $riskScore += $timeResult['score'];
        $factors['time_anomaly'] = $timeResult;
        $signals[] = $timeResult['signal'];

        // 5. IP 信誉检测
        $ipResult = $this->checkIpReputation($context['ip'] ?? '');
        $riskScore += $ipResult['score'];
        $factors['ip_reputation'] = $ipResult;
        $signals[] = $ipResult['signal'];

        // 计算综合风险等级
        $maxScore = array_sum(self::WEIGHTS);
        $normalizedScore = min(100, round(($riskScore / $maxScore) * 100));
        $riskLevel = $this->scoreToLevel($normalizedScore);

        $result = [
            'risk_score' => $normalizedScore,
            'risk_level' => $riskLevel,
            'factors' => $factors,
            'signals' => array_filter($signals),
            'action' => $this->determineAction($riskLevel),
            'evaluated_at' => now()->toIso8601String(),
        ];

        // 记录高级别风险
        if (in_array($riskLevel, ['high', 'critical'])) {
            $this->logAnomaly($license, $result, $context);
        }

        return $result;
    }

    /**
     * 批量评估（用于定时任务/后台扫描）
     */
    public function batchEvaluate(int $tenantId): array
    {
        $results = [];
        $licenses = License::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->limit(100)
            ->get();

        foreach ($licenses as $license) {
            $lastActivation = LicenseActivation::where('license_id', $license->id)
                ->latest()->first();

            if (!$lastActivation) continue;

            $context = [
                'ip' => $lastActivation->ip_address ?? '',
                'country' => $lastActivation->country ?? '',
                'device_fingerprint' => $lastActivation->device_fingerprint ?? '',
                'timestamp' => $lastActivation->activated_at?->timestamp ?? time(),
            ];

            $results[$license->id] = $this->evaluateActivation($license, $context);
        }

        return $results;
    }

    /**
     * 地理位置速度检测：短时间跨地域激活
     */
    protected function checkGeoVelocity(License $license, array $context): array
    {
        $currentCountry = $context['country'] ?? '';
        if (!$currentCountry) {
            return ['score' => 0, 'weight' => 30, 'signal' => null, 'detail' => '无地域信息'];
        }

        // 获取最近激活记录
        $recentActivations = LicenseActivation::where('license_id', $license->id)
            ->where('activated_at', '>=', now()->subHours(24))
            ->orderByDesc('activated_at')
            ->limit(5)
            ->get();

        if ($recentActivations->isEmpty()) {
            return ['score' => 0, 'weight' => 30, 'signal' => null, 'detail' => '首次激活'];
        }

        $lastCountry = $recentActivations->first()->country ?? '';
        $lastTime = $recentActivations->first()->activated_at;

        if ($lastCountry && $lastCountry !== $currentCountry) {
            $hoursDiff = $lastTime ? $lastTime->diffInHours(now()) : 0;

            if ($hoursDiff < 1) {
                return ['score' => 28, 'weight' => 30, 'signal' => 'geo_velocity_high',
                    'detail' => "1小时内跨地域激活: {$lastCountry}→{$currentCountry}"];
            }
            if ($hoursDiff < 6) {
                return ['score' => 18, 'weight' => 30, 'signal' => 'geo_velocity_medium',
                    'detail' => "6小时内跨地域激活: {$lastCountry}→{$currentCountry}"];
            }
            return ['score' => 8, 'weight' => 30, 'signal' => 'geo_velocity_low',
                'detail' => "跨地域激活: {$lastCountry}→{$currentCountry}"];
        }

        return ['score' => 0, 'weight' => 30, 'signal' => null, 'detail' => '地域一致'];
    }

    /**
     * 设备指纹异常检测
     */
    protected function checkDeviceAnomaly(License $license, array $context): array
    {
        $fingerprint = $context['device_fingerprint'] ?? '';
        if (!$fingerprint) {
            return ['score' => 5, 'weight' => 25, 'signal' => 'no_fingerprint', 'detail' => '无设备指纹'];
        }

        // 检查是否在黑名单中
        $blacklisted = Device::where('fingerprint', $fingerprint)
            ->where('is_blacklisted', true)->exists();
        if ($blacklisted) {
            return ['score' => 25, 'weight' => 25, 'signal' => 'blacklisted_device',
                'detail' => '设备在黑名单中'];
        }

        // 检查该License的设备数
        $deviceCount = Device::where('license_id', $license->id)->count();
        $newDeviceOnLicense = !Device::where('license_id', $license->id)
            ->where('fingerprint', $fingerprint)->exists();

        if ($newDeviceOnLicense && $deviceCount >= $license->max_devices) {
            return ['score' => 20, 'weight' => 25, 'signal' => 'device_limit_exceeded',
                'detail' => "设备数({$deviceCount})已达上限({$license->max_devices})"];
        }

        // 检查虚拟环境
        $isVirtual = Device::where('fingerprint', $fingerprint)
            ->where('is_virtual', true)->exists();
        if ($isVirtual) {
            return ['score' => 10, 'weight' => 25, 'signal' => 'virtual_device',
                'detail' => '虚拟环境设备'];
        }

        return ['score' => 0, 'weight' => 25, 'signal' => null, 'detail' => '设备正常'];
    }

    /**
     * 激活频率检测
     */
    protected function checkActivationFrequency(License $license): array
    {
        $oneHourAgo = now()->subHour();
        $recentCount = LicenseActivation::where('license_id', $license->id)
            ->where('activated_at', '>=', $oneHourAgo)
            ->count();

        if ($recentCount >= 20) {
            return ['score' => 20, 'weight' => 20, 'signal' => 'freq_critical',
                'detail' => "1小时内激活{$recentCount}次"];
        }
        if ($recentCount >= 10) {
            return ['score' => 14, 'weight' => 20, 'signal' => 'freq_high',
                'detail' => "1小时内激活{$recentCount}次"];
        }
        if ($recentCount >= 5) {
            return ['score' => 8, 'weight' => 20, 'signal' => 'freq_medium',
                'detail' => "1小时内激活{$recentCount}次"];
        }

        return ['score' => 0, 'weight' => 20, 'signal' => null, 'detail' => '激活频率正常'];
    }

    /**
     * 时间异常检测
     */
    protected function checkTimeAnomaly(array $context): array
    {
        $hour = (int) date('H', $context['timestamp'] ?? time());

        // 凌晨 0-5点 高风险时段
        if ($hour >= 0 && $hour <= 5) {
            return ['score' => 10, 'weight' => 15, 'signal' => 'odd_hours',
                'detail' => "非正常时段激活(凌晨{$hour}时)"];
        }

        return ['score' => 0, 'weight' => 15, 'signal' => null, 'detail' => '时段正常'];
    }

    /**
     * IP 信誉检测
     */
    protected function checkIpReputation(string $ip): array
    {
        if (empty($ip)) {
            return ['score' => 2, 'weight' => 10, 'signal' => null, 'detail' => '无IP信息'];
        }

        // 检查IP是否在已知代理/VPN列表中
        // 实际场景可对接第三方IP信誉API (如 ipinfo.io, abuseipdb)
        // 这里使用简化规则
        $knownProxies = Cache::get('proxy_ips', []);
        if (in_array($ip, $knownProxies)) {
            return ['score' => 8, 'weight' => 10, 'signal' => 'proxy_ip',
                'detail' => '代理/VPN IP'];
        }

        // 检查该IP的激活频率
        $ipCount = LicenseActivation::where('ip_address', $ip)
            ->where('activated_at', '>=', now()->subDay())
            ->count();

        if ($ipCount > 50) {
            return ['score' => 7, 'weight' => 10, 'signal' => 'ip_high_volume',
                'detail' => "该IP日激活{$ipCount}次"];
        }

        return ['score' => 0, 'weight' => 10, 'signal' => null, 'detail' => 'IP正常'];
    }

    /**
     * 风险评分转等级
     */
    protected function scoreToLevel(int $score): string
    {
        return match (true) {
            $score >= 80 => 'critical',
            $score >= 50 => 'high',
            $score >= 25 => 'medium',
            default => 'low',
        };
    }

    /**
     * 根据风险等级确定动作
     */
    protected function determineAction(string $level): string
    {
        return match ($level) {
            'critical' => 'block',       // 直接拦截
            'high' => 'challenge',       // 要求MFA验证
            'medium' => 'monitor',       // 监控标记
            default => 'allow',          // 放行
        };
    }

    /**
     * 记录异常 (适配 audit_anomalies 表结构)
     */
    protected function logAnomaly(License $license, array $result, array $context): void
    {
        try {
            AuditAnomaly::create([
                'tenant_id' => $license->tenant_id,
                'anomaly_type' => 'activation_risk',
                'severity' => $result['risk_level'] === 'critical' ? 'critical' : 'warning',
                'metric' => 'activation_risk_score',
                'baseline_value' => 0,
                'actual_value' => $result['risk_score'],
                'deviation' => $result['risk_score'],
                'description' => 'AI风控: ' . implode('; ', $result['signals'] ?? ['无信号']),
                'context' => array_merge($context, [
                    'license_id' => $license->id,
                    'license_key' => $license->license_key,
                    'risk_level' => $result['risk_level'],
                    'signals' => $result['signals'],
                    'action_taken' => $result['action'],
                    'risk_score' => $result['risk_score'],
                ]),
                'status' => 'open',
                'detected_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to log anomaly', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 获取风控统计
     */
    public function getStats(int $tenantId): array
    {
        $thirtyDaysAgo = now()->subDays(30);
        $baseQuery = AuditAnomaly::where('anomaly_type', 'activation_risk')
            ->where('detected_at', '>=', $thirtyDaysAgo);

        if ($tenantId) {
            $baseQuery->where('tenant_id', $tenantId);
        }

        $anomalies = (clone $baseQuery)->get();

        return [
            'total_evaluations' => $anomalies->count(),
            'by_level' => $anomalies->groupBy('severity')->map->count()->toArray(),
            'by_action' => $anomalies->groupBy(fn($a) => $a->context['action_taken'] ?? 'unknown')->map->count()->toArray(),
            'top_signals' => $anomalies
                ->flatMap(fn($a) => $a->context['signals'] ?? [])
                ->countBy()
                ->sortDesc()
                ->take(10)
                ->toArray(),
        ];
    }
}
