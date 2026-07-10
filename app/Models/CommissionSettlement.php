<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 佣金结算记录
 *
 * 每笔符合条件的发票生成一条结算记录。
 *
 * @mixin IdeHelperCommissionSettlement
 */
class CommissionSettlement extends Model
{
    use HasFactory;
    protected $fillable = [
        'agent_id', 'subscription_id', 'invoice_id', 'period', 'status',
        'invoice_amount', 'commission_rate', 'commission_amount',
        'rate_type', 'settlement_type',
        'settled_at', 'released_at', 'notes',
        'settlement_batch_id', 'settlement_cycle_id',
        'fee', 'net_amount', 'payout_method',
    ];

    protected function casts(): array
    {
        return [
            'invoice_amount' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'fee' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'settled_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeReleased($query)
    {
        return $query->where('status', 'released');
    }

    public function scopeByPeriod($query, string $period)
    {
        return $query->where('period', $period);
    }
}
