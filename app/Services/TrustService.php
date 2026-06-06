<?php

namespace App\Services;

use App\Models\Device;
use App\Models\License;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 设备信任体系服务
 *
 * 功能：
 * - 信任分管理（事件驱动加减分）
 * - 信任分 > 阈值 → 自动信任
 * - 新设备 → 需要 MFA 验证
 * - 虚拟环境/模拟器检测标记
 *
 * 信任分规则：0~100
 * - 100: 完全信任（白名单设备）
 * - 80-99: 高信任（长期稳定使用）
 * - 50-79: 中等信任（新设备或偶尔异常）
 * - 1-49: 低信任（频繁异常或新环境）
 * - 0: 不信任（黑名单）
 */
class TrustService
{
    /**
     * 自动信任阈值（信任分 >= 此值视为可信设备）
     */
    const AUTO_TRUST_THRESHOLD = 60;

    /**
     * 完全信任阈值（信任分 >= 此值跳过任何验证）
     */
    const FULLY_TRUSTED_THRESHOLD = 80;

    /**
     * 新设备默认信任分
     */
    const DEFAULT_TRUST_SCORE = 50;

    /**
     * 加分事件
     */
    const EVENT_SUCCESS_ACTIVATION = 10;       // 成功激活
    const EVENT_SUCCESS_VALIDATION = 2;        // 成功验证
    const EVENT_CONSISTENT_USE_1H = 1;         // 每小时持续使用
    const EVENT_MFA_VERIFIED = 20;             // 完成 MFA 验证
    const EVENT_MANUAL_WHITELIST = 100;        // 管理员手动加白

    /**
     * 减分事件
     */
    const EVENT_FAILED_ACTIVATION = -15;       // 激活失败
    const EVENT_FAILED_VALIDATION = -5;        // 验证失败
    const EVENT_SUSPICIOUS_FINGERPRINT = -20;  // 指纹异常变更
    const EVENT_GEO_ANOMALY = -25;             // 地理位置异常
    const EVENT_VM_DETECTED = -30;             // 虚拟环境被检测
    const EVENT_RATE_LIMIT_EXCEEDED = -10;     // 触发限流
    const EVENT_MANUAL_BLACKLIST = -100;       // 管理员加黑

    /**
     * 信任分更新锁 TTL（秒）
     */
    const UPDATE_LOCK_TTL = 5;

    /**
     * MFA 验证码 TTL（秒）
     */
    const MFA_CODE_TTL = 300;

    /**
     * 是否信任设备
     *
     * @param Device $device
     * @return bool
     */
    public function isTrusted(Device $device): bool
    {
        if ($device->is_blacklisted) {
            return false;
        }

        return $device->trust_score >= self::AUTO_TRUST_THRESHOLD;
    }

    /**
     * 是否完全信任（跳过 MFA）
     */
    public function isFullyTrusted(Device $device): bool
    {
        return ! $device->is_blacklisted && $device->trust_score >= self::FULLY_TRUSTED_THRESHOLD;
    }

    /**
     * 判断设备是否是新设备（需要 MFA）
     *
     * @param Device $device
     * @return bool true 需要 MFA
     */
    public function requiresMfa(Device $device): bool
    {
        if ($device->is_blacklisted) {
            return false; // 黑名单设备不触发 MFA，直接拒绝
        }

        // 首次激活（信任分为默认值且最近刚创建）
        if ($device->trust_score <= self::DEFAULT_TRUST_SCORE
            && $device->created_at->diffInMinutes(now()) < 10) {
            return true;
        }

        // 低信任设备
        if ($device->trust_score < self::AUTO_TRUST_THRESHOLD) {
            return true;
        }

        return false;
    }

    /**
     * 更新信任分（线程安全—使用分布式锁）
     *
     * @param Device $device
     * @param int $delta 信任分变化值（正为加分，负为减分）
     * @param string $reason 变化原因
     * @return int 更新后的信任分
     */
    public function updateTrustScore(Device $device, int $delta, string $reason = ''): int
    {
        $lockKey = 'trust_lock:device:' . $device->id;

        // 使用 Redis 锁防止并发
        $locked = Cache::lock($lockKey, self::UPDATE_LOCK_TTL)->get();

        try {
            $device->refresh();
            $newScore = max(0, min(100, $device->trust_score + $delta));
            $device->update([
                'trust_score' => $newScore,
                'last_seen_at' => now(),
            ]);

            if ($delta !== 0) {
                Log::info('设备信任分更新', [
                    'device_id' => $device->id,
                    'fingerprint' => $device->fingerprint,
                    'delta' => $delta,
                    'old_score' => $device->trust_score,
                    'new_score' => $newScore,
                    'reason' => $reason ?: '无',
                ]);
            }

            return $newScore;
        } finally {
            if ($locked) {
                Cache::lock($lockKey)->forceRelease();
            }
        }
    }

    /**
     * 设备激活成功后增加信任分
     */
    public function recordSuccessfulActivation(Device $device): int
    {
        return $this->updateTrustScore(
            $device,
            self::EVENT_SUCCESS_ACTIVATION,
            '成功激活',
        );
    }

