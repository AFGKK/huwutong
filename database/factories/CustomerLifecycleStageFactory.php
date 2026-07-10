<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerLifecycleStageFactory extends Factory
{
    protected $model = \App\Models\CustomerLifecycleStage::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'customer_id' => \App\Models\Customer::factory(),
            'stage' => 'active',
            'previous_stage' => 'onboarding',
            'triggered_by' => 'auto',
            'entered_at' => now(),
        ];
    }
}
