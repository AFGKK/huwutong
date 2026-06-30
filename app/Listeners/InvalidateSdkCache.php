<?php

namespace App\Listeners;

use App\Services\CacheInvalidationPushService;
use Illuminate\Support\Facades\Log;

/**
 * 通用缓存失效监听器 — 各种模型变更后推送缓存失效通知
 */
class InvalidateSdkCache
{
    protected CacheInvalidationPushService $pushService;

    public function __construct(CacheInvalidationPushService $pushService)
    {
        $this->pushService = $pushService;
    }

    /**
     * 处理 Feature Flag 变更
     */
    public function onFeatureFlagChanged(string $flagKey, int $tenantId, bool $newValue): void
    {
        $this->pushService->invalidate(
            tenantId: $tenantId,
            invalidationKey: "featureflag.{$flagKey}",
            type: 'feature_flag',
            context: [
                'flag_key' => $flagKey,
                'new_value' => $newValue,
            ],
        );
    }

    /**
     * 处理产品配置变更
     */
    public function onProductConfigChanged(int $tenantId, string $configKey, $oldValue = null, $newValue = null): void
    {
        $this->pushService->invalidate(
            tenantId: $tenantId,
            invalidationKey: "product_config.{$configKey}",
            type: 'product_config',
            context: [
                'config_key' => $configKey,
                'old_value' => $oldValue,
                'new_value' => $newValue,
            ],
        );
    }
}
