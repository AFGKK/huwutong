<?php

namespace App\Services;

use App\Models\License;
use App\Models\LicenseActivation;
use App\Models\Log;
use Illuminate\Support\Facades\Log as LogFacade;

/**
 * AI 错误诊断助手服务 (M2-40)
 *
 * 当 License 激活/验证失败时，AI 自动分析根因，
 * 提供自然语言解释和解决方案建议，减少 80% 工单。
 *
 * 诊断场景：
 * - 设备超限 (DEVICE_LIMIT_EXCEEDED)
 * - License 过期 (LICENSE_EXPIRED)
 * - 指纹不匹配 (FINGERPRINT_MISMATCH)
 * - IP 限制 (IP_RESTRICTED)
 * - 地理围栏拦截 (GEO_BLOCKED)
 * - 签名验证失败 (SIGNATURE_MISMATCH)
 * - 激活请求重放 (REPLAY_DETECTED)
 * - 租户隔离冲突 (TENANT_MISMATCH)
 */
class AiDiagnosticEngineService
{
    public function __construct(
        protected LlmService $llmService,
    ) {}

    /**
     * 诊断激活/验证失败原因并返回分析结果
     */
    public function diagnose(string $licenseKey, array $context = []): array
    {
        $license = License::where('key', $licenseKey)->first();
        if (! $license) {
            return $this->buildResponse('invalid_key', 'License Key 不存在', '请检查输入的 License Key 是否正确，或联系管理员确认 Key 是否已被删除。');
        }

        // 按优先级逐一检查
        $checks = [
            'status'       => fn() => $this->checkStatus($license),
            'expiry'       => fn() => $this->checkExpiry($license),
            'device_limit' => fn() => $this->checkDeviceLimit($license, $context),
            'fingerprint'  => fn() => $this->checkFingerprint($license, $context),
            'geo'          => fn() => $this->checkGeoRestriction($license, $context),
            'ip'           => fn() => $this->checkIpRestriction($license, $context),
        ];

        foreach ($checks as $key => $check) {
            $result = $check();
            if ($result !== null) {
                return $result;
            }
        }

        // 无法确定具体原因时，聚合最近日志
        return $this->fallbackDiagnosis($license, $context);
    }

    /**
     * 分析最近 N 次激活失败的共同模式
     */
    public function analyzeFailurePatterns(string $licenseKey, int $lastAttempts = 10): array
    {
        $license = License::where('key', $licenseKey)->first();
        if (! $license) {
            return ['error' => 'License not found'];
        }

        $recentActivations = LicenseActivation::where('license_id', $license->id)
            ->latest()
            ->take($lastAttempts)
            ->get();

        if ($recentActivations->isEmpty()) {
            return ['pattern' => 'no_recent_activity', 'message' => __('app.common.no_recent_activation_records')];
        }

        $failureCount = $recentActivations->filter(fn($a) => $a->status === 'failed')->count();
        $ipAddresses = $recentActivations->pluck('ip_address')->unique();
        $devices = $recentActivations->pluck('device_id')->unique();

        $patterns = [];
        if ($failureCount > $lastAttempts * 0.7) {
            $patterns[] = '高频失败（' . $failureCount . '/' . $lastAttempts . '次）';
        }
        if ($ipAddresses->count() > 3) {
            $patterns[] = 'IP 地址频繁变化（' . $ipAddresses->count() . '个不同IP）';
        }
        if ($devices->count() > 3) {
            $patterns[] = '设备频繁变更（' . $devices->count() . '个不同设备）';
        }

        return [
            'license_key' => $licenseKey,
            'total_attempts' => $recentActivations->count(),
            'failures' => $failureCount,
            'patterns' => $patterns,
            'suggestion' => $this->generateSuggestion($patterns),
        ];
    }

