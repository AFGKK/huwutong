<?php

namespace Database\Factories;

use App\Models\CurrencyConversionLog;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class CurrencyConversionLogFactory extends Factory
{
    protected $model = CurrencyConversionLog::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'from_currency' => $this->faker->randomElement(['USD', 'EUR', 'JPY']),
            'to_currency' => 'CNY',
            'from_amount' => $this->faker->randomFloat(2, 10, 1000),
            'to_amount' => $this->faker->randomFloat(2, 50, 7000),
            'rate_used' => $this->faker->randomFloat(6, 6, 8),
            'rate_markup' => 0,
            'conversion_type' => 'auto',
            'source' => $this->faker->randomElement(['pricing', 'invoice']),
        ];
    }
}
