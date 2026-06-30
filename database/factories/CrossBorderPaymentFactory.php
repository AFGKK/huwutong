<?php

namespace Database\Factories;

use App\Models\CrossBorderPayment;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class CrossBorderPaymentFactory extends Factory
{
    protected $model = CrossBorderPayment::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'currency' => $this->faker->randomElement(['USD', 'EUR', 'JPY', 'GBP', 'HKD']),
            'amount' => $this->faker->randomFloat(2, 10, 5000),
            'amount_cny' => $this->faker->randomFloat(2, 50, 30000),
            'exchange_rate' => $this->faker->randomFloat(6, 6, 8),
            'payment_gateway' => $this->faker->randomElement(['stripe', 'alipay']),
            'customer_country' => $this->faker->randomElement(['US', 'JP', 'GB', 'HK', 'SG']),
            'merchant_country' => 'CN',
            'gateway_fee' => $this->faker->randomFloat(4, 0.1, 50),
            'gateway_fee_cny' => $this->faker->randomFloat(4, 1, 300),
            'status' => 'completed',
        ];
    }
}
