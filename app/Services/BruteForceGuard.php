<?php

namespace App\Services;

use App\Models\License;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 暴力枚举实时阻断服务
 *
 * 检测模式：
 * - 连续 N 次无效 License Key → 临时封禁 IP
 * - 连续 N 次无效激活 → 临时封禁 IP + 告警
 * - 封禁自动过期（滑动窗口）
 * - 支持多级阈值（警告 → 短封 → 长封 → 永久封禁）
 */
class BruteForceGuard
{
    const CACHE_PREFIX = 'bruteforce:';

    /**
     * 默认阈值：连续无效次数触发封禁
     */
    const DEFAULT_THRESHOLD = 5;

    /**
     * 默认封禁时长（秒）
     */
    const DEFAULT_BAN_DURATION = 900; // 15 分钟

    /**
     * 封禁等级配置
     */
    const BAN_LEVELS = [
        5  => ['duration' => 300, 'label' => '短时封禁'],      // 5次 → 5分钟
        10 => ['duration' => 1800, 'label' => '中等封禁'],     // 10次 → 30分钟
        20 => ['duration' => 7200, 'label' => '长时封禁'],     // 20次 → 2小时
        50 => ['duration' => 86400, 'label' => '日封禁'],      // 50次 → 24小时
        100 => ['duration' => 604800, 'label' => '周封禁'],    // 100次 → 7天
    ];

    /**
     * IP 封禁键前缀
     */
    const IP_BAN_KEY = 'banned:ip:';

    /**
     * 失败计数过期时间（秒）
     */
    const FAILURE_TTL = 3600;

    /**
     * 记录一次无效 License Key 尝试
     *
     * @param string $ip 客户端 IP
     * @param string $licenseKey 尝试的 License Key
     * @param string $reason 失败原因
     * @return array ['blocked' => bool, 'ban_level' => ?int]
     */
    public function recordInvalidAttempt(string $ip, string $licenseKey, string $reason = ''): array
    {
        // 先递增失败计数（即使已封禁也要计数，用于升级封禁等级）
        $failCount = $this->incrementFailCount($ip, $licenseKey);
        $totalFailCount = $this->getTotalFailCount($ip);

        // 1. 检查 IP 是否已封禁
        if ($this->isIpBanned($ip)) {
            // 检查是否需要升级封禁等级
            $banLevel = null;
            foreach (self::BAN_LEVELS as $threshold => $config) {
                if ($totalFailCount >= $threshold) {
                    $banLevel = $threshold;
                }
            }

            return ['blocked' => true, 'ban_level' => $banLevel];
        }

        $banLevel = null;

        // 2. 检查是否触发封禁阈值
        foreach (self::BAN_LEVELS as $threshold => $config) {
            if ($totalFailCount >= $threshold) {
                $banLevel = $threshold;
            }
        }

        if ($banLevel !== null) {
            $duration = self::BAN_LEVELS[$banLevel]['duration'];
            $this->banIp($ip, $duration, "连续 {$totalFailCount} 次无效尝试");
        }

        // 4. 告警（重要：达到长封或以上时告警）
        if ($banLevel !== null && $banLevel >= 20) {
            Log::error('暴力枚举检测告警', [
                'ip' => $ip,
                'total_failures' => $totalFailCount,
                'ban_level' => self::BAN_LEVELS[$banLevel]['label'],
                'duration' => self::BAN_LEVELS[$banLevel]['duration'],
                'reason' => $reason ?: '连续无效 License Key',
            ]);
        }

        return [
            'blocked' => $banLevel !== null,
            'ban_level' => $banLevel,
        ];
    }

    /**
     * 记录一次激活失败（有别于无效 Key—激活可能因其他原因失败）
     */
    public function recordActivationFailure(string $ip, string $licenseKey): array
    {
        // 先检查 license_key 是否有效
        $license = License::where('license_key', $licenseKey)->first();

        if (! $license) {
            // 无效 Key—属于暴力枚举
            return $this->recordInvalidAttempt($ip, $licenseKey, '无效 License Key 激活');
        }

        // 有效 Key 但激活失败（设备超限等）—不计数暴力枚举
        return ['blocked' => false, 'ban_level' => null];
    }

