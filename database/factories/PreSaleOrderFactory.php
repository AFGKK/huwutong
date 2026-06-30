<?php

namespace Database\Factories;

use App\Models\PreSaleCampaign;
use App\Models\PreSaleOrder;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PreSaleOrderFactory extends Factory
{
    protected $model = PreSaleOrder::class;

    public function definition(): array
    {
        return [
            'campaign_id' => PreSaleCampaign::factory(),
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'order_no' => 'PS' . date('Ymd') . strtoupper($this->faker->bothify('??##??##')),
            'total_amount' => $this->faker->randomFloat(2, 100, 5000),
            'deposit_paid' => 0,
            'final_payment' => 0,
            'final_paid' => 0,
            'currency' => 'CNY',
            'payment_status' => 'deposit_pending',
            'fulfillment_status' => 'pending',
            'quantity' => 1,
        ];
    }
}
