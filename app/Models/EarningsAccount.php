<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperEarningsAccount
 */
class EarningsAccount extends Model
{
    use HasFactory;
    protected $fillable = [
        'tenant_id', 'user_id', 'type',
        'pending_balance', 'available_balance',
        'total_withdrawn', 'frozen_amount', 'status',
        'metadata',
        'last_settlement_at', 'next_settlement_at', 'lifetime_settled',
    ];

    protected function casts(): array
    {
        return [
            'pending_balance' => 'decimal:2',
            'available_balance' => 'decimal:2',
            'total_withdrawn' => 'decimal:2',
            'frozen_amount' => 'decimal:2',
            'lifetime_settled' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function commissions()
    {
        return $this->hasMany(Commission::class);
    }

    public function withdrawals()
    {
        return $this->hasMany(Withdrawal::class);
    }
}
