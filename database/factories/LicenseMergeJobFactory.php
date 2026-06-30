<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\LicenseMergeJob;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LicenseMergeJobFactory extends Factory
{
    protected $model = LicenseMergeJob::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'source_customer_id' => Customer::factory(),
            'target_customer_id' => Customer::factory(),
            'status' => 'completed',
            'total_licenses' => fake()->numberBetween(1, 20),
            'merged_licenses' => 0,
            'skipped_licenses' => 0,
            'failed_licenses' => 0,
            'total_devices' => fake()->numberBetween(0, 10),
            'migrated_devices' => 0,
            'summary' => [
                'licenses_moved' => fake()->numberBetween(1, 10),
                'devices_migrated' => fake()->numberBetween(0, 5),
                'licenses_retired' => fake()->numberBetween(0, 3),
                'licenses_skipped' => 0,
            ],
            'merge_audit' => [
                ['action' => 'created', 'at' => now()->toIso8601String()],
                ['action' => 'completed', 'at' => now()->toIso8601String()],
            ],
            'merged_by' => User::factory(),
            'merged_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn(array $attrs) => [
            'status' => 'pending',
            'merged_by' => null,
            'merged_at' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn(array $attrs) => [
            'status' => 'failed',
            'errors' => ['Something went wrong during merge'],
        ]);
    }
}
