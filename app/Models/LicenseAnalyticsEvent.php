<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperLicenseAnalyticsEvent
 */
class LicenseAnalyticsEvent extends Model
{
    use HasFactory;
    protected $fillable = [
        'license_id', 'tenant_id',
        'event_type', 'ip_address',
        'country_code', 'country_name', 'city',
        'latitude', 'longitude',
        'platform', 'sdk_version', 'sdk_language', 'sdk_arch',
        'violation_type', 'violation_detail',
        'metadata', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
