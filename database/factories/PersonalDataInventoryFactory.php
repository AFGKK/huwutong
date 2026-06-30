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
            'field_name' => $this->faker->randomElement(['name', 'email', 'phone', 'address']),
            'table_name' => $this->faker->randomElement(['users', 'customers', 'invoices']),
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
