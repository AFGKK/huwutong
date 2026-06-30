<?php

namespace App\Services;

use App\Models\AuditAnomaly;
use App\Models\License;
use App\Models\LicenseActivation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 行为风控引擎 (M3-02)
 *
 * AI模型识别异常请求、自动封禁、行为画像
 * 基于规则引擎+统计异常检测，可扩展为ML模型驱动
 */
class BehaviorEngine
{
    const ACTION_NONE = 'none';
    const ACTION_FLAG = 'flag';
    const ACTION_RATE_LIMIT = 'rate_limit';
    const ACTION_CHALLENGE = 'challenge';
    const ACTION_BLOCK = 'block';

    /**
     * 分析请求行为并返回处理建议
     */
    public function analyze(string $endpoint, array $context): array
    {
        $signals = [];
        $riskScore = 0;

        // 1. 速率异常检测
        $rateResult = $this->detectRateAnomaly($endpoint, $context);
        $riskScore += $rateResult['score'];
        if ($rateResult['signal']) $signals[] = $rateResult;

        // 2. 设备行为画像
        $profileResult = $this->checkDeviceProfile($context);
        $riskScore += $profileResult['score'];
        if ($profileResult['signal']) $signals[] = $profileResult;

        // 3. 请求模式检测
        $patternResult = $this->detectRequestPattern($endpoint, $context);
        $riskScore += $patternResult['score'];
        if ($patternResult['signal']) $signals[] = $patternResult;

        // 4. 并发检测
        $concurrencyResult = $this->detectConcurrency($context);
        $riskScore += $concurrencyResult['score'];
        if ($concurrencyResult['signal']) $signals[] = $concurrencyResult;

        $action = $this->determineAction($riskScore);
        $isBlocked = $action === self::ACTION_BLOCK;

        // 自动封禁
        if ($isBlocked) {
            $this->autoBan($context, $signals);
        }

        return [
            'risk_score' => $riskScore,
            'action' => $action,
            'is_blocked' => $isBlocked,
            'signals' => $signals,
            'analyzed_at' => now()->toIso8601String(),
        ];
    }

    /**
     * 速率异常检测
     */
    protected function detectRateAnomaly(string $endpoint, array $context): array
    {
        $ip = $context['ip'] ?? '';
        $licenseKey = $context['license_key'] ?? '';
        $key = "behavior:rate:{$endpoint}:{$ip}";

        // 使用计数器统计请求频率
        $count = Cache::increment($key);
        if ($count === 1) {
            Cache::put($key, true, 60); // 60秒窗口
        }

        if ($count > 100) {
            return ['score' => 40, 'signal' => 'rate_extreme',
                'detail' => "IP {$ip} 在60秒内请求{$count}次"];
        }
        if ($count > 50) {
            return ['score' => 25, 'signal' => 'rate_high',
                'detail' => "IP {$ip} 在60秒内请求{$count}次"];
        }
        if ($count > 20) {
            return ['score' => 10, 'signal' => 'rate_medium',
                'detail' => "IP {$ip} 在60秒内请求{$count}次"];
        }

        return ['score' => 0, 'signal' => null, 'detail' => '速率正常'];
    }

    /**
     * 设备行为画像检测
     */
    protected function checkDeviceProfile(array $context): array
    {
        $fingerprint = $context['device_fingerprint'] ?? '';
        if (!$fingerprint) {
            return ['score' => 0, 'signal' => null, 'detail' => '无设备信息'];
        }

        // 检查设备关联的License数
        $licenseCount = DB::table('license_activations')
            ->where('fingerprint', $fingerprint)
            ->distinct('license_id')
            ->count('license_id');

        if ($licenseCount > 10) {
            return ['score' => 30, 'signal' => 'device_license_abuse',
                'detail' => "设备关联{$licenseCount}个License"];
        }
        if ($licenseCount > 5) {
            return ['score' => 15, 'signal' => 'device_license_many',
                'detail' => "设备关联{$licenseCount}个License"];
        }

        // 检查设备失败率
        $totalAttempts = LicenseActivation::where('fingerprint', $fingerprint)->count();
        if ($totalAttempts > 0) {
            // 简化处理，实际应检查失败次数
        }

        return ['score' => 0, 'signal' => null, 'detail' => '设备画像正常'];
    }

