<?php

namespace App\Listeners;

use App\Events\LicenseStatusChanged;
use App\Models\Log;
use Illuminate\Support\Facades\Request;

class LogLicenseStatusChanged
{
    public function handle(LicenseStatusChanged $event): void
    {
        Log::create([
            'tenant_id' => $event->license->tenant_id,
            'user_id' => auth()->id(),
            'license_id' => $event->license->id,
            'type' => 'audit',
            'action' => 'license.status_changed',
            'description' => sprintf(
                'License [%s] 状态变更: %s → %s',
                $event->license->license_key,
                $event->oldStatus,
                $event->newStatus,
            ),
            'payload' => [
                'license_id' => $event->license->id,
                'license_key' => $event->license->license_key,
                'old_status' => $event->oldStatus,
                'new_status' => $event->newStatus,
                'reason' => $event->reason,
            ],
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }
}
