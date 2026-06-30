<?php

namespace App\Services;

use App\Models\SdkCacheInvalidationLog;
use App\Models\SdkCacheRecord;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * M2-17b SDK 在线验证本地缓存 + 离线宽限期
 *
 * 管理 SDK 验证结果的本地缓存策略、离线宽限期、
 * 网络抖动降级、缓存失效推送。
 * 依赖 M2-16 SDK版本兼容、M1.3-04 本地加密缓存。
 */
class SdkLocalCacheService
{
    private const CACHE_KEY_PREFIX = 'sdk:cache:status:%s';

    /**
     * SDK 上报缓存状态
     */
    public function reportCacheStatus(array $data): SdkCacheRecord
    {
        $cacheKeyHash = hash('sha256', ($data['license_key'] ?? '') . '|' . ($data['sdk_instance_id'] ?? ''));

        $ttl = config('sdk-local-cache.cache.ttl', 86400);
        $graceDays = config('sdk-local-cache.grace_period.days', 7);

        $record = SdkCacheRecord::updateOrCreate(
            [
                'sdk_instance_id' => $data['sdk_instance_id'],
                'cache_key_hash' => $cacheKeyHash,
            ],
            [
                'language' => $data['language'] ?? null,
                'sdk_version' => $data['sdk_version'] ?? null,
                'machine_id' => $data['machine_id'] ?? null,
                'license_key' => $data['license_key'] ?? null,
                'status' => 'active',
                'cached_at' => $data['cached_at'] ?? now(),
                'expires_at' => now()->addSeconds($ttl),
                'grace_expires_at' => now()->addSeconds($ttl)->addDays($graceDays),
                'last_access_at' => now(),
                'last_verification_result' => $data['result'] ?? 'unknown',
                'is_offline' => $data['is_offline'] ?? false,
            ]
        );

        // 递增访问计数
        $record->increment('access_count');

        return $record;
    }

    /**
     * SDK 请求缓存状态（检查是否需要刷新缓存）
     */
    public function getCacheStatus(string $sdkInstanceId, string $licenseKey): array
    {
        $cacheKeyHash = hash('sha256', $licenseKey . '|' . $sdkInstanceId);
        $record = SdkCacheRecord::byInstance($sdkInstanceId)
            ->where('cache_key_hash', $cacheKeyHash)
            ->first();

        if (!$record) {
            return [
                'cached' => false,
                'needs_refresh' => true,
                'status' => 'not_found',
            ];
        }

        $record->increment('access_count');
        $record->update(['last_access_at' => now()]);

        $now = now();
        $isExpired = $record->expires_at && $record->expires_at->isPast();
        $inGrace = $record->grace_expires_at && !$record->grace_expires_at->isPast();
        $isTampered = $record->status === 'tampered';
        $isInvalidated = $record->status === 'invalidated';

        // 判断是否需要刷新
        $needsRefresh = $isExpired || $isInvalidated || $isTampered;

        // 判断是否在宽限期内
        $inGracePeriod = $inGrace && ($isExpired || $needsRefresh) && !$isTampered;

        return [
            'cached' => true,
            'needs_refresh' => $needsRefresh,
            'in_grace_period' => $inGracePeriod,
            'grace_expires_at' => $record->grace_expires_at?->toIso8601String(),
            'expires_at' => $record->expires_at?->toIso8601String(),
            'status' => $record->status,
            'access_count' => $record->access_count,
            'is_offline' => $record->is_offline,
        ];
    }

    /**
     * 失效指定 License 的缓存
     */
    public function invalidateByLicense(string $licenseKey, string $reason = 'license_change', ?int $userId = null): int
    {
        $affected = SdkCacheRecord::byLicense($licenseKey)->active()->get();
        $count = 0;

        foreach ($affected as $record) {
            $record->update(['status' => 'invalidated']);
            $this->clearCacheStatus($record->sdk_instance_id);
            $count++;
        }

        SdkCacheInvalidationLog::create([
            'license_key' => $licenseKey,
            'trigger_type' => 'license_change',
            'reason' => $reason,
            'affected_cache_keys' => $affected->pluck('cache_key_hash')->toArray(),
            'source' => 'system',
            'triggered_by' => $userId,
        ]);

        return $count;
    }

