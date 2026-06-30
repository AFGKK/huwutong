<?php

namespace Database\Factories;

use App\Models\SlaContract;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class SlaContractFactory extends Factory
{
    protected $model = SlaContract::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->company() . ' SLA',
            'slug' => $this->faker->unique()->slug(),
            'level' => $this->faker->randomElement(['standard', 'premium', 'enterprise', 'custom']),
            'description' => $this->faker->sentence(),
            'effective_date' => now()->format('Y-m-d'),
            'is_active' => true,
        ];
    }

    public function premium(): static
    {
        return $this->state(fn(array $attr) => [
            'level' => 'premium',
            'penalties' => [
                'compensation_type' => 'credit',
                'auto_approve' => false,
                'currency' => 'CNY',
                'amounts' => ['minor' => 100, 'major' => 300, 'critical' => 1000],
            ],
        ]);
    }
}
