<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @mixin IdeHelperRegionalSalesRestriction
 */
class RegionalSalesRestriction extends Model
{
    protected $table = 'regional_sales_restrictions';

    protected $fillable = [
        'tenant_id', 'restrictable_type', 'restrictable_id',
        'region_key', 'is_allowed', 'restriction_type',
        'restriction_value', 'reason', 'override_by',
        'effective_at', 'expires_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_allowed' => 'boolean',
            'is_active' => 'boolean',
            'effective_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function restrictable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * 检查限制是否生效
     */
    public function isEffective(): bool
    {
        if (!$this->is_active) {
            return false;
        }
        if ($this->effective_at && now()->lt($this->effective_at)) {
            return false;
        }
        if ($this->expires_at && now()->gt($this->expires_at)) {
            return false;
        }
        return true;
    }
}