    /**
     * 请求模式检测
     */
    protected function detectRequestPattern(string $endpoint, array $context): array
    {
        $ip = $context['ip'] ?? '';

        // 检测暴力枚举模式（连续无效key）
        $invalidKeyPattern = "behavior:invalid_key:{$ip}";
        $invalidCount = (int) Cache::get($invalidKeyPattern, 0);

        if (str_contains($endpoint, 'activate') || str_contains($endpoint, 'validate')) {
            $licenseKey = $context['license_key'] ?? '';
            if ($licenseKey && !License::where('license_key', $licenseKey)->exists()) {
                Cache::increment($invalidKeyPattern);
                Cache::put($invalidKeyPattern, true, 3600);

                if ($invalidCount >= 10) {
                    return ['score' => 35, 'signal' => 'brute_force',
                        'detail' => "连续{$invalidCount}次无效License Key"];
                }
            }
        }

        return ['score' => 0, 'signal' => null, 'detail' => '请求模式正常'];
    }

    /**
     * 并发检测
     */
    protected function detectConcurrency(array $context): array
    {
        $ip = $context['ip'] ?? '';
        $key = "behavior:concurrent:{$ip}";

        $concurrent = (int) Cache::get($key, 0);
        Cache::increment($key);
        Cache::put($key, true, 10); // 10秒窗口

        if ($concurrent > 20) {
            return ['score' => 20, 'signal' => 'high_concurrency',
                'detail' => "IP {$ip} 并发{$concurrent}请求"];
        }

        return ['score' => 0, 'signal' => null, 'detail' => '并发正常'];
    }

    /**
     * 根据风险分决定动作
     */
    protected function determineAction(int $score): string
    {
        return match (true) {
            $score >= 80 => self::ACTION_BLOCK,
            $score >= 50 => self::ACTION_CHALLENGE,
            $score >= 25 => self::ACTION_RATE_LIMIT,
            $score >= 10 => self::ACTION_FLAG,
            default => self::ACTION_NONE,
        };
    }

    /**
     * 自动封禁
     */
    protected function autoBan(array $context, array $signals): void
    {
        $ip = $context['ip'] ?? '';
        $fingerprint = $context['device_fingerprint'] ?? '';

        // IP临时封禁 (1小时)
        if ($ip) {
            Cache::put("banned:ip:{$ip}", true, 3600);
        }

        // 设备封禁
        if ($fingerprint) {
            Cache::put("banned:device:{$fingerprint}", true, 86400);
            DB::table('devices')->where('fingerprint', $fingerprint)
                ->update(['is_blacklisted' => true]);
        }

        Log::warning('BehaviorEngine: auto-banned', [
            'ip' => $ip,
            'device_fingerprint' => $fingerprint,
            'signals' => $signals,
        ]);
    }

    /**
     * 检查IP是否被封禁
     */
    public function isIpBanned(string $ip): bool
    {
        return Cache::has("banned:ip:{$ip}");
    }

    /**
     * 检查设备是否被封禁
     */
    public function isDeviceBanned(string $fingerprint): bool
    {
        return Cache::has("banned:device:{$fingerprint}");
    }

    /**
     * 手动解封
     */
    public function unban(string $type, string $value): bool
    {
        $key = $type === 'ip' ? "banned:ip:{$value}" : "banned:device:{$value}";

        if (Cache::has($key)) {
            Cache::forget($key);

            if ($type === 'device') {
                DB::table('devices')->where('fingerprint', $value)
                    ->update(['is_blacklisted' => false]);
            }

            Log::info('BehaviorEngine: unbanned', ['type' => $type, 'value' => $value]);
            return true;
        }

        return false;
    }

    /**
     * 获取封禁列表
     */
    public function getBannedList(): array
    {
        // 简化实现 - 实际应使用专门的封禁管理表
        $bannedIps = [];
        $bannedDevices = [];

        // 从缓存获取活跃封禁
        // 实际项目中应使用封禁记录表

        return [
            'banned_ips' => $bannedIps,
            'banned_devices' => $bannedDevices,
        ];
    }

    /**
     * 获取行为统计
     */
    public function getStats(): array
    {
        return [
            'total_analyses' => Cache::get('behavior:total_analyses', 0),
            'blocked_count' => Cache::get('behavior:blocked_count', 0),
            'challenged_count' => Cache::get('behavior:challenged_count', 0),
        ];
    }
}
