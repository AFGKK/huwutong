<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'customer_id' => Customer::factory(),
            'subscription_id' => Subscription::factory(),
            'invoice_no' => 'INV-' . strtoupper(fake()->bothify('########')),
            'amount' => fake()->randomFloat(2, 10, 999),
            'currency' => 'CNY',
            'status' => 'pending',
        ];
    }
}
