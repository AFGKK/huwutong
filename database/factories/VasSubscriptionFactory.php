<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\VasService;
use App\Models\VasSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

class VasSubscriptionFactory extends Factory
{
    protected $model = VasSubscription::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'vas_service_id' => VasService::factory(),
            'status' => 'active',
            'start_date' => now()->format('Y-m-d'),
            'billing_period' => 'monthly',
            'price' => 100,
            'currency' => 'CNY',
        ];
    }
}
