<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperPrepaidBalance
 */
class PrepaidBalance extends Model
{
    protected $fillable = [
        'tenant_id', 'customer_id', 'currency',
        'balance', 'total_recharged', 'total_consumed',
        'status', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'total_recharged' => 'decimal:2',
            'total_consumed' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function transactions()
    {
        return $this->hasMany(PrepaidTransaction::class, 'customer_id', 'customer_id');
    }
}
