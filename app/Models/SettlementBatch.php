<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperSettlementBatch
 */
class SettlementBatch extends Model
{
    protected $fillable = [
        'settlement_cycle_id',
        'tenant_id',
        'batch_no',
        'channel',
        'total_amount',
        'total_fee',
        'net_amount',
        'item_count',
        'status',
        'notes',
        'metadata',
        'approved_by',
        'approved_at',
        'processed_at',
        'completed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'total_fee' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'metadata' => 'json',
            'approved_at' => 'datetime',
            'processed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public const CHANNELS = ['bank', 'alipay', 'wechat', 'paypal', 'balance'];
    public const STATUSES = ['draft', 'pending_approval', 'approved', 'processing', 'completed', 'failed', 'cancelled'];

    public function settlementCycle(): BelongsTo
    {
        return $this->belongsTo(SettlementCycle::class, 'settlement_cycle_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SettlementBatchItem::class);
    }

    public function scopeByTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['draft', 'pending_approval', 'approved', 'processing']);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}
