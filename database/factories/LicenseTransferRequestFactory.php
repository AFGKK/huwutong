<?php

namespace Database\Factories;

use App\Models\License;
use App\Models\LicenseTransferRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LicenseTransferRequestFactory extends Factory
{
    protected $model = LicenseTransferRequest::class;

    public function definition(): array
    {
        return [
            'reference' => 'TX-' . now()->format('Ymd') . '-' . strtoupper(fake()->bothify('######')),
            'type' => 'device_transfer',
            'status' => 'pending',
            'license_id' => License::factory(),
            'requested_by' => User::factory(),
            'reason' => fake()->sentence(),
            'request_ip' => fake()->ipv4(),
            'source_info' => [
                'customer_name' => fake()->company(),
                'devices_count' => fake()->numberBetween(1, 5),
                'device_ids' => [],
            ],
            'audit_log' => [
                ['action' => 'created', 'by' => 1, 'at' => now()->toIso8601String()],
            ],
        ];
    }

    public function deviceTransfer(): static
    {
        return $this->state(fn(array $attrs) => [
            'type' => 'device_transfer',
            'target_device_fingerprint' => 'v2:' . fake()->sha256(),
            'target_device_name' => fake()->word() . '-PC',
        ]);
    }

    public function customerTransfer(): static
    {
        return $this->state(fn(array $attrs) => [
            'type' => 'customer_transfer',
        ]);
    }

    public function userTransfer(): static
    {
        return $this->state(fn(array $attrs) => [
            'type' => 'user_transfer',
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn(array $attrs) => [
            'status' => 'approved',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn(array $attrs) => [
            'status' => 'completed',
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn(array $attrs) => [
            'status' => 'rejected',
        ]);
    }
}
