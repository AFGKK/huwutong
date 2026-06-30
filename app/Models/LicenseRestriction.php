<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $restrictable_type
 * @property int $restrictable_id
 * @property string $type ip_range / geo_fence
 * @property bool $is_active
 * @property string $action block / allow / audit
 * @property array|null $ip_ranges
 * @property array|null $ip_whitelist
 * @property array|null $ip_blacklist
 * @property array|null $allowed_countries
 * @property array|null $blocked_countries
 * @property string $unknown_location_action
 * @property string|null $description
 * @property int|null $created_by
 * @property int|null $approved_by
 * @property string|null $approved_at
 */
class LicenseRestriction extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'restrictable_type',
        'restrictable_id',
        'type',
        'is_active',
        'action',
        'ip_ranges',
        'ip_whitelist',
        'ip_blacklist',
        'allowed_countries',
        'blocked_countries',
        'unknown_location_action',
        'description',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'ip_ranges' => 'array',
            'ip_whitelist' => 'array',
            'ip_blacklist' => 'array',
            'allowed_countries' => 'array',
            'blocked_countries' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    public function restrictable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeForLicense($query, int $licenseId)
    {
        return $query->where('restrictable_type', 'license')->where('restrictable_id', $licenseId);
    }
}
