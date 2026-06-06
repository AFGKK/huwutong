<?php

namespace Database\Factories;

use App\Models\License;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeviceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'license_id' => License::factory(),
            'fingerprint' => '2:' . fake()->sha256(),
            'platform' => fake()->randomElement(['windows', 'macos', 'linux']),
            'trust_score' => fake()->numberBetween(0, 100),
            'is_blacklisted' => false,
            'is_virtual' => false,
            'last_seen_at' => now(),
        ];
    }
}
