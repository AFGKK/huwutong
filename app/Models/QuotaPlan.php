<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperQuotaPlan
 */
class QuotaPlan extends Model
{
    protected $table = 'quota_plans';

    protected $fillable = [
        'name', 'slug', 'description', 'limits', 'features',
        'tier', 'price_monthly', 'price_yearly',
        'is_active', 'is_default',
    ];

    protected function casts(): array
    {
        return [
            'limits' => 'array',
            'features' => 'array',
            'price_monthly' => 'decimal:2',
            'price_yearly' => 'decimal:2',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    const TIERS = ['free', 'starter', 'business', 'enterprise', 'custom'];

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class, 'quota_plan_id');
    }

    /**
     * 获取默认配额方案的默认 limits
     */
    public static function defaultLimits(): array
    {
        return [
            'licenses_max' => 50,
            'devices_max' => 500,
            'users_max' => 10,
            'api_keys_max' => 5,
            'storage_mb' => 1024,
            'bandwidth_gb' => 50,
            'monthly_api_calls' => 100000,
            'seats_total' => 100,
        ];
    }
}
