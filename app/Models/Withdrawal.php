<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Withdrawal extends Model
{
    protected $fillable = [
        'earnings_account_id', 'amount', 'channel',
        'channel_account', 'status', 'proof', 'remark', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'completed_at' => 'datetime',
        ];
    }

    public function earningsAccount()
    {
        return $this->belongsTo(EarningsAccount::class);
    }
}
