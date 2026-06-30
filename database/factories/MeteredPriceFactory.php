<?php

namespace Database\Factories;

use App\Models\MeteredPrice;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class MeteredPriceFactory extends Factory
{
    protected $model = MeteredPrice::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'metric_key' => $this->faker->randomElement([
                'api_call.validate', 'api_call.activate', 'api_call.revoke',
                'device.active', 'storage.used_bytes', 'ai.tokens_used',
            ]),
            'name' => $this->faker->words(2, true),
            'unit' => 'count',
            'billing_period' => 'monthly',
            'tiers' => [
                ['from' => 0, 'to' => 1000, 'unit_price' => 0.01],
                ['from' => 1001, 'to' => null, 'unit_price' => 0.005],
            ],
            'base_fee' => 0,
            'included_quantity' => 0,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
