<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class WebhookEndpointFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->word() . ' Webhook',
            'url' => fake()->url(),
            'secret' => fake()->sha256(),
            'events' => ['license.active', 'license.expired'],
            'is_active' => true,
        ];
    }
}
