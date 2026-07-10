<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperRegionSyncLog
 */
class RegionSyncLog extends Model
{
    protected $table = 'region_sync_logs';

    protected $fillable = [
        'source_region', 'target_region', 'data_type',
        'status', 'items_count', 'items_synced', 'items_failed',
        'error_message', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'items_count' => 'integer',
            'items_synced' => 'integer',
            'items_failed' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    const STATUSES = ['pending', 'running', 'completed', 'failed', 'cancelled'];
    const DATA_TYPES = ['license', 'customer', 'product', 'audit_log'];
}
