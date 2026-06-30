<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EarningsAccountFactory extends Factory
{
    protected $model = \App\Models\EarningsAccount::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'type' => 'agent',
            'pending_balance' => 0,
            'available_balance' => 0,
            'total_withdrawn' => 0,
            'frozen_amount' => 0,
            'status' => 'active',
        ];
    }
}