    /**
     * 设备验证成功后增加信任分
     */
    public function recordSuccessfulValidation(Device $device): int
    {
        return $this->updateTrustScore(
            $device,
            self::EVENT_SUCCESS_VALIDATION,
            '成功验证',
        );
    }

    /**
     * 激活失败后减少信任分
     */
    public function recordFailedActivation(Device $device): int
    {
        return $this->updateTrustScore(
            $device,
            self::EVENT_FAILED_ACTIVATION,
            '激活失败',
        );
    }

    /**
     * 检测到指纹异常时减少信任分
     */
    public function recordSuspiciousFingerprint(Device $device): int
    {
        return $this->updateTrustScore(
            $device,
            self::EVENT_SUSPICIOUS_FINGERPRINT,
            '指纹异常变更',
        );
    }

    /**
     * 检测到地理位置异常时减少信任分
     */
    public function recordGeoAnomaly(Device $device): int
    {
        return $this->updateTrustScore(
            $device,
            self::EVENT_GEO_ANOMALY,
            '地理位置异常',
        );
    }

    /**
     * 检测到虚拟环境时减少信任分
     */
    public function recordVmDetected(Device $device): int
    {
        return $this->updateTrustScore(
            $device,
            self::EVENT_VM_DETECTED,
            '虚拟环境',
        );
    }

    /**
     * MFA 验证成功后增加信任分
     */
    public function recordMfaVerified(Device $device): int
    {
        return $this->updateTrustScore(
            $device,
            self::EVENT_MFA_VERIFIED,
            'MFA 验证通过',
        );
    }

    /**
     * 将设备加入白名单（信任分设为 100）
     */
    public function whitelist(Device $device): void
    {
        $device->update([
            'trust_score' => 100,
            'is_blacklisted' => false,
        ]);

        Log::info('设备已加入白名单', [
            'device_id' => $device->id,
            'fingerprint' => $device->fingerprint,
        ]);
    }

    /**
     * 将设备加入黑名单（信任分设为 0）
     */
    public function blacklist(Device $device, string $reason = ''): void
    {
        $device->update([
            'trust_score' => 0,
            'is_blacklisted' => true,
        ]);

        Log::warning('设备已加入黑名单', [
            'device_id' => $device->id,
            'fingerprint' => $device->fingerprint,
            'reason' => $reason ?: '管理员操作',
        ]);
    }

    /**
     * 计算设备信任等级标签
     */
    public function getTrustLevel(Device $device): string
    {
        if ($device->is_blacklisted) {
            return 'blacklisted';
        }

        return match (true) {
            $device->trust_score >= 100 => 'whitelist',
            $device->trust_score >= 80 => 'high',
            $device->trust_score >= 60 => 'trusted',
            $device->trust_score >= 50 => 'neutral',
            $device->trust_score >= 1 => 'low',
            default => 'untrusted',
        };
    }

    // ─── MFA 验证码 ───

    /**
     * 生成并存储 MFA 验证码
     *
     * @param Device $device
     * @return string 6位验证码
     */
    public function generateMfaCode(Device $device): string
    {
        $code = (string) random_int(100000, 999999);

        Cache::put(
            "mfa_code:{$device->id}",
            [
                'code' => $code,
                'device_fingerprint' => $device->fingerprint,
                'attempts' => 0,
                'created_at' => now()->timestamp,
            ],
            self::MFA_CODE_TTL,
        );

        Log::info('MFA 验证码已生成', [
            'device_id' => $device->id,
            'ttl' => self::MFA_CODE_TTL,
        ]);

        return $code;
    }

    /**
     * 验证 MFA 码
     *
     * @param Device $device
     * @param string $code 用户提供的验证码
     * @return bool
     */
    public function verifyMfaCode(Device $device, string $code): bool
    {
        $key = "mfa_code:{$device->id}";
        $stored = Cache::get($key);

        if (! $stored || ! isset($stored['code'])) {
            return false;
        }

        // 检查尝试次数（防止暴力破解）
        $attempts = ($stored['attempts'] ?? 0) + 1;
        if ($attempts > 5) {
            Cache::forget($key);
            Log::warning('MFA 验证码暴力尝试', [
                'device_id' => $device->id,
                'attempts' => $attempts,
            ]);
            return false;
        }

        // 更新尝试次数
        Cache::put($key, array_merge($stored, ['attempts' => $attempts]), self::MFA_CODE_TTL);

        if (! hash_equals($stored['code'], $code)) {
            // 验证码错误
            return false;
        }

        // 验证成功—删除验证码
        Cache::forget($key);

        // 提升信任分
        $this->recordMfaVerified($device);

        return true;
    }

    /**
     * 获取可信任的设备列表（信任分 >= 阈值）
     *
     * @param License $license
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getTrustedDevices(License $license)
    {
        return Device::where('license_id', $license->id)
            ->where('is_blacklisted', false)
            ->where('trust_score', '>=', self::AUTO_TRUST_THRESHOLD)
            ->get();
    }

    /**
     * 创建新设备并赋予默认信任分
     */
    public function createDevice(array $data): Device
    {
        return Device::create(array_merge($data, [
            'trust_score' => self::DEFAULT_TRUST_SCORE,
        ]));
    }
}
