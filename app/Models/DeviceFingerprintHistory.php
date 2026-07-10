<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperDeviceFingerprintHistory
 */
class DeviceFingerprintHistory extends Model
{
    protected $table = 'device_fingerprint_history';

    protected $fillable = [
        'device_id', 'tenant_id', 'license_id',
        'fingerprint', 'fingerprint_version',
        'mac', 'cpu_id', 'motherboard', 'disk_sn', 'system_uuid',
        'components', 'drift_type', 'changed_components', 'total_components',
        'similarity_score', 'is_baseline', 'auto_accepted',
        'source', 'notes',
    ];

    protected $casts = [
        'components' => 'array',
        'is_baseline' => 'boolean',
        'auto_accepted' => 'boolean',
        'fingerprint_version' => 'integer',
        'changed_components' => 'integer',
        'total_components' => 'integer',
        'similarity_score' => 'decimal:2',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }
}
