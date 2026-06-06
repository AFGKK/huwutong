<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'customer_id' => Customer::factory(),
            'subject' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'priority' => fake()->randomElement(['low', 'medium', 'high']),
            'status' => 'open',
            'source' => 'portal',
        ];
    }
}
