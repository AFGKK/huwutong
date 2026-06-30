<?php

namespace Database\Factories;

use App\Models\IdsAlert;
use App\Models\IdsRule;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class IdsAlertFactory extends Factory
{
    protected $model = IdsAlert::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'ids_rule_id' => IdsRule::factory(),
            'rule_slug' => 'test-rule',
            'rule_name' => '测试规则',
            'detection_type' => $this->faker->randomElement(['brute_force', 'geo_anomaly', 'suspicious_pattern']),
            'severity' => $this->faker->randomElement(['info', 'warning', 'critical']),
            'source_ip' => $this->faker->ipv4(),
            'evidence' => ['event_id' => 1, 'user_agent' => 'Mozilla/5.0'],
            'matched_conditions' => ['event_type' => 'login_failed'],
            'status' => 'open',
        ];
    }
}
