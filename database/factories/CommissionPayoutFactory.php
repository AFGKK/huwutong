<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\CommissionPayout;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommissionPayoutFactory extends Factory
{
    protected $model = CommissionPayout::class;

    public function definition(): array
    {
        return [
            'agent_id' => Agent::factory(),
            'amount' => fake()->randomFloat(2, 100, 10000),
            'fee' => fake()->randomFloat(2, 0, 100),
            'net_amount' => fn(array $attrs) => ($attrs['amount'] ?? 0) - ($attrs['fee'] ?? 0),
            'status' => 'pending',
            'payout_method' => 'alipay',
            'account_info' => encrypt(json_encode(['alipay_account' => 'test@alipay.com'])),
            'requested_at' => now(),
        ];
    }
}
