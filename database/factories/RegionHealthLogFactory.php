<?php

namespace Database\Factories;

use App\Models\RegionHealthLog;
use App\Models\DataCenter;
use Illuminate\Database\Eloquent\Factories\Factory;

class RegionHealthLogFactory extends Factory
{
    protected $model = RegionHealthLog::class;

    public function definition(): array
    {
        return [
            'data_center_id' => DataCenter::factory(),
            'latency_ms' => $this->faker->randomFloat(2, 5, 300),
            'load' => $this->faker->randomFloat(2, 10, 95),
            'is_healthy' => true,
            'check_type' => 'ping',
            'checked_at' => now(),
        ];
    }
}
