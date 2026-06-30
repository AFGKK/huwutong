<?php

namespace Database\Factories;

use App\Models\PromotionRule;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class PromotionRuleFactory extends Factory
{
    protected $model = PromotionRule::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->words(3, true),
            'slug' => $this->faker->unique()->slug(3),
            'type' => 'amount_off',
            'condition_type' => 'subtotal',
            'condition_value' => 0,
            'discount_value' => 50,
            'max_discount' => null,
            'min_order_amount' => 0,
            'applicable_products' => null,
            'applicable_categories' => null,
            'excluded_products' => null,
            'stackable_with_coupon' => false,
            'stackable_with_other_rules' => false,
            'priority' => 0,
            'usage_limit' => null,
            'usage_limit_per_customer' => null,
            'usage_count' => 0,
            'budget' => null,
            'budget_spent' => 0,
            'starts_at' => null,
            'ends_at' => null,
            'tiers' => null,
            'buy_quantity' => null,
            'free_quantity' => null,
            'free_products' => null,
            'status' => 'draft',
            'created_by' => null,
        ];
    }
}
