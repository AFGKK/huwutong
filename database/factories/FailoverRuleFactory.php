<?php

namespace Database\Factories;

use App\Models\FailoverRule;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class FailoverRuleFactory extends Factory
{
    protected $model = FailoverRule::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->sentence(3),
            'primary_dc_id' => DataCenterFactory::new(),
            'backup_dc_id' => DataCenterFactory::new(),
            'trigger_type' => 'latency',
            'trigger_threshold_ms' => 300,
            'failure_count_threshold' => 3,
            'auto_failover' => false,
            'is_active' => true,
            'status' => 'active',
        ];
    }
}
