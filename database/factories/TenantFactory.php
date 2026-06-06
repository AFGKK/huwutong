<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TenantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'domain' => fake()->unique()->domainName(),
            'subscription_plan' => 'standard',
            'status' => 'active',
            'data_region' => 'cn',
        ];
    }
}
