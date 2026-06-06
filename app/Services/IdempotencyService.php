<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 幂等性服务
 *
 * 用于保证 API 请求的幂等性，防止重复处理。
 * 基于 Idempotency-Key 头 + Redis 缓存实现。
 *
 * 工作机制：
 * 1. 客户端在请求头中传入 Idempotency-Key（UUID v4）
 * 2. 服务端检查该 key 是否已处理过
 *    - 已处理 → 直接返回缓存的结果
 *    - 未处理 → 处理请求 → 缓存结果 → 返回
 * 3. 缓存 TTL 默认 24 小时（可配置）
 */
class IdempotencyService
{
    /**
     * 缓存前缀
     */
    const CACHE_PREFIX = 'idempotency:';

    /**
     * 默认缓存 TTL（秒）
     */
    const DEFAULT_TTL = 86400; // 24 小时

    /**
     * 获取幂等性 Key 的缓存键
     */
    protected function cacheKey(string $idempotencyKey): string
    {
        return self::CACHE_PREFIX . $idempotencyKey;
    }

    /**
     * 检查幂等性 Key 是否已存在
     *
     * @return array|null 返回之前缓存的结果，或 null 表示未处理过
     */
    public function get(string $idempotencyKey): ?array
    {
        $cached = Cache::get($this->cacheKey($idempotencyKey));

        if ($cached !== null) {
            Log::debug('幂等性命中', ['key' => $idempotencyKey]);
            return is_array($cached) ? $cached : null;
        }

        return null;
    }

    /**
     * 保存幂等性处理结果
     */
    public function save(string $idempotencyKey, array $result, ?int $ttl = null): void
    {
        Cache::put(
            $this->cacheKey($idempotencyKey),
            $result,
            $ttl ?? self::DEFAULT_TTL,
        );

        Log::debug('幂等性已保存', ['key' => $idempotencyKey, 'ttl' => $ttl ?? self::DEFAULT_TTL]);
    }

    /**
     * 生成新的幂等性 Key
     */
    public function generateKey(): string
    {
        return (string) Str::uuid();
    }

    /**
     * 验证 Idempotency-Key 格式是否合法（UUID v4）
     */
    public function isValidKey(?string $key): bool
    {
        if (empty($key)) {
            return false;
        }

        return Str::isUuid($key);
    }

    /**
     * 清除幂等性缓存
     */
    public function forget(string $idempotencyKey): bool
    {
        return Cache::forget($this->cacheKey($idempotencyKey));
    }
}
