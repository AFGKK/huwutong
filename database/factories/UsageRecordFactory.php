<?php

namespace Database\Factories;

use App\Models\UsageRecord;
use App\Models\Tenant;
use App\Models\License;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class UsageRecordFactory extends Factory
{
    protected $model = UsageRecord::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'license_id' => License::factory(),
            'customer_id' => Customer::factory(),
            'metric_key' => 'api_call.validate',
            'action' => 'validate',
            'window_type' => 'monthly',
            'quantity' => $this->faker->numberBetween(1, 100),
            'unit' => 'count',
            'context' => null,
            'recorded_at' => now(),
        ];
    }
}
