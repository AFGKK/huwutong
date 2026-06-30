<?php

namespace Database\Factories;

use App\Models\PersonalizedRecommendation;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class PersonalizedRecommendationFactory extends Factory
{
    protected $model = PersonalizedRecommendation::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'customer_id' => 1,
            'recommendation_type' => $this->faker->randomElement(['license', 'feature', 'addon', 'article']),
            'recommendable_id' => 0,
            'recommendable_type' => 'App\Models\PricingPlan',
            'reason' => $this->faker->sentence(),
            'score' => $this->faker->randomFloat(4, 0.5, 1.0),
            'source' => $this->faker->randomElement(['rule', 'rfm', 'behavior']),
            'is_dismissed' => false,
        ];
    }
}
