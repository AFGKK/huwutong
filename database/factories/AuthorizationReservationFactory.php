<?php

namespace Database\Factories;

use App\Models\AuthorizationReservation;
use App\Models\License;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuthorizationReservationFactory extends Factory
{
    protected $model = AuthorizationReservation::class;

    public function definition(): array
    {
        return [
            'license_id' => License::factory(),
            'tenant_id' => fn(array $attrs) => License::find($attrs['license_id'])?->tenant_id
                ?? throw new \RuntimeException('License not found, cannot resolve tenant_id'),
            'reservation_token' => fake()->uuid(),
            'fingerprint' => fake()->sha256(),
            'ip_address' => fake()->ipv4(),
            'payload' => ['platform' => fake()->randomElement(['windows', 'macos', 'linux'])],
            'status' => 'reserved',
            'expires_at' => now()->addMinutes(5),
        ];
    }

    public function reserved(): static
    {
        return $this->state([
            'status' => 'reserved',
            'expires_at' => now()->addMinutes(5),
            'committed_at' => null,
            'cancelled_at' => null,
        ]);
    }

    public function expired(): static
    {
        return $this->state([
            'status' => 'expired',
            'expires_at' => now()->subMinute(),
            'cancelled_at' => now(),
        ]);
    }

    public function committed(): static
    {
        return $this->state([
            'status' => 'committed',
            'committed_at' => now(),
            'cancelled_at' => null,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state([
            'status' => 'cancelled',
            'committed_at' => null,
            'cancelled_at' => now(),
        ]);
    }
}
