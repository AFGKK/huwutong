<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'customer_id' => Customer::factory(),
            'product_id' => Product::factory(),
            'status' => 'active',
            'plan' => 'standard',
            'price' => 99,
            'currency' => 'CNY',
            'billing_period' => 'monthly',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'grace_days' => 7,
            'auto_renew' => true,
            'next_billing_at' => now()->addMonth(),
        ];
    }
}
