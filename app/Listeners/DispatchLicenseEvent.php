<?php

namespace App\Listeners;

use App\Services\EventBus;

class DispatchLicenseEvent
{
    /**
     * 通过 EventBus 将 License 事件分发到所有渠道
     */
    public function handle(object $event): void
    {
        app(EventBus::class)->dispatch($event);
    }
}
