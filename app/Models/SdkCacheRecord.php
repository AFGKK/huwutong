<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * M2-17b SDK缓存记录（服务端追踪）
 *
 * @property int $id
 * @property string $sdk_instance_id
 * @property string|null $language
 * @property string|null $sdk_version
 * @property string|null $machine_id
 * @property string|null $license_key
 * @property string $cache_key_hash
 * @property string $status active|expired|invalidated|tampered
 * @property bool $is_offline
 * @mixin IdeHelperSdkCacheRecord
 */
class SdkCacheRecord extends Model
{
    protected $fillable = [
        'sdk_instance_id', 'language', 'sdk_version', 'machine_id',
        'license_key', 'cache_key_hash', 'status',
        'cached_at', 'expires_at', 'grace_expires_at', 'last_access_at',
        'access_count', 'last_verification_result', 'is_offline', 'notes',
    ];

    protected $casts = [
        'cached_at' => 'datetime',
        'expires_at' => 'datetime',
        'grace_expires_at' => 'datetime',
        'last_access_at' => 'datetime',
        'is_offline' => 'boolean',
    ];

    public const STATUSES = ['active', 'expired', 'invalidated', 'tampered'];

    public function scopeActive($q) { return $q->where('status', 'active'); }
    public function scopeByInstance($q, $id) { return $q->where('sdk_instance_id', $id); }
    public function scopeByLicense($q, $key) { return $q->where('license_key', $key); }
    public function scopeOffline($q) { return $q->where('is_offline', true); }
    public function scopeExpiring($q) { return $q->where('expires_at', '<=', now()); }
}
