<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 客户 - SLA 等级关联
 *
 * @mixin IdeHelperCustomerSlaAssignment
 */
class CustomerSlaAssignment extends Model
{
    protected $fillable = [
        'tenant_id', 'customer_id', 'sla_tier_id',
        'assigned_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function slaTier(): BelongsTo
    {
        return $this->belongsTo(SlaTier::class);
    }

    /**
     * 是否已过期
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
