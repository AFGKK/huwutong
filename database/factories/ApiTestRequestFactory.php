<?php

namespace Database\Factories;

use App\Models\ApiDocEndpoint;
use App\Models\ApiTestRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ApiTestRequestFactory extends Factory
{
    protected $model = ApiTestRequest::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'endpoint_id' => ApiDocEndpoint::factory(),
            'method' => $this->faker->randomElement(['GET', 'POST']),
            'url' => $this->faker->url,
            'status' => 'success',
            'response_status' => 200,
            'response_time_ms' => $this->faker->numberBetween(10, 500),
        ];
    }
}
