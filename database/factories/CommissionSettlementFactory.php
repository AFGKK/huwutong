<?php

namespace Database\Factories;

use App\Models\Agent;
use App\Models\Invoice;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommissionSettlementFactory extends Factory
{
    protected $model = \App\Models\CommissionSettlement::class;

    public function definition(): array
    {
        return [
            'agent_id' => Agent::factory(),
            'subscription_id' => Subscription::factory(),
            'invoice_id' => Invoice::factory(),
            'period' => now()->format('Y-m'),
            'status' => 'pending',
            'invoice_amount' => 1000.00,
            'commission_rate' => 10.00,
            'commission_amount' => 100.00,
            'rate_type' => 'percentage',
            'settlement_type' => 'subscription',
            'settled_at' => now(),
            'released_at' => now()->addDays(30),
        ];
    }
}
