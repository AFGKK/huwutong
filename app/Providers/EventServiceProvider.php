<?php

namespace App\Providers;

use App\Events\LicenseAboutToExpire;
use App\Events\LicenseStatusChanged;
use App\Listeners\DispatchLicenseEvent;
use App\Listeners\LogLicenseStatusChanged;
use App\Listeners\SyncLicenseFeatureFlags;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        LicenseStatusChanged::class => [
            LogLicenseStatusChanged::class,
            DispatchLicenseEvent::class,
            SyncLicenseFeatureFlags::class,  // 状态变更 → 联动 Feature Flag
        ],
        LicenseAboutToExpire::class => [
            DispatchLicenseEvent::class,
        ],
    ];

    public function boot(): void
    {
        parent::boot();
    }
}
