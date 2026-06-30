<?php

namespace Database\Factories;

use App\Models\Refund;
use App\Models\License;
use App\Models\Invoice;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RefundFactory extends Factory
{
    protected $model = Refund::class;

    public function definition(): array
    {
        return [
            'license_id' => License::factory(),
            'invoice_id' => Invoice::factory(),
            'customer_id' => Customer::factory(),
            'processed_by' => User::factory(),
            'refund_no' => 'RF' . now()->format('YmdHis') . strtoupper(substr(uniqid(), -6)),
            'amount' => 100.00,
            'currency' => 'CNY',
            'reason' => 'test refund',
            'status' => 'completed',
            'completed_at' => now(),
        ];
    }
}
