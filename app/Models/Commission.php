<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperCommission
 */
class Commission extends Model
{
    protected $fillable = [
        'earnings_account_id', 'order_id', 'amount',
        'rate', 'status', 'settled_at', 'frozen_until',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'rate' => 'decimal:2',
            'settled_at' => 'datetime',
            'frozen_until' => 'datetime',
        ];
    }

    public function earningsAccount()
    {
        return $this->belongsTo(EarningsAccount::class);
    }
}
