<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaxComplianceRuleFactory extends Factory
{
    protected $model = \App\Models\TaxComplianceRule::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => '测试规则',
            'rule_type' => 'reduced_rate',
            'action' => 'reduce_rate',
            'rate_modifier' => 0.50,
            'is_active' => true,
        ];
    }
}
