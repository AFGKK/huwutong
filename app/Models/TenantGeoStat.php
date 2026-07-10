<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperTenantGeoStat
 */
class TenantGeoStat extends Model
{
    protected $fillable = [
        'tenant_id', 'country_code', 'country', 'region',
        'device_count', 'activation_count', 'stat_date',
    ];

    protected $casts = [
        'stat_date' => 'date',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
