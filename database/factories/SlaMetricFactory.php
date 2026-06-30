<?php

namespace Database\Factories;

use App\Models\SlaContract;
use App\Models\SlaMetric;
use Illuminate\Database\Eloquent\Factories\Factory;

class SlaMetricFactory extends Factory
{
    protected $model = SlaMetric::class;

    public function definition(): array
    {
        return [
            'sla_contract_id' => SlaContract::factory(),
            'metric_key' => $this->faker->randomElement(['response_time', 'resolution_time', 'uptime', 'availability', 'ticket_backlog']),
            'name' => $this->faker->word() . '指标',
            'unit' => $this->faker->randomElement(['minutes', 'hours', 'percentage', 'count']),
            'target_value' => $this->faker->randomFloat(1, 10, 100),
            'warning_threshold' => $this->faker->numberBetween(70, 95),
            'measurement_window' => 'daily',
            'data_source' => 'tickets',
            'is_active' => true,
        ];
    }
}
