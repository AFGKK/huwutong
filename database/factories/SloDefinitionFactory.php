<?php

namespace Database\Factories;

use App\Models\SloDefinition;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class SloDefinitionFactory extends Factory
{
    protected $model = SloDefinition::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->word() . ' SLO',
            'slug' => fake()->unique()->slug(2),
            'description' => fake()->sentence(),
            'service_name' => fake()->randomElement(['api', 'web', 'worker', 'cron']),
            'sli_type' => fake()->randomElement(['latency', 'availability', 'error_rate', 'throughput']),
            'target' => fake()->randomElement([99.9, 99.5, 99.0, 95.0]),
            'window_days' => 30,
            'burn_rate_alerts' => [
                ['window_hours' => 1, 'threshold' => 2],
                ['window_hours' => 6, 'threshold' => 5],
            ],
            'is_active' => true,
            'total_requests' => 0,
            'good_requests' => 0,
            'remaining_budget' => fn(array $attrs) => ($attrs['window_days'] ?? 30) * 24 * 60 * (1 - ($attrs['target'] ?? 99.9) / 100),
        ];
    }

    public function active(): static
    {
        return $this->state(['is_active' => true]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function latency(): static
    {
        return $this->state(['sli_type' => 'latency', 'target' => 99.9]);
    }

    public function availability(): static
    {
        return $this->state(['sli_type' => 'availability', 'target' => 99.5]);
    }

    public function exhausted(): static
    {
        return $this->state(['remaining_budget' => 0, 'current_sli' => 85.0]);
    }
}
