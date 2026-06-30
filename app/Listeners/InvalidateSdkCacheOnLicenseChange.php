<?php

namespace App\Listeners;

use App\Events\LicenseStatusChanged;
use App\Services\CacheInvalidationPushService;

class InvalidateSdkCacheOnLicenseChange
{
    protected CacheInvalidationPushService $pushService;

    public function __construct(CacheInvalidationPushService $pushService)
    {
        $this->pushService = $pushService;
    }

    /**
     * License 状态变更 → 通知 SDK 清除缓存
     */
    public function handle(LicenseStatusChanged $event): void
    {
        $license = $event->license;

        $this->pushService->invalidate(
            tenantId: $license->tenant_id,
            invalidationKey: "license.status.{$license->id}",
            type: 'license_status',
            context: [
                'license_id' => $license->id,
                'license_key' => $license->license_key,
                'previous_status' => $event->previousStatus,
                'new_status' => $event->newStatus,
            ],
            immediate: false, // 合并推送，减少高峰期的 WS 消息量
        );
    }
}
