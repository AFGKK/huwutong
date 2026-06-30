<?php

namespace Database\Factories;

use App\Models\LicenseContract;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class LicenseContractFactory extends Factory
{
    protected $model = LicenseContract::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->words(3, true) . '合约',
            'slug' => $this->faker->unique()->slug(2),
            'description' => $this->faker->sentence(),
            'contract_type' => $this->faker->randomElement(array_keys(LicenseContract::CONTRACT_TYPES)),
            'conditions' => [
                ['type' => 'time_window', 'operator' => 'between', 'field' => 'current_time', 'days' => [1, 2, 3, 4, 5], 'start_time' => '09:00', 'end_time' => '18:00', 'label' => '工作时间'],
            ],
            'actions' => null,
            'evaluation_mode' => 'all',
            'grant_template' => null,
            'is_active' => true,
            'is_system' => false,
            'version' => 1,
            'priority' => 100,
        ];
    }

    public function timeRestricted(): static
    {
        return $this->state(fn(array $attrs) => [
            'contract_type' => 'time',
            'conditions' => [
                ['type' => 'time_window', 'operator' => 'between', 'field' => 'current_time',
                 'days' => [1, 2, 3, 4, 5], 'start_time' => '09:00', 'end_time' => '18:00',
                 'timezone' => 'Asia/Shanghai', 'label' => '工作日 9:00-18:00'],
            ],
        ]);
    }

    public function system(): static
    {
        return $this->state(fn(array $attrs) => [
            'is_system' => true,
        ]);
    }
}
