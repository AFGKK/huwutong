<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeployEnvironmentFactory extends Factory
{
    protected $model = \App\Models\DeployEnvironment::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->randomElement(['Production', 'Staging', 'Development']),
            'slug' => $this->faker->unique()->word(),
            'server_type' => $this->faker->randomElement(['self-hosted', 'cloud', 'kubernetes']),
            'is_protected' => true,
            'is_active' => true,
        ];
    }
}
