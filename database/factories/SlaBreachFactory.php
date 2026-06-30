<?php

namespace Database\Factories;

use App\Models\SlaBreach;
use App\Models\SlaContract;
use App\Models\SlaMetric;
use Illuminate\Database\Eloquent\Factories\Factory;

class SlaBreachFactory extends Factory
{
    protected $model = SlaBreach::class;

    public function definition(): array
    {
        return [
            'sla_contract_id' => SlaContract::factory(),
            'sla_metric_id' => SlaMetric::factory(),
            'breach_type' => $this->faker->randomElement(['response_time', 'resolution_time', 'uptime', 'availability']),
            'severity' => $this->faker->randomElement(['minor', 'major', 'critical']),
            'description' => $this->faker->sentence(),
            'expected_value' => $this->faker->randomFloat(1, 10, 100),
            'actual_value' => $this->faker->randomFloat(1, 100, 200),
            'deviation' => $this->faker->randomFloat(1, 5, 50),
            'context' => ['source' => 'test'],
            'status' => $this->faker->randomElement(['open', 'acknowledged', 'resolved']),
        ];
    }
}