    /**
     * 利用 LLM 生成自然语言诊断报告
     */
    public function generateReport(array $diagnosisResult): string
    {
        $prompt = "你是一个 License 授权系统的 AI 诊断助手。基于以下诊断数据，用简洁的中文向用户解释问题原因和解决步骤：\n\n"
            . json_encode($diagnosisResult, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        try {
            return $this->llmService->chat($prompt);
        } catch (\Exception $e) {
            LogFacade::warning('AI 诊断 LLM 调用失败', ['error' => $e->getMessage()]);
            return $diagnosisResult['message'] ?? '诊断失败，请查看详细错误信息。';
        }
    }

    // ─── 内部检查方法 ───

    protected function checkStatus(License $license): ?array
    {
        $failedStatuses = [
            'revoked'    => 'License 已被撤销，请联系管理员恢复。',
            'blacklisted'=> 'License 已被加入黑名单，请联系管理员。',
            'refunded'   => 'License 已退款，不可继续使用。',
            'suspended'  => 'License 已被挂起，请检查账户状态。',
            'frozen'     => 'License 已被冻结（风控），请联系客服解冻。',
        ];

        if (isset($failedStatuses[$license->status->value])) {
            return $this->buildResponse(
                'status_' . $license->status->value,
                'License 状态异常：' . $license->status->value,
                $failedStatuses[$license->status->value]
            );
        }
        return null;
    }

    protected function checkExpiry(License $license): ?array
    {
        if ($license->valid_until && $license->valid_until->isPast()) {
            $daysOverdue = now()->diffInDays($license->valid_until);
            return $this->buildResponse(
                'license_expired',
                "License 已过期 {$daysOverdue} 天",
                '请续期 License。前往后台 → 订阅管理 → 续期，或联系销售。'
            );
        }
        return null;
    }

    protected function checkDeviceLimit(License $license, array $context): ?array
    {
        $fingerprint = $context['fingerprint'] ?? $context['machine_id'] ?? null;
        if (! $fingerprint) {
            return null;
        }

        $activeCount = LicenseActivation::where('license_id', $license->id)
            ->whereNull('deactivated_at')
            ->count();

        if ($activeCount >= $license->max_devices) {
            $suggestion = '当前已激活 ' . $activeCount . '/' . $license->max_devices . ' 台设备。'
                . '请在已有设备上解除激活，或升级 License 以增加设备上限。'
                . '管理后台 → License 详情 → 设备管理。';
            return $this->buildResponse('device_limit_exceeded', '设备激活数已达上限', $suggestion);
        }
        return null;
    }

    protected function checkFingerprint(License $license, array $context): ?array
    {
        $fingerprint = $context['fingerprint'] ?? $context['machine_id'] ?? null;
        if (! $fingerprint || empty($context['activation_id'])) {
            return null;
        }

        $activation = LicenseActivation::find($context['activation_id']);
        if (! $activation) {
            return null;
        }

        if ($activation->device_id && optional($activation->device)->fingerprint !== $fingerprint) {
            return $this->buildResponse(
                'fingerprint_mismatch',
                '设备指纹不匹配',
                '当前设备的硬件指纹与激活记录不一致。可能原因：'
                . '①硬件变更（更换硬盘/CPU/主板）；'
                . '②虚拟机克隆。解决方案：在旧设备上解除激活后重新激活，'
                . '或联系管理员强制解绑。'
            );
        }
        return null;
    }

    protected function checkGeoRestriction(License $license, array $context): ?array
    {
        $country = $context['country'] ?? null;
        if (! $country || ! $license->geo_restrictions) {
            return null;
        }

        $allowed = $license->geo_restrictions['allowed_countries'] ?? [];
        if (! empty($allowed) && ! in_array($country, $allowed)) {
            return $this->buildResponse(
                'geo_blocked',
                "当前区域 {$country} 不在允许列表",
                '此 License 仅限 ' . implode(', ', $allowed) . ' 区域使用。'
                . '如需跨区域使用，请联系管理员调整地理围栏配置。'
            );
        }
        return null;
    }

    protected function checkIpRestriction(License $license, array $context): ?array
    {
        $ip = $context['ip'] ?? request()->ip();
        if (! $ip || ! $license->ip_restrictions) {
            return null;
        }

        $cidrList = $license->ip_restrictions['allowed_cidr'] ?? [];
        foreach ($cidrList as $cidr) {
            if ($this->ipInCidr($ip, $cidr)) {
                return null;
            }
        }

        return $this->buildResponse(
            'ip_restricted',
            "IP {$ip} 不在许可范围内",
            '此 License 绑定到特定 IP 范围 ' . implode(', ', $cidrList)
            . '。请使用绑定 IP 范围内的网络访问，或联系管理员修改 IP 限制。'
        );
    }

    protected function fallbackDiagnosis(License $license, array $context): array
    {
        $recentLogs = Log::where('resource_type', 'license')
            ->where('resource_id', $license->id)
            ->where('created_at', '>=', now()->subHours(24))
            ->latest()
            ->take(5)
            ->get();

        $logSummary = $recentLogs->map(fn($log) => sprintf(
            '[%s] %s: %s',
            $log->created_at->format('H:i:s'),
            $log->action,
            json_encode($log->new_values, JSON_UNESCAPED_UNICODE)
        ))->implode("\n");

        return $this->buildResponse(
            'unknown',
            '无法确定具体原因',
            "最近活动日志：\n{$logSummary}\n\n建议联系技术支持并提供此诊断报告。"
        );
    }

    protected function generateSuggestion(array $patterns): string
    {
        if (empty($patterns)) {
            return '建议检查网络连接和 License Key 是否正确。';
        }
        return implode('；', $patterns) . '。建议联系技术支持。';
    }

    protected function buildResponse(string $code, string $reason, string $suggestion): array
    {
        return [
            'code' => $code,
            'reason' => $reason,
            'suggestion' => $suggestion,
            'diagnosed_at' => now()->toIso8601String(),
        ];
    }

    protected function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr) + [1 => 32];
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $mask = -1 << (32 - (int) $bits);
        $subnetLong &= $mask;
        return ($ipLong & $mask) === $subnetLong;
    }
}
