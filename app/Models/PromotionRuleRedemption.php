<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperPromotionRuleRedemption
 */
class PromotionRuleRedemption extends Model
{
    protected $fillable = [
        'promotion_rule_id', 'tenant_id', 'customer_id', 'invoice_id',
        'original_amount', 'discount_amount', 'final_amount',
        'currency', 'tier_applied', 'context',
    ];

    protected function casts(): array
    {
        return [
            'original_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'final_amount' => 'decimal:2',
            'tier_applied' => 'array',
            'context' => 'array',
        ];
    }

    public function promotionRule(): BelongsTo
    {
        return $this->belongsTo(PromotionRule::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
