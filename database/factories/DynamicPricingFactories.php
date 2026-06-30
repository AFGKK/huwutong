<?php

namespace Database\Factories;

use App\Models\DynamicPricingRule;
use App\Models\PricingPlan;
use App\Models\PricingTier;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DynamicPricingRuleFactory extends Factory
{
    protected $model = DynamicPricingRule::class;

    public function definition(): array
    {
        $ruleTypes = ['volume', 'segment', 'time_seasonal', 'time_hourly', 'promotion', 'llm_optimized'];
        $targetTypes = ['plan', 'customer', 'segment', 'product'];
        $adjustmentTypes = ['percentage', 'fixed', 'override'];

        return [
            'name' => $this->faker->sentence(3),
            'slug' => Str::slug($this->faker->unique()->sentence(2)),
            'description' => $this->faker->sentence,
            'rule_type' => $this->faker->randomElement($ruleTypes),
            'target_type' => $this->faker->randomElement($targetTypes),
            'adjustment_type' => $this->faker->randomElement($adjustmentTypes),
            'adjustment_value' => $this->faker->randomFloat(2, 5, 50),
            'priority' => $this->faker->numberBetween(1, 1000),
            'stack_mode' => $this->faker->randomElement(['replace', 'add', 'multiply', 'compound']),
            'is_active' => true,
            'applied_count' => 0,
        ];
    }
}

class PricingTierFactory extends Factory
{
    protected $model = PricingTier::class;

    public function definition(): array
    {
        return [
            'pricing_plan_id' => PricingPlan::factory(),
            'name' => $this->faker->word() . ' Tier',
            'from_quantity' => 1,
            'to_quantity' => null,
            'unit_price' => $this->faker->randomFloat(2, 10, 200),
            'flat_fee' => 0,
            'is_active' => true,
            'sort_order' => 1,
        ];
    }
}
