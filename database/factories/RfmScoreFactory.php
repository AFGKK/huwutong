<?php

namespace Database\Factories;

use App\Models\RfmScore;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class RfmScoreFactory extends Factory
{
    protected $model = RfmScore::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'customer_id' => 1,
            'recency_days' => $this->faker->numberBetween(1, 90),
            'recency_score' => $this->faker->numberBetween(1, 5),
            'frequency_count' => $this->faker->numberBetween(1, 50),
            'frequency_score' => $this->faker->numberBetween(1, 5),
            'monetary_total' => $this->faker->randomFloat(2, 100, 10000),
            'monetary_score' => $this->faker->numberBetween(1, 5),
            'rfm_total' => $this->faker->numberBetween(3, 15),
            'rfm_segment' => $this->faker->randomElement(['Champions', 'Loyal', 'Recent', 'Big Spenders', 'Promising', 'Need Attention', 'About to Sleep', 'Lost']),
        ];
    }
}
