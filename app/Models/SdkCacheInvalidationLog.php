<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * M2-17b SDK缓存失效日志
 *
 * @property int $id
 * @property string|null $sdk_instance_id
 * @property string|null $license_key
 * @property string $trigger_type
 * @property string|null $reason
 * @property array|null $affected_cache_keys
 * @property string $source
 */
class SdkCacheInvalidationLog extends Model
{
    protected $fillable = [
        'sdk_instance_id', 'license_key', 'trigger_type', 'reason',
        'affected_cache_keys', 'source', 'triggered_by',
    ];

    protected $casts = [
        'affected_cache_keys' => 'json',
    ];

    public const TRIGGER_TYPES = [
        'license_change', 'device_change', 'feature_change', 'manual',
    ];

    public function triggerer(): BelongsTo { return $this->belongsTo(User::class, 'triggered_by'); }

    public function scopeByInstance($q, $id) { return $q->where('sdk_instance_id', $id); }
    public function scopeByLicense($q, $key) { return $q->where('license_key', $key); }
    public function scopeByTrigger($q, $type) { return $q->where('trigger_type', $type); }
}