    /**
     * 失效指定 SDK 实例的所有缓存
     */
    public function invalidateByInstance(string $sdkInstanceId, string $reason = 'manual', ?int $userId = null): int
    {
        $affected = SdkCacheRecord::byInstance($sdkInstanceId)->active()->get();
        $count = 0;

        foreach ($affected as $record) {
            $record->update(['status' => 'invalidated']);
            $count++;
        }

        $this->clearCacheStatus($sdkInstanceId);

        SdkCacheInvalidationLog::create([
            'sdk_instance_id' => $sdkInstanceId,
            'trigger_type' => 'manual',
            'reason' => $reason,
            'affected_cache_keys' => $affected->pluck('cache_key_hash')->toArray(),
            'source' => 'admin',
            'triggered_by' => $userId,
        ]);

        return $count;
    }

    /**
     * 标记缓存被篡改
     */
    public function markTampered(string $sdkInstanceId, string $cacheKeyHash, string $reason = 'integrity_check_failed'): bool
    {
        $record = SdkCacheRecord::byInstance($sdkInstanceId)
            ->where('cache_key_hash', $cacheKeyHash)
            ->first();

        if (!$record) {
            return false;
        }

        $record->update(['status' => 'tampered']);
        $this->clearCacheStatus($sdkInstanceId);

        SdkCacheInvalidationLog::create([
            'sdk_instance_id' => $sdkInstanceId,
            'license_key' => $record->license_key,
            'trigger_type' => 'manual',
            'reason' => "缓存被篡改: {$reason}",
            'affected_cache_keys' => [$cacheKeyHash],
            'source' => 'system',
        ]);

        return true;
    }

    /**
     * SDK 获取缓存配置（启动时拉取）
     */
    public function getCacheConfig(): array
    {
        return [
            'enabled' => config('sdk-local-cache.cache.enabled', true),
            'ttl' => config('sdk-local-cache.cache.ttl', 86400),
            'max_entries' => config('sdk-local-cache.cache.max_entries', 1000),
            'storage' => config('sdk-local-cache.cache.storage', 'encrypted_file'),
            'encryption' => config('sdk-local-cache.cache.encryption', 'aes-256-gcm'),
            'file_path' => config('sdk-local-cache.cache.file_path'),
            'grace_period' => [
                'enabled' => config('sdk-local-cache.grace_period.enabled', true),
                'days' => config('sdk-local-cache.grace_period.days', 7),
                'lock_on_expiry' => config('sdk-local-cache.grace_period.lock_on_expiry', true),
                'degraded_mode' => config('sdk-local-cache.grace_period.degraded_mode', 'readonly'),
            ],
            'network' => [
                'timeout_threshold' => config('sdk-local-cache.network.timeout_threshold', 3000),
                'retry_count' => config('sdk-local-cache.network.retry_count', 2),
                'retry_interval' => config('sdk-local-cache.network.retry_interval', 500),
                'silent_fallback' => config('sdk-local-cache.network.silent_fallback', true),
            ],
            'tamper' => [
                'enabled' => config('sdk-local-cache.tamper.enabled', true),
                'algorithm' => config('sdk-local-cache.tamper.algorithm', 'hmac-sha256'),
                'on_tamper' => config('sdk-local-cache.tamper.on_tamper', 'invalidate'),
            ],
        ];
    }

    /**
     * 获取仪表盘数据
     */
    public function getDashboard(): array
    {
        $total = SdkCacheRecord::count();
        $active = SdkCacheRecord::active()->count();
        $expired = SdkCacheRecord::expiring()->count();
        $invalidated = SdkCacheRecord::where('status', 'invalidated')->count();
        $tampered = SdkCacheRecord::where('status', 'tampered')->count();
        $offline = SdkCacheRecord::offline()->count();
        $inGrace = SdkCacheRecord::where('status', 'active')
            ->where('expires_at', '<=', now())
            ->where('grace_expires_at', '>', now())
            ->count();

        $recentLogs = SdkCacheInvalidationLog::orderByDesc('id')->limit(10)->get();

        return [
            'total_records' => $total,
            'active' => $active,
            'expired' => $expired,
            'invalidated' => $invalidated,
            'tampered' => $tampered,
            'offline_instances' => $offline,
            'in_grace_period' => $inGrace,
            'cache_hit_rate' => $total > 0 ? round((($total - $expired) / $total) * 100, 1) : 100,
            'recent_invalidations' => $recentLogs,
        ];
    }

