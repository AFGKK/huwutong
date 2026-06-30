<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AgentFactory extends Factory
{
    protected $model = Agent::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'agent_code' => 'AGT' . strtoupper(fake()->bothify('??????')),
            'level' => 'regular',
            'status' => 'active',
            'total_earned' => 0,
            'total_withdrawn' => 0,
            'commission_rate' => 10.00,
        ];
    }
}
