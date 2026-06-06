<?php

namespace App\Services;

use App\Models\License;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DeviceLimiter
{
    /**
     * 分布式锁前缀
     */
    const string LOCK_PREFIX = 'hwt:device:lock:';

    /**
     * 设备计数缓存前缀
     */
    const string COUNT_PREFIX = 'hwt:device:count:';

    /**
     * 锁持有时间（秒）
     */
    const int LOCK_TTL = 10;

    /**
     * 缓存 TTL（秒）
     */
    const int COUNT_TTL = 3600;

    /**
     * 尝试获取分布式锁并检查设备数量限制
     *
     * @param License $license
     * @param string  $fingerprint
     * @param int     $maxDevices
     * @return DeviceLimiterResult
     */
    public function acquire(License $license, string $fingerprint, int $maxDevices): DeviceLimiterResult
    {
        $lockKey = self::LOCK_PREFIX . $license->id;
        $lock = Cache::lock($lockKey, self::LOCK_TTL);

        try {
            // 阻塞获取锁，最大等待 5 秒
            $lock->block(5);

            // 获取锁成功后，重新从数据库读取最新设备数
            $currentCount = $this->getDeviceCount($license);

            // 先检查该 fingerprint 是否已经注册（已有设备不受限制）
            if ($this->isDeviceRegistered($license, $fingerprint)) {
                return new DeviceLimiterResult(
                    allowed: true,
                    currentCount: $currentCount,
                    maxDevices: $maxDevices,
                    isExistingDevice: true,
                );
            }

            if ($currentCount >= $maxDevices) {
                return new DeviceLimiterResult(
                    allowed: false,
                    currentCount: $currentCount,
                    maxDevices: $maxDevices,
                    reason: '设备数量已达上限',
                );
            }

            // 检查通过，返回授权
            return new DeviceLimiterResult(
                allowed: true,
                currentCount: $currentCount,
                maxDevices: $maxDevices,
                isExistingDevice: false,
            );

        } catch (LockTimeoutException $e) {
            Log::warning('设备限流锁超时', [
                'license_id' => $license->id,
                'fingerprint' => $fingerprint,
            ]);

            // 锁超时：降级为乐观检查（从 DB 读取）
            return $this->fallbackCheck($license, $fingerprint, $maxDevices);
        } finally {
            // 注意：锁在事务完成后才释放，由调用方显式释放
            // 这里不释放锁，避免并发问题
        }
    }

    /**
     * 释放分布式锁
     */
    public function release(License $license): void
    {
        $lockKey = self::LOCK_PREFIX . $license->id;
        $lock = Cache::lock($lockKey, self::LOCK_TTL);
        $lock->forceRelease();
    }

    /**
     * 获取当前设备数（优先从缓存读取）
     */
    public function getDeviceCount(License $license): int
    {
        $cacheKey = self::COUNT_PREFIX . $license->id;

        return Cache::remember($cacheKey, self::COUNT_TTL, function () use ($license) {
            return $license->devices()->where('is_blacklisted', false)->count();
        });
    }

    /**
     * 刷新设备计数缓存（设备创建/删除后调用）
     */
    public function refreshDeviceCount(License $license): void
    {
        $cacheKey = self::COUNT_PREFIX . $license->id;
        $count = $license->devices()->where('is_blacklisted', false)->count();
        Cache::put($cacheKey, $count, self::COUNT_TTL);
    }

    /**
     * 检查 fingerprint 是否已绑定到该 License
     */
    protected function isDeviceRegistered(License $license, string $fingerprint): bool
    {
        return $license->devices()
            ->where('fingerprint', $fingerprint)
            ->where('is_blacklisted', false)
            ->exists();
    }

    /**
     * 锁超时降级：直接数据库查，不做并发保护
     */
    protected function fallbackCheck(License $license, string $fingerprint, int $maxDevices): DeviceLimiterResult
    {
        $currentCount = $license->devices()->where('is_blacklisted', false)->count();

        $isExisting = $license->devices()
            ->where('fingerprint', $fingerprint)
            ->where('is_blacklisted', false)
            ->exists();

        // 已有设备不受限制
        if ($isExisting) {
            return new DeviceLimiterResult(
                allowed: true,
                currentCount: $currentCount,
                maxDevices: $maxDevices,
                isExistingDevice: true,
            );
        }

        if ($currentCount >= $maxDevices) {
            return new DeviceLimiterResult(
                allowed: false,
                currentCount: $currentCount,
                maxDevices: $maxDevices,
                reason: '设备数量已达上限',
            );
        }

        return new DeviceLimiterResult(
            allowed: true,
            currentCount: $currentCount,
            maxDevices: $maxDevices,
            isExistingDevice: false,
        );
    }
}
