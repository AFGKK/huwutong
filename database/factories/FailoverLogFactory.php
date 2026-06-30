<?php

namespace Database\Factories;

use App\Models\FailoverLog;
use App\Models\FailoverRule;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class FailoverLogFactory extends Factory
{
    protected $model = FailoverLog::class;

    public function definition(): array
    {
        return [
            'failover_rule_id' => FailoverRule::factory(),
            'tenant_id' => Tenant::factory(),
            'action' => $this->faker->randomElement(['failover', 'restore', 'manual_failover']),
            'from_dc' => $this->faker->regexify('[a-z]{2}-[a-z]+-[0-9]'),
            'to_dc' => $this->faker->regexify('[a-z]{2}-[a-z]+-[0-9]'),
            'trigger_reason' => $this->faker->sentence(),
            'is_automatic' => $this->faker->boolean(),
        ];
    }
}
