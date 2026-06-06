<?php

namespace App\Services;

use App\Models\FeatureFlag;
use App\Models\License;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Feature Flag 细粒度授权服务
 *
 * 控制 License 可用的功能模块：
 * - 按产品关联 Feature Flag
 * - 与 License 状态机联动（过期/冻结/撤销自动关闭）
 * - 支持缓存加速
 * - 支持 License 级别的 metadata 覆盖
 */
class FeatureFlagService
{
    /**
     * 缓存前缀
     */
    const CACHE_PREFIX = 'feature_flag:';

    /**
     * 缓存 TTL（秒）
     */
    const CACHE_TTL = 300; // 5 分钟

    /**
     * License 状态变更时禁用的 Feature Flag 列表
     * 不同不可用状态对应的禁用策略
     */
    const DISABLED_FEATURES_BY_STATUS = [
        'expired' => ['*'],       // 过期 → 全部禁用
        'revoked' => ['*'],       // 撤销 → 全部禁用
        'blacklisted' => ['*'],   // 黑名单 → 全部禁用
        'refunded' => ['*'],      // 退款 → 全部禁用
        'suspended' => ['*'],     // 挂起 → 全部禁用
        'frozen' => ['*'],        // 冻结 → 全部禁用
        'pending' => ['api_access', 'advanced_features'], // 待激活 → 仅保留基础功能
    ];

    /**
     * 获取产品的所有 Feature Flag（含状态）
     */
    public function getProductFeatures(Product $product): array
    {
        $cacheKey = self::CACHE_PREFIX . "product:{$product->id}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($product) {
            return $product->featureFlags()
                ->withPivot('is_active')
                ->get()
                ->map(fn(FeatureFlag $flag) => [
                    'key' => $flag->key,
                    'name' => $flag->name,
                    'description' => $flag->description,
                    'globally_active' => $flag->is_active,
                    'product_active' => (bool) $flag->pivot->is_active,
                ])
                ->toArray();
        });
    }

    /**
     * 检查 License 是否有某个功能的权限
     *
     * 检查链：
     * 1. License 状态是否可用（状态机）
     * 2. License 是否过期
     * 3. 产品是否关联了该功能
     * 4. 功能全局开关
     * 5. 产品下该功能开关
     * 6. License metadata 中是否有功能覆盖
     */
    public function hasFeature(License $license, string $featureKey): bool
    {
        // 1. 检查 License 状态
        if (! $this->isLicenseUsable($license)) {
            return false;
        }

        $product = $license->product;
        if (! $product) {
            return false;
        }

        // 2. 从缓存获取产品的功能列表
        $features = $this->getProductFeatures($product);

        // 3. 查找指定功能
        $feature = collect($features)->firstWhere('key', $featureKey);

        if (! $feature) {
            return false;
        }

        // 4. 检查全局开关
        if (! $feature['globally_active']) {
            return false;
        }

        // 5. 检查产品下功能开关
        if (! $feature['product_active']) {
            return false;
        }

        // 6. 检查 License metadata 中的功能覆盖
        $metadata = $license->metadata;
        if ($metadata && isset($metadata['features'][$featureKey])) {
            return (bool) $metadata['features'][$featureKey];
        }

        return true;
    }

    /**
     * 批量检查 License 的多个功能权限
     */
    public function checkFeatures(License $license, array $featureKeys): array
    {
        $result = [];
        foreach ($featureKeys as $key) {
            $result[$key] = $this->hasFeature($license, $key);
        }
        return $result;
    }

    /**
     * 获取 License 所有可用的功能列表
     */
    public function getLicenseFeatures(License $license): array
    {
        $product = $license->product;
        if (! $product) {
            return [];
        }

        $features = $this->getProductFeatures($product);

        return array_map(function ($feature) use ($license) {
            return [
                'key' => $feature['key'],
                'name' => $feature['name'],
                'enabled' => $this->hasFeature($license, $feature['key']),
            ];
        }, $features);
    }

    /**
     * License 状态变更时联动禁用 Feature
     *
     * 返回被禁用的功能列表
     */
    public function onLicenseStatusChanged(License $license, string $newStatus): array
    {
        $disabledFeatures = self::DISABLED_FEATURES_BY_STATUS[$newStatus] ?? [];
        $product = $license->product;

        if (empty($disabledFeatures) || ! $product) {
            return [];
        }

        // 清除缓存，让下次检查时重新计算
        $this->clearProductCache($product);

        if ($disabledFeatures === ['*']) {
            Log::info('Feature Flag 联动: License 状态变更导致全部功能禁用', [
                'license_id' => $license->id,
                'license_key' => $license->license_key,
                'new_status' => $newStatus,
            ]);
            return ['*'];
        }

        Log::info('Feature Flag 联动: License 状态变更导致部分功能禁用', [
            'license_id' => $license->id,
            'license_key' => $license->license_key,
            'new_status' => $newStatus,
            'disabled_features' => $disabledFeatures,
        ]);

        return $disabledFeatures;
    }

    /**
     * 将功能分配到产品
     */
    public function assignFeatureToProduct(Product $product, FeatureFlag $feature, bool $isActive = true): void
    {
        $product->featureFlags()->syncWithoutDetaching([
            $feature->id => ['is_active' => $isActive],
        ]);

        $this->clearProductCache($product);
    }

    /**
     * 更新产品下功能的开关状态
     */
    public function updateProductFeature(Product $product, FeatureFlag $feature, bool $isActive): void
    {
        $product->featureFlags()->updateExistingPivot($feature->id, [
            'is_active' => $isActive,
        ]);

        $this->clearProductCache($product);
    }

    /**
     * 从产品移除功能
     */
    public function removeFeatureFromProduct(Product $product, FeatureFlag $feature): void
    {
        $product->featureFlags()->detach($feature->id);
        $this->clearProductCache($product);
    }

    /**
     * 清除产品的功能缓存
     */
    public function clearProductCache(?Product $product = null): void
    {
        if ($product) {
            Cache::forget(self::CACHE_PREFIX . "product:{$product->id}");
        }
    }

    /**
     * 判断 License 状态是否可用（与状态机联动）
     *
     * 比 LicenseStatus::isUsable() 更严格：
     * Suspended（挂起）被视为不可用
     */
    protected function isLicenseUsable(License $license): bool
    {
        $status = \App\Enums\LicenseStatus::tryFrom($license->status);

        if (! $status) {
            return false;
        }

        // 只有 Active 和 Frozen 视为可用
        if (! in_array($status, [
            \App\Enums\LicenseStatus::Active,
            \App\Enums\LicenseStatus::Frozen,
        ], true)) {
            return false;
        }

        // 检查是否过期
        if ($license->expires_at && $license->expires_at->isPast()) {
            return false;
        }

        return true;
    }
}
