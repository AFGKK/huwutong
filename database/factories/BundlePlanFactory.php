<?php

namespace Database\Factories;

use App\Models\BundlePlan;
use App\Models\PricingPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

class BundlePlanFactory extends Factory
{
    protected $model = BundlePlan::class;

    public function definition(): array
    {
        return [
            'parent_plan_id' => PricingPlan::factory(),
            'included_plan_id' => PricingPlan::factory(),
            'type' => 'optional',
            'discount_percent' => $this->faker->randomFloat(2, 0, 50),
            'fixed_discount' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
