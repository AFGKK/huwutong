<?php

namespace Database\Factories;

use App\Models\DataCenter;
use Illuminate\Database\Eloquent\Factories\Factory;

class DataCenterFactory extends Factory
{
    protected $model = DataCenter::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company . ' DC',
            'code' => $this->faker->unique()->regexify('[a-z]{2}-[a-z]+-[0-9]'),
            'region' => $this->faker->randomElement(['asia', 'europe', 'us', 'oceania']),
            'country_code' => $this->faker->countryCode(),
            'city' => $this->faker->city(),
            'is_active' => true,
            'sort_order' => $this->faker->numberBetween(0, 10),
            'status' => 'healthy',
            'current_latency_ms' => $this->faker->randomFloat(2, 10, 150),
            'current_load' => $this->faker->randomFloat(2, 20, 80),
            'capabilities' => ['compute', 'storage'],
        ];
    }
}
