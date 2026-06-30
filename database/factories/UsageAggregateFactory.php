<?php

namespace Database\Factories;

use App\Models\UsageAggregate;
use App\Models\Tenant;
use App\Models\License;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class UsageAggregateFactory extends Factory
{
    protected $model = UsageAggregate::class;

    public function definition(): array
    {
        $start = Carbon::now()->startOfMonth();
        return [
            'tenant_id' => Tenant::factory(),
            'license_id' => License::factory(),
            'customer_id' => Customer::factory(),
            'metric_key' => 'api_call.validate',
            'period' => 'monthly',
            'period_start' => $start,
            'period_end' => Carbon::now(),
            'total_quantity' => $this->faker->numberBetween(10, 5000),
            'record_count' => $this->faker->numberBetween(1, 100),
        ];
    }
}
