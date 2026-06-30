<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceGeoRecord extends Model
{
    protected $fillable = [
        'tenant_id', 'device_id', 'license_id', 'customer_id',
        'ip_address', 'country', 'country_code', 'region', 'city',
        'isp', 'latitude', 'longitude', 'timezone',
        'source', 'is_blacklisted',
    ];

    protected $casts = [
        'is_blacklisted' => 'boolean',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
