<?php

namespace App\Listeners;

use App\Events\LicenseStatusChanged;
use App\Services\FeatureFlagService;
use Illuminate\Support\Facades\Log;

class SyncLicenseFeatureFlags
{
    /**
     * License 状态变更时，联动同步 Feature Flag
     *
     * 当 License 进入不可用状态（过期/撤销/黑名单/退款/挂起/冻结），
     * 自动禁用对应的功能模块
     */
    public function handle(LicenseStatusChanged $event): void
    {
        try {
            $service = app(FeatureFlagService::class);
            $service->onLicenseStatusChanged($event->license, $event->newStatus);
        } catch (\Throwable $e) {
            Log::error('Feature Flag 联动失败', [
                'license_id' => $event->license->id,
                'new_status' => $event->newStatus,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
