<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperCloudMarketplaceNotification
 */
class CloudMarketplaceNotification extends Model
{
    protected $table = 'cloud_marketplace_notifications';

    protected $fillable = [
        'tenant_id', 'marketplace', 'notification_type',
        'raw_payload', 'status', 'error_message', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
        ];
    }
}
