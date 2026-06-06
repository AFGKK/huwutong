<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SLA 事件审计日志
 */
class SlaAuditEvent extends Model
{
    const EVENT_TIER_ASSIGNED = 'tier_assigned';
    const EVENT_TIER_CHANGED = 'tier_changed';
    const EVENT_TIER_EXPIRED = 'tier_expired';
    const EVENT_LIMIT_EXCEEDED = 'limit_exceeded';
    const EVENT_SLA_BREACHED = 'sla_breached';

    protected $fillable = [
        'tenant_id', 'customer_id', 'sla_tier_id',
        'event_type', 'description', 'context',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
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
}
