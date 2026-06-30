<?php

namespace Database\Factories;

use App\Models\LicenseContract;
use App\Models\LicenseContractAssignment;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class LicenseContractAssignmentFactory extends Factory
{
    protected $model = LicenseContractAssignment::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'contract_id' => LicenseContract::factory(),
            'assignable_type' => 'App\\Models\\License',
            'assignable_id' => 1,
            'is_enabled' => true,
            'priority' => 0,
        ];
    }
}
