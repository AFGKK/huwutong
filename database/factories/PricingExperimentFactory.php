<?php

namespace Database\Factories;

use App\Models\PricingExperiment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PricingExperimentFactory extends Factory
{
    protected $model = PricingExperiment::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->sentence(3),
            'slug' => fake()->slug(3) . '-' . strtolower(fake()->bothify('??##??')),
            'description' => fake()->paragraph(),
            'status' => 'draft',
            'experiment_type' => fake()->randomElement(['pricing', 'discount', 'bundle', 'tier', 'promotion']),
            'target_metric' => fake()->randomElement(['conversion', 'revenue', 'retention', 'profit']),
            'confidence_level' => 95,
            'minimum_sample_size' => 100,
            'traffic_split' => 50,
            'control_config' => ['price_monthly' => 99, 'price_yearly' => 990],
            'treatment_config' => ['adjustment_type' => 'percentage', 'adjustment_value' => -10],
            'created_by' => User::factory(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn(array $attrs) => ['status' => 'draft']);
    }

    public function running(): static
    {
        return $this->state(fn(array $attrs) => [
            'status' => 'running',
            'starts_at' => now()->subDays(5),
            'sample_size' => 200,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn(array $attrs) => [
            'status' => 'completed',
            'starts_at' => now()->subDays(30),
            'ends_at' => now()->subDays(1),
            'sample_size' => 500,
            'results' => [
                'control_conversions' => 20,
                'treatment_conversions' => 28,
                'control_rate' => '0.08',
                'treatment_rate' => '0.112',
                'improvement' => '+40%',
                'z_score' => 1.96,
                'p_value' => 0.05,
                'significant' => true,
            ],
        ]);
    }
}
