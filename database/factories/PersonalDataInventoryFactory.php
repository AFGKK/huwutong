<?php

namespace Database\Factories;

use App\Models\PersonalDataInventory;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class PersonalDataInventoryFactory extends Factory
{
    protected $model = PersonalDataInventory::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'field_name' => fake()->unique()->bothify('field_##??'),
            'table_name' => 'users',
            'category' => $this->faker->randomElement(['person', 'general', 'sensitive']),
            'classification' => $this->faker->randomElement(['L1', 'L2', 'L3']),
            'purpose' => '业务运营',
            'retention_days' => '365',
            'is_required' => false,
            'is_exportable' => true,
            'is_deletable' => true,
        ];
    }
}
