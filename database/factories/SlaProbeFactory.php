<?php

namespace Database\Factories;

use App\Models\SlaProbe;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class SlaProbeFactory extends Factory
{
    protected $model = SlaProbe::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->word() . ' 拨测',
            'url' => fake()->url(),
            'method' => 'GET',
            'headers' => null,
            'body' => null,
            'expected_status' => '200-299',
            'expected_body_contains' => null,
            'timeout_seconds' => 10,
            'interval_minutes' => 5,
            'sla_targets' => ['max_response_time' => 500],
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function unhealthy(): static
    {
        return $this->state([
            'last_status' => 'down',
            'consecutive_failures' => 5,
        ]);
    }

    public function healthy(): static
    {
        return $this->state([
            'last_status' => 'up',
            'last_response_time_ms' => 120,
            'consecutive_failures' => 0,
        ]);
    }
}
