<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\SlaCompensation;
use App\Models\SlaContract;
use Illuminate\Database\Eloquent\Factories\Factory;

class SlaCompensationFactory extends Factory
{
    protected $model = SlaCompensation::class;

    public function definition(): array
    {
        return [
            'sla_contract_id' => SlaContract::factory(),
            'sla_breach_id' => null,
            'tenant_id' => Tenant::factory(),
            'customer_id' => null,
            'compensation_type' => $this->faker->randomElement(['credit', 'discount', 'extension', 'refund']),
            'severity' => $this->faker->randomElement(['minor', 'major', 'critical']),
            'amount' => $this->faker->randomFloat(2, 50, 1000),
            'currency' => 'CNY',
            'reason' => $this->faker->sentence(),
            'calculation_method' => 'automatic',
            'status' => $this->faker->randomElement(['pending', 'approved', 'issued', 'rejected']),
        ];
    }
}
