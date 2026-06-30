<?php

namespace Database\Factories;

use App\Models\PricingPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

class PricingPlanFactory extends Factory
{
    protected $model = PricingPlan::class;

    public function definition(): array
    {
        return [
            'slug' => $this->faker->unique()->slug(1),
            'name' => $this->faker->words(2, true),
            'description' => $this->faker->sentence(),
            'price_monthly' => $this->faker->randomFloat(2, 10, 500),
            'price_quarterly' => $this->faker->randomFloat(2, 20, 1000),
            'price_yearly' => $this->faker->randomFloat(2, 100, 5000),
            'is_public' => true,
            'is_active' => true,
            'sort_order' => 0,
            'trial_days' => 0,
        ];
    }
}
