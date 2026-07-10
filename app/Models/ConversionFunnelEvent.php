<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperConversionFunnelEvent
 */
class ConversionFunnelEvent extends Model
{
    protected $table = 'conversion_funnel_events';

    protected $fillable = [
        'tenant_id', 'customer_id', 'license_id', 'stage', 'event',
        'metadata', 'source', 'campaign', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
