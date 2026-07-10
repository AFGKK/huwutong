<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperPaymentSecurityLog
 */
class PaymentSecurityLog extends Model
{
    protected $fillable = [
        'order_id', 'check_type', 'passed', 'details',
        'risk_level', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'passed' => 'boolean',
            'details' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
