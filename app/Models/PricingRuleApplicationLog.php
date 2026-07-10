<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @mixin IdeHelperPricingRuleApplicationLog
 */
class PricingRuleApplicationLog extends Model
{
    use HasFactory;

    protected $table = 'pricing_rule_application_logs';

    protected $fillable = [
        'rule_id',
        'appliable_type',
        'appliable_id',
        'context_type',
        'original_price',
        'final_price',
        'discount_amount',
        'applied_rules',
    ];

    protected function casts(): array
    {
        return [
            'original_price' => 'decimal:2',
            'final_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'applied_rules' => 'array',
        ];
    }

    public function appliable(): MorphTo
    {
        return $this->morphTo();
    }
}
