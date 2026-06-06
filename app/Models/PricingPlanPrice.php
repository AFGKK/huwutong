<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingPlanPrice extends Model
{
    protected $fillable = [
        'pricing_plan_id', 'currency', 'price', 'setup_fee', 'trial_price',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'setup_fee' => 'decimal:2',
            'trial_price' => 'decimal:2',
        ];
    }

    public function pricingPlan(): BelongsTo
    {
        return $this->belongsTo(PricingPlan::class);
    }

    /**
     * 获取格式化价格
     */
    public function formattedPrice(): string
    {
        return ExchangeRate::format((float) $this->price, $this->currency);
    }

    /**
     * 获取格式化设置费
     */
    public function formattedSetupFee(): string
    {
        return ExchangeRate::format((float) $this->setup_fee, $this->currency);
    }
}