    /**
     * 获取缓存记录列表
     */
    public function getRecords(array $filters = [], int $perPage = 20): array
    {
        $query = SdkCacheRecord::query();

        if (!empty($filters['sdk_instance_id'])) {
            $query->byInstance($filters['sdk_instance_id']);
        }
        if (!empty($filters['license_key'])) {
            $query->byLicense($filters['license_key']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['is_offline'])) {
            $query->where('is_offline', $filters['is_offline'] === 'true' || $filters['is_offline'] === true);
        }
        if (!empty($filters['language'])) {
            $query->where('language', $filters['language']);
        }

        $query->orderByDesc('last_access_at');

        return $query->paginate(min($perPage, 100))->toArray();
    }

    /**
     * 获取失效日志列表
     */
    public function getInvalidationLogs(array $filters = [], int $perPage = 20): array
    {
        $query = SdkCacheInvalidationLog::query()->with('triggerer:id,name');

        if (!empty($filters['sdk_instance_id'])) {
            $query->byInstance($filters['sdk_instance_id']);
        }
        if (!empty($filters['license_key'])) {
            $query->byLicense($filters['license_key']);
        }
        if (!empty($filters['trigger_type'])) {
            $query->byTrigger($filters['trigger_type']);
        }

        $query->orderByDesc('id');

        return $query->paginate(min($perPage, 100))->toArray();
    }

    /**
     * 批量失效缓存
     */
    public function batchInvalidate(array $criteria, string $reason, ?int $userId = null): array
    {
        $query = SdkCacheRecord::active();

        if (!empty($criteria['language'])) {
            $query->where('language', $criteria['language']);
        }
        if (!empty($criteria['sdk_version'])) {
            $query->where('sdk_version', $criteria['sdk_version']);
        }
        if (!empty($criteria['offline_only'])) {
            $query->offline();
        }
        if (!empty($criteria['expired_only'])) {
            $query->expiring();
        }

        $records = $query->get();
        $count = 0;

        foreach ($records as $record) {
            $record->update(['status' => 'invalidated']);
            $this->clearCacheStatus($record->sdk_instance_id);
            $count++;
        }

        SdkCacheInvalidationLog::create([
            'trigger_type' => 'manual',
            'reason' => "批量失效: {$reason}",
            'affected_cache_keys' => $records->pluck('cache_key_hash')->toArray(),
            'source' => 'admin',
            'triggered_by' => $userId,
        ]);

        return ['total' => $count, 'affected_instances' => $records->pluck('sdk_instance_id')->unique()->values()->toArray()];
    }

    /**
     * 处理过期缓存记录
     */
    public function processExpiredRecords(): int
    {
        // 将超过宽限期的缓存标记为 expired
        $expired = SdkCacheRecord::where('status', 'active')
            ->where('grace_expires_at', '<=', now())
            ->get();

        $count = 0;
        foreach ($expired as $record) {
            $record->update(['status' => 'expired']);
            $this->clearCacheStatus($record->sdk_instance_id);
            $count++;
        }

        return $count;
    }

    /**
     * 处理被篡改缓存（对接 M2-17 完整性自检）
     */
    public function handleTamperedCache(int $recordId): array
    {
        $record = SdkCacheRecord::findOrFail($recordId);
        $record->update(['status' => 'tampered']);
        $this->clearCacheStatus($record->sdk_instance_id);

        return [
            'record' => $record,
            'message' => "SDK实例 {$record->sdk_instance_id} 的缓存已被标记为篡改",
        ];
    }

    /**
     * 清除服务端缓存状态
     */
    private function clearCacheStatus(string $sdkInstanceId): void
    {
        Cache::forget(sprintf(self::CACHE_KEY_PREFIX, $sdkInstanceId));
    }
}
