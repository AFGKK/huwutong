<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EarningsAccount extends Model
{
    protected $fillable = [
        'tenant_id', 'user_id', 'type',
        'pending_balance', 'available_balance',
        'total_withdrawn', 'frozen_amount', 'status',
    ];

    protected function casts(): array
    {
        return [
            'pending_balance' => 'decimal:2',
            'available_balance' => 'decimal:2',
            'total_withdrawn' => 'decimal:2',
            'frozen_amount' => 'decimal:2',
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
