<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperCustomerCurrencyPreference
 */
class CustomerCurrencyPreference extends Model
{
    protected $fillable = [
        'tenant_id', 'customer_id',
        'preferred_currency', 'display_currency', 'accepted_currencies',
    ];

    protected function casts(): array
    {
        return [
            'accepted_currencies' => 'array',
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

    /**
     * 是否接受指定货币
     */
    public function acceptsCurrency(string $currency): bool
    {
        $accepted = $this->accepted_currencies ?? [$this->preferred_currency];
        return in_array(strtoupper($currency), $accepted);
    }
}
