<?php

namespace Database\Factories;

use App\Models\PlanUpgradePath;
use App\Models\PricingPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlanUpgradePathFactory extends Factory
{
    protected $model = PlanUpgradePath::class;

    public function definition(): array
    {
        return [
            'from_plan_id' => PricingPlan::factory(),
            'to_plan_id' => PricingPlan::factory(),
            'proration_ratio' => $this->faker->randomFloat(4, 0, 1),
            'additional_fee' => 0,
            'allow_downgrade' => false,
            'is_active' => true,
        ];
    }
}
