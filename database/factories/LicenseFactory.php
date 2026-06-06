<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class LicenseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'product_id' => Product::factory(),
            'license_key' => 'HWT-'.strtoupper(fake()->bothify('????-####-????-####')),
            'type' => 'standard',
            'status' => 'pending',
            'expires_at' => now()->addYear(),
            'seats' => 1,
            'max_devices' => 3,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => 'active',
            'activated_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => 'expired',
            'expires_at' => now()->subDay(),
        ]);
    }
}
