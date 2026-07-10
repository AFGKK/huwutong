<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperTokenBudget
 */
class TokenBudget extends Model
{
    protected $fillable = [
        'tenant_id', 'period', 'budget_limit',
        'alert_threshold_1', 'alert_threshold_2', 'alert_threshold_3',
        'hard_cap', 'is_active',
    ];

    protected $casts = [
        'hard_cap' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(TokenAlert::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByTenant($query, ?int $tenantId)
    {
        if ($tenantId) {
            return $query->where('tenant_id', $tenantId);
        }
        return $query->whereNull('tenant_id'); // 全局预算
    }
}
