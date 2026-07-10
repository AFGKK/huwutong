<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperTokenAlert
 */
class TokenAlert extends Model
{
    protected $fillable = [
        'tenant_id', 'token_budget_id', 'type',
        'threshold_pct', 'current_spend', 'budget_limit',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(TokenBudget::class, 'token_budget_id');
    }

    public function scopeUnresolved($query)
    {
        return $query->whereNull('resolved_at');
    }

    public function scopeByTenant($query, ?int $tenantId)
    {
        return $tenantId ? $query->where('tenant_id', $tenantId) : $query;
    }
}
