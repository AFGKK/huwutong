<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 提现记录
 *
 * @mixin IdeHelperCommissionPayout
 */
class CommissionPayout extends Model
{
    use HasFactory;
    protected $fillable = [
        'agent_id', 'amount', 'fee', 'net_amount', 'status',
        'payout_method', 'account_info', 'requested_at',
        'processed_at', 'transaction_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fee' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'requested_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