    /**
     * 检查 IP 是否被封禁
     */
    public function isIpBanned(string $ip): bool
    {
        return Cache::has(self::CACHE_PREFIX . self::IP_BAN_KEY . $ip);
    }

    /**
     * 获取封禁剩余秒数
     *
     * 从缓存数据中计算剩余时间（兼容 Array/Redis store）
     */
    public function getBanRemainingTtl(string $ip): int
    {
        $data = Cache::get(self::CACHE_PREFIX . self::IP_BAN_KEY . $ip);
        if (! $data || ! isset($data['expires_at'])) {
            return 0;
        }

        return max(0, (int) ($data['expires_at'] - time()));
    }

    /**
     * 获取封禁等级信息
     */
    public function getBanLevel(string $ip): ?int
    {
        $info = $this->getBanInfo($ip);
        return $info ? ($info['total_failures'] ?? null) : null;
    }

    /**
     * 封禁 IP
     */
    public function banIp(string $ip, int $duration, string $reason = ''): void
    {
        $expiresAt = time() + $duration;

        Cache::put(self::CACHE_PREFIX . self::IP_BAN_KEY . $ip, [
            'banned_at' => now()->timestamp,
            'duration' => $duration,
            'reason' => $reason,
            'ip' => $ip,
            'expires_at' => $expiresAt,
        ], $duration);

        Log::warning("IP {$ip} 已被封禁", [
            'duration' => $duration,
            'reason' => $reason,
        ]);
    }

    /**
     * 解封 IP
     */
    public function unbanIp(string $ip): void
    {
        Cache::forget(self::CACHE_PREFIX . self::IP_BAN_KEY . $ip);
        Cache::forget(self::CACHE_PREFIX . 'ban_level:' . $ip);
        Cache::forget(self::CACHE_PREFIX . 'failures:' . $ip);
        Cache::forget(self::CACHE_PREFIX . 'total_failures:' . $ip);

        Log::info("IP {$ip} 已被解封");
    }

    /**
     * 递增 IP 的失败计数
     */
    protected function incrementFailCount(string $ip, string $licenseKey): int
    {
        // 按特定 Key 计数
        $key = self::CACHE_PREFIX . 'failures:' . $ip;
        $count = Cache::increment($key, 1) ?? 1;
        Cache::put($key, $count, self::FAILURE_TTL);

        // 总失败计数（累积）
        $totalKey = self::CACHE_PREFIX . 'total_failures:' . $ip;
        $totalCount = Cache::increment($totalKey, 1) ?? 1;
        Cache::put($totalKey, $totalCount, self::FAILURE_TTL * 24); // 保留 24h

        // 记录最后一次尝试
        Cache::put(self::CACHE_PREFIX . 'last_attempt:' . $ip, [
            'license_key' => $licenseKey,
            'attempted_at' => now()->timestamp,
        ], self::FAILURE_TTL);

        return $count;
    }

    /**
     * 获取短窗口失败计数
     */
    public function getFailCount(string $ip): int
    {
        return (int) Cache::get(self::CACHE_PREFIX . 'failures:' . $ip, 0);
    }

    /**
     * 获取总失败计数
     */
    public function getTotalFailCount(string $ip): int
    {
        return (int) Cache::get(self::CACHE_PREFIX . 'total_failures:' . $ip, 0);
    }

    /**
     * 获取最后一次尝试信息
     */
    public function getLastAttempt(string $ip): ?array
    {
        return Cache::get(self::CACHE_PREFIX . 'last_attempt:' . $ip);
    }

    /**
     * 获取封禁统计信息
     */
    public function getBanInfo(string $ip): ?array
    {
        if (! $this->isIpBanned($ip)) {
            return null;
        }

        $data = Cache::get(self::CACHE_PREFIX . self::IP_BAN_KEY . $ip, []);
        $remaining = $this->getBanRemainingTtl($ip);
        $failCount = $this->getFailCount($ip);
        $totalFailCount = $this->getTotalFailCount($ip);

        return [
            'ip' => $ip,
            'banned_at' => $data['banned_at'] ?? null,
            'duration' => $data['duration'] ?? 0,
            'reason' => $data['reason'] ?? '',
            'remaining_seconds' => $remaining,
            'recent_failures' => $failCount,
            'total_failures' => $totalFailCount,
        ];
    }
}
